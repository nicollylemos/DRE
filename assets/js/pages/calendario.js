/* Calendário de vencimentos: contas a pagar e a receber do mês. */
(function () {
  const hoje0 = new Date();
  const cal = { ano: hoje0.getFullYear(), mes: hoje0.getMonth() + 1, dia: null, itens: [] };
  const ed = podeEditar('lancamentos');
  const params = new URLSearchParams(location.search);
  if (/^\d{4}-\d{2}-\d{2}$/.test(params.get('dia') || '')) {
    const [a, m] = params.get('dia').split('-').map(Number);
    cal.ano = a; cal.mes = m; cal.dia = params.get('dia');
  }

  async function carregar() {
    $('cal-titulo').textContent = `${MESES[cal.mes - 1]} de ${cal.ano}`;
    const r = await api(`agenda?year=${cal.ano}&month=${cal.mes}`);
    cal.itens = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    render();
  }
  function filtrados() {
    const tipo = fv('cal-f-tipo'), sit = fv('cal-f-sit');
    return cal.itens.filter(x => (!tipo || x.tipo === tipo) &&
      (!sit || (sit === 'vencido' ? x.vencido : x.status === sit)));
  }
  function render() { renderKpis(); renderGrade(); renderLista(); }

  function renderKpis() {
    const ab = cal.itens.filter(x => x.status === 'em_aberto');
    const soma = l => l.reduce((s, x) => s + x.valor, 0);
    const rec = ab.filter(x => x.tipo === 'income'), pag = ab.filter(x => x.tipo === 'expense'), venc = ab.filter(x => x.vencido);
    const recQ = cal.itens.filter(x => x.status === 'quitado' && x.tipo === 'income'), pagQ = cal.itens.filter(x => x.status === 'quitado' && x.tipo === 'expense');
    $('cal-k-rec').textContent = fmtBRL(soma(rec));
    $('cal-k-rec-sub').textContent = `${rec.length} em aberto${recQ.length ? ` · ${fmtBRL(soma(recQ))} já recebido` : ''}`;
    $('cal-k-pag').textContent = fmtBRL(soma(pag));
    $('cal-k-pag-sub').textContent = `${pag.length} em aberto${pagQ.length ? ` · ${fmtBRL(soma(pagQ))} já pago` : ''}`;
    $('cal-k-venc').textContent = String(venc.length);
    $('cal-k-venc').className = venc.length ? 'neg' : '';
    $('cal-k-venc-sub').textContent = venc.length ? fmtBRL(soma(venc)) + ' em atraso' : 'nada vencido no mês';
    const saldo = soma(rec) - soma(pag);
    $('cal-k-saldo').textContent = fmtBRLs(saldo);
    $('cal-k-saldo').classList.toggle('neg-on-dark', saldo < 0);
  }

  function renderGrade() {
    const { ano: a, mes: m } = cal;
    const lista = filtrados();
    const primeiro = new Date(a, m - 1, 1).getDay(), dias = new Date(a, m, 0).getDate(), hoje = hojeISO();
    let html = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map(d => `<div class="dow">${d}</div>`).join('');
    for (let i = 0; i < primeiro; i++) html += '<button type="button" class="out" tabindex="-1" aria-hidden="true"></button>';
    for (let d = 1; d <= dias; d++) {
      const iso = `${a}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const its = lista.filter(x => x.venc === iso);
      const abertos = its.filter(x => x.status === 'em_aberto');
      const rec = abertos.filter(x => x.tipo === 'income').reduce((s, x) => s + x.valor, 0);
      const pag = abertos.filter(x => x.tipo === 'expense').reduce((s, x) => s + x.valor, 0);
      const quitados = its.length - abertos.length;
      const fds = [0, 6].includes(new Date(a, m - 1, d).getDay());
      const cls = [iso === hoje ? 'today' : '', iso === cal.dia ? 'sel' : '', its.some(x => x.vencido) ? 'late' : '', rec ? 'has-in' : '', pag ? 'has-out' : '', fds ? 'fds' : ''].join(' ');
      const tags = (rec ? `<span class="tag in">+${fmtBRLk(rec)}</span>` : '') + (pag ? `<span class="tag out">−${fmtBRLk(pag)}</span>` : '')
        + (quitados ? `<span class="tag done">✓ ${quitados}</span>` : '');
      const titulo = its.length ? `${its.length} título(s)` + (rec ? ` · a receber ${fmtBRL(rec)}` : '') + (pag ? ` · a pagar ${fmtBRL(pag)}` : '') : 'Sem vencimentos';
      html += `<button type="button" class="${cls}" data-dia="${iso}" title="${esc(titulo)}"><span class="d">${d}</span>${tags}</button>`;
    }
    $('cal').innerHTML = html;
    $('cal').querySelectorAll('button[data-dia]').forEach(b => {
      b.onclick = () => { cal.dia = cal.dia === b.dataset.dia ? null : b.dataset.dia; renderGrade(); renderLista(); };
      if (ed) b.ondblclick = () => novaConta(b.dataset.dia);
    });
  }

  function renderLista() {
    const todos = filtrados();
    const lista = cal.dia ? todos.filter(x => x.venc === cal.dia) : todos;
    if (cal.dia) { const [, mm, dd] = cal.dia.split('-'); $('agenda-dia-titulo').textContent = `${Number(dd)} de ${MESES[Number(mm) - 1].toLowerCase()}`; }
    else $('agenda-dia-titulo').textContent = `${MESES[cal.mes - 1]} — mês inteiro`;
    $('agenda-foot').classList.toggle('hidden', !cal.dia);
    const ab = lista.filter(x => x.status === 'em_aberto');
    const rec = ab.filter(x => x.tipo === 'income').reduce((s, x) => s + x.valor, 0), pag = ab.filter(x => x.tipo === 'expense').reduce((s, x) => s + x.valor, 0);
    $('agenda-dia-tot').innerHTML = lista.length ? `<span class="pos">+${esc(fmtBRL(rec))}</span> · <span class="neg">−${esc(fmtBRL(pag))}</span>` : '';
    if (!lista.length) {
      $('agenda-itens').innerHTML = `<div class="ag-empty">${cal.dia ? 'Nenhuma conta neste dia.' : 'Nenhuma conta a pagar ou a receber neste mês.'}${ed ? '<br/><small>Dica: dê dois cliques num dia para lançar uma conta.</small>' : ''}</div>`;
      return;
    }
    let html = '', diaAnt = '';
    lista.forEach(x => {
      if (!cal.dia && x.venc !== diaAnt) { diaAnt = x.venc; const dt = new Date(x.venc + 'T12:00'); html += `<div class="ag-day">${dt.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })}</div>`; }
      const st = x.status === 'quitado' ? '<span class="st on">Quitado</span>' : x.vencido ? '<span class="st late">Vencido</span>' : '<span class="st open">Em aberto</span>';
      const quem = [x.cliente_fornecedor, x.categoria_nome].filter(Boolean).join(' · ') || (x.tipo === 'income' ? 'A receber' : 'A pagar');
      const btn = ed && x.status === 'em_aberto' ? `<button type="button" class="btn btn-ghost xs ag-baixa" data-id="${x.id}" title="Dar baixa hoje">✓ Baixa</button>` : '';
      html += `<div class="ag-item ${x.status === 'quitado' ? 'done' : ''}" data-id="${x.id}" tabindex="0">
        <span class="bar-s ${x.tipo === 'income' ? 'in' : 'out'}"></span>
        <div class="tx"><b>${esc(x.descricao) || '<span class="dim">Sem descrição</span>'}</b><small>${esc(quem)}</small></div>
        <div class="vl ${x.tipo === 'income' ? 'pos' : 'neg'}">${x.tipo === 'income' ? '+' : '−'} ${fmtBRL(x.valor)}<div>${st}</div></div>${btn}</div>`;
    });
    $('agenda-itens').innerHTML = html;
    const porId = id => cal.itens.find(x => x.id === Number(id));
    $('agenda-itens').querySelectorAll('.ag-item').forEach(el => {
      const abre = () => verTitulo(porId(el.dataset.id));
      el.onclick = e => { if (!e.target.closest('.ag-baixa')) abre(); };
      el.onkeydown = e => { if (e.key === 'Enter') abre(); };
    });
    $('agenda-itens').querySelectorAll('.ag-baixa').forEach(b => b.onclick = () => LancForm.baixar(porId(b.dataset.id), true, carregar));
  }

  function verTitulo(x) {
    const botoes = [];
    if (ed && x.status === 'em_aberto') botoes.push({ label: 'Dar baixa', cls: 'btn-gold', fn: () => { fecharModal('modal-view'); LancForm.baixar(x, true, carregar); } });
    if (ed && x.status === 'quitado') botoes.push({ label: 'Reabrir', cls: 'btn-ghost', fn: () => { fecharModal('modal-view'); LancForm.baixar(x, false, carregar); } });
    if (ed) botoes.push({ label: 'Editar', fn: () => { fecharModal('modal-view'); LancForm.abrir(x, {}, carregar); } });
    mostrarDetalhes(x.descricao || 'Título', `${x.tipo === 'income' ? 'Conta a receber' : 'Conta a pagar'} · ${fmtBRL(x.valor)}`, [
      ['Vencimento', fmtData(x.venc)], ['Situação', x.status === 'quitado' ? `Quitado em ${fmtData(x.data_pagamento)}` : x.vencido ? 'Vencido' : 'Em aberto'],
      [x.tipo === 'income' ? 'Cliente' : 'Fornecedor', x.cliente_fornecedor], ['Categoria', x.categoria_nome],
      ['Forma de pagamento', x.forma_pagamento_nome], ['Caixa / Banco', x.caixa_nome], ['Data do lançamento', fmtData(x.data)],
      ['Observações', x.observacoes],
    ], botoes);
  }

  function novaConta(dia) {
    const d = dia || cal.dia || (cal.ano === hoje0.getFullYear() && cal.mes === hoje0.getMonth() + 1 ? hojeISO() : `${cal.ano}-${String(cal.mes).padStart(2, '0')}-01`);
    LancForm.abrir(null, { titulo: 'Nova conta', tipo: fv('cal-f-tipo') || 'expense', status: 'em_aberto', data: d, data_vencimento: d }, carregar);
  }

  function mudarMes(delta) {
    let m = cal.mes + delta, a = cal.ano;
    if (m < 1) { m = 12; a--; } if (m > 12) { m = 1; a++; }
    cal.mes = m; cal.ano = a; cal.dia = null; carregar();
  }
  $('cal-prev').addEventListener('click', () => mudarMes(-1));
  $('cal-next').addEventListener('click', () => mudarMes(1));
  $('cal-hoje').addEventListener('click', () => { cal.ano = hoje0.getFullYear(); cal.mes = hoje0.getMonth() + 1; cal.dia = hojeISO(); carregar(); });
  ['cal-f-tipo', 'cal-f-sit'].forEach(id => $(id).addEventListener('change', () => { renderGrade(); renderLista(); }));
  $('agenda-ver-mes').addEventListener('click', () => { cal.dia = null; renderGrade(); renderLista(); });
  if ($('cal-novo')) $('cal-novo').addEventListener('click', () => novaConta());
  if ($('agenda-novo-dia')) $('agenda-novo-dia').addEventListener('click', () => novaConta(cal.dia));
  document.addEventListener('keydown', e => {
    if (e.target.closest('input,select,textarea') || document.querySelector('.modal:not(.hidden)')) return;
    if (e.key === 'ArrowLeft' && e.altKey) mudarMes(-1);
    if (e.key === 'ArrowRight' && e.altKey) mudarMes(1);
  });

  if (ed) carregarCadastros();
  carregar();
})();
