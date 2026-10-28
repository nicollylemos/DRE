/* Modal de lançamento — compartilhado por Lançamentos e Calendário.
   Uso: LancForm.abrir(lancamento | null, { padrao }, aoSalvar) */
const LancForm = (function () {
  const CENTROS_RECEITA = ['RECEITA VENDAS', 'RECEITA OPERACIONAL', 'RECEITA COMUM'];
  let editId = null, aoSalvar = null, notaId = null, pre = {};

  /** x = lançamento sendo editado; sem x usa os valores pré-preenchidos (pre) ou o que já está na tela. */
  function preencherSelects(x) {
    const atual = (campo, id) => x ? x[campo] : (pre[campo] !== undefined && pre[campo] !== null ? pre[campo] : $(id).value);
    const tipo = segValor('tx-tipo-seg');
    const c = state.cad;
    // categorias do tipo (ativas) + a atual, mesmo se desativada
    const cats = (c.categorias || []).filter(k => k.tipo === 'ambos' || k.tipo === tipo).map(k => ({ v: String(k.id), l: k.nome }));
    const catAtual = x && x.categoria_id ? String(x.categoria_id) : $('tx-categoria').value;
    if (x && x.categoria_id && !cats.some(k => k.v === catAtual)) cats.push({ v: catAtual, l: x.categoria_nome + ' (desativada)' });
    $('tx-categoria').innerHTML = opts(cats, cats.some(k => k.v === catAtual) ? catAtual : '', 'Sem categoria');

    const pessoas = tipo === 'expense' ? (c.fornecedores || []) : [...new Set([...(c.clientes || []), ...(c.fornecedores || [])])].sort();
    $('tx-pessoa-label').textContent = tipo === 'expense' ? 'Fornecedor' : 'Cliente';
    $('tx-pessoa').innerHTML = opts(pessoas, atual('cliente_fornecedor', 'tx-pessoa'));
    $('tx-forma').innerHTML = opts(c.formasPagamento || [], x ? x.forma_pagamento_nome : atual('forma_pagamento', 'tx-forma'));
    $('tx-caixa').innerHTML = opts(c.caixasBancos || [], x ? x.caixa_nome : $('tx-caixa').value);
    const centros = (c.centrosCusto || []).filter(n => tipo === 'income' ? CENTROS_RECEITA.includes(n) : !CENTROS_RECEITA.includes(n));
    $('tx-centro').innerHTML = opts(centros, x ? x.centro_custo_nome : $('tx-centro').value);
    const contas = (tipo === 'income' ? c.contasReceita : c.contasDespesa) || [];
    const contaOpts = contas.map(k => ({ v: `${k.grupo}||${k.conta || ''}`, l: k.conta ? `${k.grupo} — ${k.conta}` : k.grupo }));
    const contaAtual = x ? (x.grupo ? `${x.grupo}||${x.conta_nome || ''}` : '') : $('tx-conta').value;
    $('tx-conta').innerHTML = opts(contaOpts, contaAtual);
    calcTaxa();
  }
  function calcTaxa() {
    const valor = parseFloat($('tx-valor').value) || 0;
    const fp = (state.cad.formasFull || []).find(f => f.nome === $('tx-forma').value);
    if (segValor('tx-tipo-seg') !== 'income' || !fp || !valor || (!fp.taxa_intermediacao && !fp.tarifa_fixa)) { $('tx-taxa').classList.add('hidden'); return; }
    const taxa = fp.taxa_intermediacao / 100 * valor + fp.tarifa_fixa;
    $('tx-taxa-v').textContent = fmtBRL(taxa); $('tx-liq-v').textContent = fmtBRL(valor - taxa);
    $('tx-taxa').classList.remove('hidden');
  }
  function situacaoMudou() {
    const aberto = $('tx-status').value === 'em_aberto';
    if (!aberto && !$('tx-pag').value) $('tx-pag').value = $('tx-data').value || hojeISO();
    if (aberto) $('tx-pag').value = '';
    $('tx-pag').disabled = aberto;
  }

  $('tx-tipo-seg').addEventListener('change', () => { pre = {}; preencherSelects(null); });
  $('tx-forma').addEventListener('change', calcTaxa);
  $('tx-valor').addEventListener('input', calcTaxa);
  $('tx-status').addEventListener('change', situacaoMudou);
  $('tx-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-tx').click(); });

  /** p = valores iniciais pra um lançamento novo: { tipo, data, status, data_vencimento, categoria_id, titulo,
   *  valor, descricao, cliente_fornecedor, forma_pagamento, observacoes, nota_fiscal_id } */
  async function abrir(x = null, p = {}, cb = null) {
    if (!state.cad.categorias) await carregarCadastros();
    editId = x ? x.id : null; aoSalvar = cb;
    notaId = !x && p.nota_fiscal_id ? p.nota_fiscal_id : null;
    pre = x ? {} : p;
    $('tx-modal-title').textContent = x ? 'Editar lançamento' : (p.titulo || 'Novo lançamento');
    $('tx-error').textContent = '';
    ['tx-categoria', 'tx-pessoa', 'tx-forma', 'tx-caixa', 'tx-centro', 'tx-conta'].forEach(id => $(id).innerHTML = '');
    segSet('tx-tipo-seg', x ? x.tipo : (p.tipo || 'expense'));
    $('tx-data').value = x ? x.data : (p.data || hojeISO());
    $('tx-valor').value = x ? x.valor : (p.valor || '');
    $('tx-descricao').value = x ? (x.descricao || '') : (p.descricao || '');
    $('tx-status').value = x ? x.status : (p.status || 'quitado');
    $('tx-venc').value = x ? (x.data_vencimento || '') : (p.data_vencimento || '');
    $('tx-pag').value = x ? (x.data_pagamento || '') : '';
    $('tx-obs').value = x ? (x.observacoes || '') : (p.observacoes || '');
    preencherSelects(x);
    if (!x && p.categoria_id) $('tx-categoria').value = String(p.categoria_id);
    situacaoMudou();
    abrirModal('modal-tx');
  }

  $('btn-salvar-tx').addEventListener('click', async () => {
    const [grupo, conta] = ($('tx-conta').value || '||').split('||');
    const body = {
      type: segValor('tx-tipo-seg'), date: $('tx-data').value, amount: parseFloat($('tx-valor').value),
      categoria_id: $('tx-categoria').value ? Number($('tx-categoria').value) : null,
      description: $('tx-descricao').value.trim(), cliente_fornecedor: $('tx-pessoa').value,
      forma_pagamento: $('tx-forma').value, caixa_banco: $('tx-caixa').value, centro_custo: $('tx-centro').value,
      grupo_contas: grupo || '', conta: conta || '',
      status: $('tx-status').value, data_vencimento: $('tx-venc').value || null, data_pagamento: $('tx-pag').value || null,
      observacoes: $('tx-obs').value.trim(),
    };
    if (notaId) body.nota_fiscal_id = notaId;
    if (!body.date || !(body.amount > 0)) { $('tx-error').textContent = 'Informe a data e um valor maior que zero.'; return; }
    const btn = $('btn-salvar-tx'); carregando(btn, true);
    const r = editId ? await api('transactions/' + editId, 'PUT', body) : await api('transactions', 'POST', body);
    carregando(btn, false);
    if (r.error) { $('tx-error').textContent = r.error; return; }
    fecharModal('modal-tx');
    toast(editId ? 'Lançamento atualizado' : 'Lançamento criado');
    if (aoSalvar) aoSalvar(r);
  });

  async function baixar(x, quitar, cb) {
    const r = await api('transactions/' + x.id, 'PATCH', quitar ? { status: 'quitado', data_pagamento: hojeISO() } : { status: 'em_aberto', data_pagamento: null });
    if (r.error) return toast(r.error, 'err');
    toast(quitar ? 'Baixa registrada' : 'Lançamento reaberto');
    if (cb) cb();
  }
  async function apagar(x, cb) {
    if (!await confirmar(`Apagar o lançamento "${x.descricao || fmtBRL(x.valor)}" de ${fmtData(x.data)}?`, { ok: 'Apagar' })) return;
    const r = await api('transactions/' + x.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Lançamento apagado'); if (cb) cb();
  }
  function ver(x, cb) {
    const linhas = [
      ['Tipo', x.tipo === 'income' ? 'Receita' : 'Despesa'], ['Data', fmtData(x.data)], ['Valor', fmtBRL(x.valor)],
      x.taxa_mdr ? ['Taxa / Valor líquido', `${fmtBRL(x.taxa_mdr)} · ${fmtBRL(x.valor_liquido)}`] : null,
      ['Categoria', x.categoria_nome], ['Descrição', x.descricao], [x.tipo === 'income' ? 'Cliente' : 'Fornecedor', x.cliente_fornecedor],
      ['Forma de pagamento', x.forma_pagamento_nome], ['Caixa / Banco', x.caixa_nome], ['Centro de custo', x.centro_custo_nome],
      ['Grupo / Conta', [x.grupo, x.conta_nome].filter(Boolean).join(' — ')],
      ['Situação', x.status === 'quitado' ? 'Quitado' : 'Em aberto'], ['Vencimento', fmtData(x.data_vencimento)], ['Pagamento', fmtData(x.data_pagamento)],
      ['Observações', x.observacoes], x.importacao_id ? ['Origem', `Importação #${x.importacao_id}`] : null,
      x.nf_id && pode('notas-fiscais') ? ['Nota fiscal', `<a class="link" href="${esc(url('notas-fiscais?ver=' + x.nf_id))}">Abrir nota${x.nf_qtd > 1 ? ` (${x.nf_qtd} notas)` : ''}</a>`, true] : null,
    ];
    const botoes = [];
    if (podeEditar('lancamentos')) {
      if (x.status === 'em_aberto') botoes.push({ label: 'Dar baixa', cls: 'btn-gold', fn: () => { fecharModal('modal-view'); baixar(x, true, cb); } });
      botoes.push({ label: 'Editar', fn: () => { fecharModal('modal-view'); abrir(x, {}, cb); } });
    }
    mostrarDetalhes(x.descricao || 'Lançamento', `${x.tipo === 'income' ? 'Receita' : 'Despesa'} · ${fmtBRL(x.valor)}`, linhas, botoes);
  }

  return { abrir, baixar, apagar, ver };
})();
