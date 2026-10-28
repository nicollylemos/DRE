/* Lançamentos: lista com filtros, KPIs e ações. */
(function () {
  const tx = { lista: [], pagina: 1 };
  const params = new URLSearchParams(location.search);
  let importacaoId = params.get('importacao') || '';
  const ed = podeEditar('lancamentos');

  const ano = new Date().getFullYear();
  $('tx-f-mes').innerHTML = '<option value="">Todos os meses</option>' + MESES.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
  $('tx-f-ano').innerHTML = '<option value="">Todos os anos</option>' + [ano + 1, ano, ano - 1, ano - 2, ano - 3].map(a => `<option value="${a}" ${a === ano ? 'selected' : ''}>${a}</option>`).join('');
  // Vindo de outra tela com filtro na URL (?busca=, ?categoria=, ?importacao=)
  if (params.get('busca')) $('tx-f-busca').value = params.get('busca');
  if (params.get('categoria') || params.get('busca') || importacaoId) $('tx-f-ano').value = '';

  async function carregar() {
    const qs = new URLSearchParams();
    [['year', 'tx-f-ano'], ['month', 'tx-f-mes'], ['type', 'tx-f-tipo'], ['status', 'tx-f-status'], ['categoria_id', 'tx-f-categoria']]
      .forEach(([k, id]) => { if (fv(id)) qs.set(k, fv(id)); });
    if (importacaoId) qs.set('importacao_id', importacaoId);
    const r = await api('transactions?' + qs);
    tx.lista = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    render();
  }
  function filtrados() {
    const t = norm($('tx-f-busca').value.trim());
    return t ? tx.lista.filter(x => [x.descricao, x.cliente_fornecedor, x.categoria_nome, x.observacoes].some(v => norm(v).includes(t))) : tx.lista;
  }
  function render() {
    const lista = filtrados();
    let rec = 0, desp = 0, aberto = 0;
    lista.forEach(x => { if (x.tipo === 'income') rec += x.valor; else desp += x.valor; if (x.status === 'em_aberto') aberto += x.valor; });
    $('kpi-rec').textContent = fmtBRL(rec); $('kpi-desp').textContent = fmtBRL(desp); $('kpi-aberto').textContent = fmtBRL(aberto);
    $('kpi-saldo').textContent = fmtBRLs(rec - desp);

    const { itens, pagina, total, n } = paginar(lista, tx.pagina); tx.pagina = pagina;
    const hoje = hojeISO();
    const cor = id => ((state.cad.categorias || []).find(c => c.id === id) || {}).cor || '#C9CAD6';
    document.querySelector('#tx-table tbody').innerHTML = itens.length ? itens.map(x => {
      const vencido = x.status === 'em_aberto' && x.data_vencimento && x.data_vencimento < hoje;
      const menu = acoes('tx' + x.id, [
        { label: 'Visualizar', fn: () => LancForm.ver(x, carregar) },
        ed && { label: 'Editar', fn: () => LancForm.abrir(x, {}, carregar) },
        ed && (x.status === 'em_aberto' ? { label: 'Dar baixa', fn: () => LancForm.baixar(x, true, carregar) } : { label: 'Reabrir', fn: () => LancForm.baixar(x, false, carregar) }),
        x.nf_id && pode('notas-fiscais') ? { label: 'Nota fiscal', fn: () => { location.href = url('notas-fiscais?ver=' + x.nf_id); } }
          : (!x.nf_id && podeEditar('notas-fiscais') ? { label: 'Anexar nota fiscal', fn: () => { location.href = url(`notas-fiscais?novo=1&lancamento=${x.id}&tipo=${x.tipo === 'income' ? 'saida' : 'entrada'}&valor=${x.valor}`); } } : null),
        ed && { label: 'Apagar', danger: true, fn: () => LancForm.apagar(x, carregar) },
      ]);
      return `<tr>
        <td class="nowrap num">${fmtData(x.data)}</td>
        <td class="wrap"><span class="ttl">${esc(x.descricao) || '<span class="dim">Sem descrição</span>'}</span>${x.nf_id ? ` <a class="fbadge nf" href="${esc(url('notas-fiscais?ver=' + x.nf_id))}" title="${x.nf_qtd > 1 ? x.nf_qtd + ' notas fiscais vinculadas' : 'Nota fiscal vinculada'}">NF</a>` : ''}</td>
        <td class="nowrap">${x.categoria_nome ? `<span class="catg"><i style="background:${esc(cor(x.categoria_id))}"></i>${esc(x.categoria_nome)}</span>` : '<span class="dim">—</span>'}</td>
        <td class="wrap">${esc(x.cliente_fornecedor) || '<span class="dim">—</span>'}</td>
        <td class="money r ${x.tipo === 'income' ? 'pos' : 'neg'}">${x.tipo === 'income' ? '+ ' : '− '}${fmtBRL(x.valor)}</td>
        <td class="nowrap num ${vencido ? 'neg' : ''}">${x.data_vencimento ? fmtData(x.data_vencimento) : '<span class="dim">—</span>'}</td>
        <td>${x.status === 'quitado' ? '<span class="st on">Quitado</span>' : vencido ? '<span class="st late">Vencido</span>' : '<span class="st open">Em aberto</span>'}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="8" class="empty"><strong>Nenhum lançamento aqui</strong>${ed ? 'Ajuste os filtros ou registre um novo lançamento.' : 'Ajuste os filtros.'}</td></tr>`;
    renderPager('tx-pager', pagina, total, p => { tx.pagina = p; render(); }, n);

    const chip = $('tx-filtro-imp');
    chip.classList.toggle('hidden', !importacaoId);
    if (importacaoId) {
      chip.innerHTML = `Mostrando só os lançamentos da importação <b>#${esc(importacaoId)}</b> <button type="button" class="link" id="tx-limpa-imp">Mostrar todos</button>`;
      $('tx-limpa-imp').onclick = () => { importacaoId = ''; history.replaceState(null, '', location.pathname); $('tx-f-ano').value = String(ano); tx.pagina = 1; carregar(); };
    }
  }

  $('tx-toolbar').addEventListener('submit', e => { e.preventDefault(); tx.pagina = 1; carregar(); });
  ['tx-f-tipo', 'tx-f-status', 'tx-f-categoria', 'tx-f-mes', 'tx-f-ano'].forEach(id => $(id).addEventListener('change', () => { tx.pagina = 1; carregar(); }));
  $('tx-f-busca').addEventListener('input', () => { tx.pagina = 1; render(); });
  if ($('btn-novo-tx')) $('btn-novo-tx').addEventListener('click', () => LancForm.abrir(null, { tipo: fv('tx-f-tipo') || 'expense', categoria_id: $('tx-f-categoria').value }, carregar));

  carregarCadastros().then(c => {
    const sel = $('tx-f-categoria');
    sel.innerHTML = '<option value="">Todas as categorias</option>' + (c.categorias || []).map(k => `<option value="${k.id}">${esc(k.nome)}</option>`).join('');
    if (params.get('categoria')) sel.value = params.get('categoria');
    carregar();
  });
})();
