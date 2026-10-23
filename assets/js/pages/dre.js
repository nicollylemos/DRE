/* DRE: demonstrativo mês a mês com AV% e comparação com o ano anterior. */
(function () {
  const st = { ano: new Date().getFullYear(), dados: null, abertos: { income: true, expense: true } };
  const params = new URLSearchParams(location.search);
  if (params.get('ano')) st.ano = Number(params.get('ano')) || st.ano;
  try { const p = JSON.parse(localStorage.getItem('dre.prefs') || '{}'); if (p.agrupar) fset('dre-agrupar', p.agrupar); if (p.regime) fset('dre-regime', p.regime); if (p.meses === false) $('dre-meses').checked = false; } catch (e) {}

  const anos = anosLista(); if (!anos.includes(st.ano)) anos.push(st.ano);
  $('dre-ano').innerHTML = anos.sort((a, b) => b - a).map(a => `<option value="${a}">${a}</option>`).join('');
  $('dre-ano').value = String(st.ano);

  const q = () => `year=${st.ano}&agrupar=${fv('dre-agrupar')}&regime=${fv('dre-regime')}`;
  function salvarPrefs() { try { localStorage.setItem('dre.prefs', JSON.stringify({ agrupar: fv('dre-agrupar'), regime: fv('dre-regime'), meses: $('dre-meses').checked })); } catch (e) {} }

  async function carregar() {
    salvarPrefs();
    document.querySelector('#dre-table tbody').innerHTML = '<tr><td class="empty" colspan="20">Carregando…</td></tr>';
    const r = await api('dre/demonstrativo?' + q());
    if (r.error) { toast(r.error, 'err'); return; }
    st.dados = r;
    render();
  }

  function render() {
    const d = st.dados; if (!d) return;
    const comMeses = $('dre-meses').checked, ant = d.tem_anterior;
    const L = tipo => d.linhas.find(l => l.tipo === tipo) || { total: 0, anterior: 0 };
    const liq = L('subtotal'), res = L('resultado'), desp = d.linhas.find(l => l.tipo === 'grupo' && l.label.includes('Despesas')) || { total: 0 };
    const ah = (a, b) => b ? (a - b) / Math.abs(b) : null;
    $('dre-k-liq').textContent = fmtBRL(liq.total);
    $('dre-k-liq-sub').innerHTML = ant ? varTxt(ah(liq.total, liq.anterior)) + ` vs ${d.ano - 1}` : '';
    $('dre-k-desp').textContent = fmtBRL(desp.total);
    $('dre-k-desp-sub').innerHTML = ant ? varTxt(ah(desp.total, desp.anterior), true) + ` vs ${d.ano - 1}` : '';
    $('dre-k-res').textContent = fmtBRLs(res.total);
    $('dre-k-res').classList.toggle('neg-on-dark', res.total < 0);
    $('dre-k-res-sub').textContent = res.total < 0 ? 'prejuízo no exercício' : 'lucro no exercício';
    $('dre-k-margem').textContent = fmtPct(d.margem.total);
    $('dre-k-margem-sub').textContent = 'resultado ÷ receita líquida';

    const meses = comMeses ? d.meses.map(m => `<th class="r mes">${m}</th>`).join('') : '';
    $('dre-table').querySelector('thead').innerHTML = `<tr><th class="conta">Conta</th>${meses}<th class="r tot">Total ${d.ano}</th><th class="r">AV %</th>${ant ? `<th class="r">${d.ano - 1}</th><th class="r">AH %</th>` : ''}</tr>`;

    let natureza = 'income';
    const rows = d.linhas.map(l => {
      if (l.tipo === 'grupo') natureza = l.label.includes('Despesas') ? 'expense' : 'income';
      const item = l.tipo === 'item';
      if (item && !st.abertos[l.natureza]) return '';
      const cls = { grupo: 'dre-grupo', item: 'dre-item', deducao: 'dre-ded', subtotal: 'dre-sub', resultado: 'dre-res' + (l.total < 0 ? ' neg-res' : '') }[l.tipo];
      const seta = l.tipo === 'grupo' ? `<button type="button" class="dre-tog ${st.abertos[natureza] ? 'on' : ''}" data-nat="${natureza}" aria-label="Mostrar/ocultar itens"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>` : '';
      const val = v => `<td class="r money ${l.tipo === 'resultado' && v < 0 ? 'neg' : ''}">${v ? (l.tipo === 'resultado' ? fmtBRLs(v) : fmtBRL(v)) : '<span class="dim">—</span>'}</td>`;
      const label = item && l.cor ? `<span class="catg"><i style="background:${esc(l.cor)}"></i>${esc(l.label)}</span>` : esc(l.label);
      return `<tr class="${cls}"><td class="conta">${seta}${label}</td>${comMeses ? l.valores.map(val).join('') : ''}<td class="r money tot">${l.tipo === 'resultado' ? fmtBRLs(l.total) : fmtBRL(l.total)}</td>
        <td class="r num dim-t">${fmtPct(l.av)}</td>${ant ? `<td class="r money dim-t">${fmtBRL(l.anterior)}</td><td class="r num">${varTxt(l.ah, l.tipo === 'deducao' || (natureza === 'expense' && l.tipo !== 'resultado' && l.tipo !== 'subtotal'))}</td>` : ''}</tr>`;
    }).join('');
    const margem = `<tr class="dre-margem"><td class="conta">Margem líquida</td>${comMeses ? d.margem.valores.map(v => `<td class="r num ${v !== null && v < 0 ? 'neg' : ''}">${fmtPct(v)}</td>`).join('') : ''}<td class="r num tot">${fmtPct(d.margem.total)}</td><td></td>${ant ? '<td></td><td></td>' : ''}</tr>`;
    const vazio = !d.linhas.some(l => l.total);
    document.querySelector('#dre-table tbody').innerHTML = vazio
      ? `<tr><td class="empty" colspan="20"><strong>Nenhum lançamento em ${d.ano}</strong>${fv('dre-regime') === 'caixa' ? 'No regime de caixa só entra o que já foi quitado.' : 'Troque o ano ou registre lançamentos.'}</td></tr>`
      : rows + margem;
    document.querySelectorAll('.dre-tog').forEach(b => b.onclick = () => { st.abertos[b.dataset.nat] = !st.abertos[b.dataset.nat]; render(); });
  }
  /** ▲ 12,3% (verde se bom). inverso = despesa (subir é ruim). */
  function varTxt(v, inverso) {
    if (v === null || v === undefined || !isFinite(v)) return '<span class="dim">—</span>';
    const bom = inverso ? v <= 0 : v >= 0;
    return `<span class="var ${bom ? 'pos' : 'neg'}">${v >= 0 ? '▲' : '▼'} ${fmtPct(Math.abs(v))}</span>`;
  }

  function mudarAno(delta) {
    st.ano += delta;
    if (![...$('dre-ano').options].some(o => Number(o.value) === st.ano)) $('dre-ano').insertAdjacentHTML('beforeend', `<option value="${st.ano}">${st.ano}</option>`);
    $('dre-ano').value = String(st.ano); carregar();
  }
  $('dre-ano').addEventListener('change', () => { st.ano = Number($('dre-ano').value); carregar(); });
  $('dre-prev').addEventListener('click', () => mudarAno(-1));
  $('dre-next').addEventListener('click', () => mudarAno(1));
  ['dre-agrupar', 'dre-regime'].forEach(id => $(id).addEventListener('change', carregar));
  $('dre-meses').addEventListener('change', () => { salvarPrefs(); render(); });
  $('dre-excel').addEventListener('click', () => baixar('export/dre-excel?' + q()));
  $('dre-pdf').addEventListener('click', () => baixar('export/pdf?' + q()));

  carregar();
})();
