/* Dashboard: KPIs do exercício, gráfico mensal, próximos vencimentos, saldos e categorias. */
(function () {
  const dash = { ano: new Date().getFullYear() };
  $('dash-ano').innerHTML = anosLista().map(a => `<option value="${a}" ${a === dash.ano ? 'selected' : ''}>${a}</option>`).join('');
  $('dash-ano').addEventListener('change', () => { dash.ano = Number($('dash-ano').value); carregar(); });
  $('btn-dash-excel').addEventListener('click', () => baixar(`export/excel?year=${dash.ano}`));

  async function carregar() {
    const r = await api('dre?year=' + dash.ano);
    if (r.error) { toast(r.error, 'err'); return; }
    const L = r.dreLines || {};
    const bruta = L['Receita Bruta'] || 0, ded = L['(-) Impostos e Deduções'] || 0, liq = L['Receita Líquida'] || 0;
    const desp = L['(-) Despesas Operacionais'] || 0, lucro = L['Lucro / Prejuízo Líquido'] || 0;
    $('dash-rec').textContent = fmtBRL(bruta);
    $('dash-rec-sub').textContent = ded ? `Taxas/deduções ${fmtBRL(ded)}` : 'sem taxas no período';
    $('dash-desp').textContent = fmtBRL(desp);
    $('dash-desp-sub').textContent = liq ? `${Math.round(desp / liq * 100)}% da receita líquida` : '';
    $('dash-lucro').textContent = fmtBRLs(lucro);
    $('dash-lucro').classList.toggle('neg-on-dark', lucro < 0);
    $('dash-lucro-sub').textContent = lucro < 0 ? 'prejuízo no exercício' : 'resultado do exercício';
    $('dash-margem').textContent = liq ? fmtPct(lucro / liq) : '—';

    const ab = r.emAberto || {};
    $('dash-areceber').textContent = fmtBRL(ab.a_receber);
    $('dash-apagar').textContent = fmtBRL(ab.a_pagar);
    $('dash-vencidos').textContent = String(Math.round(ab.qtd_vencidos || 0));
    $('dash-vencidos').className = ab.qtd_vencidos ? 'neg' : '';
    $('dash-vencidos-sub').textContent = ab.qtd_vencidos ? `${fmtBRL(ab.pagar_vencido)} a pagar · ${fmtBRL(ab.receber_vencido)} a receber` : 'nada vencido';
    $('dash-saldo-cx').textContent = fmtBRLs(r.saldoCaixas);
    $('dash-saldo-cx').className = r.saldoCaixas < 0 ? 'neg' : '';

    const linha = (cls, k, v) => `<div class="${cls}"><dt>${k}</dt><dd>${v}</dd></div>`;
    $('dash-dre').innerHTML =
      linha('tot', 'Receita bruta', fmtBRL(bruta)) +
      linha('sub', '(−) Impostos, taxas e deduções', fmtBRL(ded)) +
      linha('tot', 'Receita líquida', fmtBRL(liq)) +
      linha('sub', '(−) Despesas operacionais', fmtBRL(desp)) +
      linha('res' + (lucro < 0 ? ' neg-res' : ''), lucro < 0 ? 'Prejuízo líquido' : 'Lucro líquido', fmtBRLs(lucro));

    renderCompara(r.comparativo);
    const meses = r.summary || [];
    renderChartMeses($('dash-chart'), meses.map(m => m.receitas - m.deducoes), meses.map(m => m.despesas));
    renderProximos(r.proximos || []);
    renderCaixas(r.caixas || []);
    $('dash-cat-ano').textContent = `Exercício ${dash.ano}`;
    renderCategorias(r.expensesByCategory || {}, r.categoryColors || {});
  }

  // Mesmo período do ano anterior
  function renderCompara(c) {
    const el = $('dash-compara');
    if (!c || (!c.anterior.receita && !c.anterior.despesa)) { el.innerHTML = `<p class="muted">Sem dados de ${dash.ano - 1} para comparar.</p>`; return; }
    const periodo = c.ate_mes === 12 ? `ano todo` : `jan–${MESES_CURTOS[c.ate_mes - 1].toLowerCase()}`;
    const var_ = (a, b, inverso) => {
      if (!b) return '<span class="dim">—</span>';
      const v = (a - b) / Math.abs(b), bom = inverso ? v <= 0 : v >= 0;
      return `<span class="var ${bom ? 'pos' : 'neg'}">${v >= 0 ? '▲' : '▼'} ${fmtPct(Math.abs(v))}</span>`;
    };
    el.innerHTML = `<div class="compara-h">Comparado a ${dash.ano - 1} (${periodo})</div>
      <div class="compara-l"><span>Receita líquida</span>${var_(c.atual.receita, c.anterior.receita)}</div>
      <div class="compara-l"><span>Despesas</span>${var_(c.atual.despesa, c.anterior.despesa, true)}</div>
      <div class="compara-l"><span>Resultado</span>${var_(c.atual.resultado, c.anterior.resultado)}</div>`;
  }

  function renderProximos(lista) {
    const el = $('dash-proximos');
    if (!lista.length) { el.innerHTML = '<div class="ag-empty">Nada vencendo nos próximos dias. 🎉</div>'; return; }
    const hoje = hojeISO();
    el.innerHTML = lista.map(x => {
      const dias = Math.round((new Date(x.venc + 'T12:00') - new Date(hoje + 'T12:00')) / 864e5);
      const quando = x.vencido ? `<span class="st late">${-dias} dia(s) atrasado</span>` : dias === 0 ? '<span class="st open">Hoje</span>' : `<span class="muted">${dias === 1 ? 'amanhã' : `em ${dias} dias`} · ${fmtData(x.venc).slice(0, 5)}</span>`;
      return `<a class="ag-item" href="${esc(url('calendario?dia=' + x.venc))}">
        <span class="bar-s ${x.tipo === 'income' ? 'in' : 'out'}"></span>
        <div class="tx"><b>${esc(x.descricao) || '<span class="dim">Sem descrição</span>'}</b><small>${esc([x.cliente_fornecedor, x.categoria_nome].filter(Boolean).join(' · ') || (x.tipo === 'income' ? 'A receber' : 'A pagar'))}</small></div>
        <div class="vl ${x.tipo === 'income' ? 'pos' : 'neg'}">${x.tipo === 'income' ? '+' : '−'} ${fmtBRL(x.valor)}<div>${quando}</div></div></a>`;
    }).join('');
  }

  function renderCaixas(lista) {
    const el = $('dash-caixas');
    if (!lista.length) { el.innerHTML = '<div class="ag-empty">Nenhuma conta cadastrada.</div>'; return; }
    const max = Math.max(...lista.map(c => Math.abs(c.saldo_atual)), 1);
    const TIPO = { banco: 'Banco', caixa: 'Caixa físico', investimento: 'Investimento' };
    el.innerHTML = lista.map(c => `<div class="saldo-row"><div class="nm">${avatar(c.nome)}<div><b>${esc(c.nome)}</b><small>${TIPO[c.tipo] || esc(c.tipo)}</small></div></div>
      <div class="vl ${c.saldo_atual < 0 ? 'neg' : ''}">${fmtBRLs(c.saldo_atual)}</div>
      <div class="trk"><span style="width:${(Math.abs(c.saldo_atual) / max * 100).toFixed(1)}%" class="${c.saldo_atual < 0 ? 'neg-bar' : ''}"></span></div></div>`).join('');
  }

  function renderCategorias(porCat, cores) {
    const itens = Object.entries(porCat).filter(([, v]) => v > 0).sort((a, b) => b[1] - a[1]);
    const total = itens.reduce((s, [, v]) => s + v, 0), max = itens.length ? itens[0][1] : 0;
    $('dash-cats').innerHTML = itens.length ? itens.slice(0, 10).map(([nome, v]) => {
      const cor = cores[nome] || 'var(--navy-2)';
      return `<div class="cat-row"><span class="nm"><i style="background:${esc(cor)}"></i>${esc(nome)}</span>
        <span class="vl">${fmtBRL(v)}<small>${Math.round(v / total * 100)}%</small></span>
        <div class="trk"><span style="width:${(v / max * 100).toFixed(1)}%;background:${esc(cor)}"></span></div></div>`;
    }).join('') : '<div class="empty-chart" style="grid-column:1/-1;padding:30px">Nenhuma despesa neste exercício.</div>';
  }

  // Barras agrupadas por mês: receita líquida x despesas. Tooltip por mês (hover/foco).
  function renderChartMeses(el, rec, desp) {
    const max = Math.max(...rec, ...desp, 0);
    if (!max) { el.innerHTML = '<div class="empty-chart">Sem lançamentos neste exercício.</div>'; return; }
    const W = 720, H = 250, pl = 54, pr = 6, pt = 10, pb = 26, ih = H - pt - pb;
    const passo = Math.pow(10, Math.floor(Math.log10(max))) / 2;
    const topo = Math.ceil(max / passo / 4) * passo * 4 || 1;
    const y = v => pt + ih - (v / topo) * ih;
    const gw = (W - pl - pr) / 12, bw = Math.min(18, (gw - 12) / 2);
    const barra = (x, v, cor) => {
      if (!v) return '';
      const h = Math.max(2, ih * v / topo), yy = pt + ih - h, r = Math.min(4, h, bw / 2);
      return `<path class="bar" fill="${cor}" d="M${x},${pt + ih}V${yy + r}Q${x},${yy} ${x + r},${yy}H${x + bw - r}Q${x + bw},${yy} ${x + bw},${yy + r}V${pt + ih}Z"/>`;
    };
    let g = '';
    for (let i = 0; i <= 4; i++) {
      const v = topo / 4 * i, yy = y(v);
      g += `<line class="grid-l" x1="${pl}" x2="${W - pr}" y1="${yy}" y2="${yy}"/><text class="axis-t" x="${pl - 8}" y="${yy + 4}" text-anchor="end">${fmtBRLk(v)}</text>`;
    }
    MESES_CURTOS.forEach((m, i) => {
      const x0 = pl + gw * i, cx = x0 + gw / 2;
      g += `<g class="col" data-i="${i}" tabindex="0"><rect class="hit" x="${x0 + 2}" y="${pt}" width="${gw - 4}" height="${ih}" rx="6"/>`
        + barra(cx - bw - 1, rec[i], 'var(--c-rec)') + barra(cx + 1, desp[i], 'var(--c-desp)')
        + `<text class="axis-t" x="${cx}" y="${H - 6}" text-anchor="middle">${m}</text></g>`;
    });
    el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Receita líquida e despesas por mês">${g}</svg><div class="chart-tip hidden"></div>`;
    const tip = el.querySelector('.chart-tip');
    const mostra = col => {
      const i = Number(col.dataset.i), saldo = rec[i] - desp[i];
      tip.innerHTML = `<b>${MESES[i]}</b><div><i style="background:var(--c-rec)"></i>Receita líquida<span class="r">${fmtBRL(rec[i])}</span></div>`
        + `<div><i style="background:var(--c-desp)"></i>Despesas<span class="r">${fmtBRL(desp[i])}</span></div>`
        + `<div style="margin-top:4px;border-top:1px solid rgba(255,255,255,.15);padding-top:4px">Resultado<span class="r">${fmtBRLs(saldo)}</span></div>`;
      tip.classList.remove('hidden');
      const cr = el.getBoundingClientRect(), rr = col.querySelector('.hit').getBoundingClientRect();
      let left = rr.left - cr.left + rr.width / 2;
      left = Math.min(Math.max(left, tip.offsetWidth / 2 + 4), cr.width - tip.offsetWidth / 2 - 4);
      const tops = [...col.querySelectorAll('.bar')].map(b => b.getBoundingClientRect().top);
      const topo2 = tops.length ? Math.min(...tops) : rr.bottom;
      tip.style.left = left + 'px'; tip.style.top = Math.max(topo2 - cr.top, tip.offsetHeight + 8) + 'px';
    };
    el.querySelectorAll('.col').forEach(c => {
      c.addEventListener('mouseenter', () => mostra(c)); c.addEventListener('focus', () => mostra(c));
      c.addEventListener('mouseleave', () => tip.classList.add('hidden')); c.addEventListener('blur', () => tip.classList.add('hidden'));
    });
  }

  carregar();
})();
