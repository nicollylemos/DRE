/* Modal de lançamento — compartilhado por Lançamentos e Calendário.
   Uso: LancForm.abrir(lancamento | null, { padrao }, aoSalvar) */
const LancForm = (function () {
  const CENTROS_RECEITA = ['RECEITA VENDAS', 'RECEITA OPERACIONAL', 'RECEITA COMUM'];
  let editId = null, aoSalvar = null, notaId = null, pre = {}, atual = null, removerComp = false;
  const compUrl = (id, baixar) => url(`api/transactions/${id}/comprovante${baixar ? '?download=1' : ''}`);

  // ─── Comprovante no formulário ───
  function mostrarComprovante() {
    const novo = $('tx-comp-file').files[0], tem = atual && atual.tem_comprovante && !removerComp;
    $('tx-comp-nome').innerHTML = novo ? `<b>${esc(novo.name)}</b> <span class="dim">· será anexado ao salvar</span>`
      : tem ? `<a class="link" href="${compUrl(atual.id)}" target="_blank" rel="noopener">${esc(atual.comprovante_nome || 'comprovante')}</a>`
      : removerComp ? '<span class="dim">será removido ao salvar</span>' : '<span class="dim">nenhum arquivo</span>';
    $('tx-comp-btn').textContent = tem || novo ? 'Trocar arquivo' : 'Anexar comprovante';
    $('tx-comp-remover').classList.toggle('hidden', !(tem || novo));
  }
  $('tx-comp-file').addEventListener('change', () => { removerComp = false; mostrarComprovante(); });
  $('tx-comp-remover').addEventListener('click', () => {
    if ($('tx-comp-file').files[0]) $('tx-comp-file').value = ''; else removerComp = true;
    mostrarComprovante();
  });
  async function enviarComprovante(id, arquivo) {
    const fd = new FormData(); fd.append('comprovante', arquivo);
    return api(`transactions/${id}/comprovante`, 'POST', fd);
  }

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
    // Data obrigatória conforme a situação: em aberto → vencimento; quitado → data de pagamento.
    $('tx-venc-obr').classList.toggle('hidden', !aberto);
    $('tx-pag-obr').classList.toggle('hidden', aberto);
    ['tx-venc', 'tx-pag'].forEach(id => $(id).classList.remove('campo-erro'));
  }
  function erroCampo(id, msg) {
    $('tx-error').textContent = msg;
    const el = $(id); el.classList.add('campo-erro', 'shake'); el.focus();
    setTimeout(() => el.classList.remove('shake'), 400);
  }
  ['tx-data', 'tx-valor', 'tx-venc', 'tx-pag'].forEach(id => $(id).addEventListener('input', () => $(id).classList.remove('campo-erro')));

  $('tx-tipo-seg').addEventListener('change', () => { pre = {}; preencherSelects(null); });
  $('tx-forma').addEventListener('change', calcTaxa);
  $('tx-valor').addEventListener('input', calcTaxa);
  $('tx-status').addEventListener('change', situacaoMudou);
  $('tx-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-tx').click(); });
  $('btn-apagar-tx').addEventListener('click', () => { if (!atual) return; const x = atual, cb = aoSalvar; fecharModal('modal-tx'); apagar(x, cb, () => abrir(x, {}, cb)); });

  /** p = valores iniciais pra um lançamento novo: { tipo, data, status, data_vencimento, categoria_id, titulo,
   *  valor, descricao, cliente_fornecedor, forma_pagamento, observacoes, nota_fiscal_id } */
  async function abrir(x = null, p = {}, cb = null) {
    if (!state.cad.categorias) await carregarCadastros();
    editId = x ? x.id : null; aoSalvar = cb; atual = x; removerComp = false;
    $('tx-comp-file').value = ''; mostrarComprovante();
    $('btn-apagar-tx').classList.toggle('hidden', !x);
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
    if (!body.date) return erroCampo('tx-data', 'Informe a data do lançamento.');
    if (!(body.amount > 0)) return erroCampo('tx-valor', 'Informe um valor maior que zero.');
    if (body.status === 'em_aberto' && !body.data_vencimento) return erroCampo('tx-venc', 'Conta em aberto precisa da data de vencimento.');
    if (body.status === 'quitado' && !body.data_pagamento) return erroCampo('tx-pag', 'Lançamento quitado precisa da data de pagamento.');
    $('tx-error').textContent = '';
    const btn = $('btn-salvar-tx'); carregando(btn, true);
    const r = editId ? await api('transactions/' + editId, 'PUT', body) : await api('transactions', 'POST', body);
    if (r.error) { carregando(btn, false); $('tx-error').textContent = r.error; return; }
    const id = editId || r.id, arq = $('tx-comp-file').files[0];
    let aviso = '';
    if (arq) { const c = await enviarComprovante(id, arq); if (c.error) aviso = 'Lançamento salvo, mas o comprovante não foi anexado: ' + c.error; }
    else if (removerComp && editId) await api(`transactions/${id}/comprovante`, 'DELETE');
    carregando(btn, false);
    fecharModal('modal-tx');
    if (aviso) toast(aviso, 'err'); else toast(editId ? 'Lançamento atualizado' : 'Lançamento criado');
    if (aoSalvar) aoSalvar(r);
  });

  async function baixar(x, quitar, cb) {
    const r = await api('transactions/' + x.id, 'PATCH', quitar ? { status: 'quitado', data_pagamento: hojeISO() } : { status: 'em_aberto', data_pagamento: null });
    if (r.error) return toast(r.error, 'err');
    toast(quitar ? 'Baixa registrada' : 'Lançamento reaberto');
    if (cb) cb();
  }
  async function apagar(x, cb, aoCancelar) {
    const oque = x.tipo === 'income' ? 'a receita' : 'a despesa';
    const extra = [x.tem_comprovante ? 'o comprovante anexado também será apagado' : '', x.nf_id ? 'a nota fiscal continua guardada, só perde o vínculo' : ''].filter(Boolean).join('; ');
    if (!await confirmar(`Apagar ${oque} "${x.descricao || fmtBRL(x.valor)}" (${fmtBRL(x.valor)}, ${fmtData(x.data)})? Use isso para lançamentos feitos por engano — para registrar que foi pago, use "Dar baixa".${extra ? '\n\nObs.: ' + extra + '.' : ''}\n\nUma cópia fica guardada nos Logs.`, { titulo: `Apagar ${oque}?`, ok: 'Apagar' })) { if (aoCancelar) aoCancelar(); return; }
    const r = await api('transactions/' + x.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Lançamento apagado'); if (cb) cb();
  }
  /** Anexar/trocar comprovante direto (sem abrir o formulário). */
  function anexarComprovante(x, cb) {
    const inp = $('tx-comp-picker');
    inp.value = '';
    inp.onchange = async () => {
      const f = inp.files[0]; if (!f) return;
      const r = await enviarComprovante(x.id, f);
      if (r.error) return toast(r.error, 'err');
      toast(x.tem_comprovante ? 'Comprovante substituído' : 'Comprovante anexado'); if (cb) cb();
    };
    inp.click();
  }
  async function removerComprovante(x, cb) {
    if (!await confirmar(`Remover o comprovante "${x.comprovante_nome || ''}" deste lançamento?`, { ok: 'Remover' })) return;
    const r = await api(`transactions/${x.id}/comprovante`, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Comprovante removido'); if (cb) cb();
  }
  /** Botões da janela de detalhes — iguais em Lançamentos e no Calendário. */
  function botoes(x, cb) {
    const b = [];
    if (!podeEditar('lancamentos')) return b;
    b.push({ label: 'Apagar', cls: 'btn-ghost danger', fn: () => { fecharModal('modal-view'); apagar(x, cb); } });
    b.push({ label: x.tem_comprovante ? 'Trocar comprovante' : '+ Comprovante', title: x.tem_comprovante ? 'Substituir o arquivo anexado' : 'Anexar comprovante (PDF ou foto)', cls: 'btn-ghost', fn: () => { fecharModal('modal-view'); anexarComprovante(x, cb); } });
    if (x.status === 'em_aberto') b.push({ label: 'Dar baixa', cls: 'btn-gold', fn: () => { fecharModal('modal-view'); baixar(x, true, cb); } });
    else b.push({ label: 'Reabrir', cls: 'btn-ghost', fn: () => { fecharModal('modal-view'); baixar(x, false, cb); } });
    b.push({ label: 'Editar', fn: () => { fecharModal('modal-view'); abrir(x, {}, cb); } });
    return b;
  }
  const linhaComprovante = x => ['Comprovante', x.tem_comprovante
    ? `<a class="link" href="${compUrl(x.id)}" target="_blank" rel="noopener">${esc(x.comprovante_nome || 'Abrir')}</a> · <a class="link" href="${compUrl(x.id, true)}">baixar</a>`
    : '<span class="dim">nenhum anexado</span>', true];

  function ver(x, cb) {
    const linhas = [
      ['Tipo', x.tipo === 'income' ? 'Receita' : 'Despesa'], ['Data', fmtData(x.data)], ['Valor', fmtBRL(x.valor)],
      x.taxa_mdr ? ['Taxa / Valor líquido', `${fmtBRL(x.taxa_mdr)} · ${fmtBRL(x.valor_liquido)}`] : null,
      ['Categoria', x.categoria_nome], ['Descrição', x.descricao], [x.tipo === 'income' ? 'Cliente' : 'Fornecedor', x.cliente_fornecedor],
      ['Forma de pagamento', x.forma_pagamento_nome], ['Caixa / Banco', x.caixa_nome], ['Centro de custo', x.centro_custo_nome],
      ['Grupo / Conta', [x.grupo, x.conta_nome].filter(Boolean).join(' — ')],
      ['Situação', x.status === 'quitado' ? 'Quitado' : 'Em aberto'], ['Vencimento', fmtData(x.data_vencimento)], ['Pagamento', fmtData(x.data_pagamento)],
      ['Observações', x.observacoes], x.importacao_id ? ['Origem', `Importação #${x.importacao_id}`] : null,
      linhaComprovante(x),
      x.nf_id && pode('notas-fiscais') ? ['Nota fiscal', `<a class="link" href="${esc(url('notas-fiscais?ver=' + x.nf_id))}">Abrir nota${x.nf_qtd > 1 ? ` (${x.nf_qtd} notas)` : ''}</a>`, true] : null,
    ];
    mostrarDetalhes(x.descricao || 'Lançamento', `${x.tipo === 'income' ? 'Receita' : 'Despesa'} · ${fmtBRL(x.valor)}`, linhas, botoes(x, cb));
  }

  return { abrir, baixar, apagar, ver, anexarComprovante, removerComprovante, botoes, linhaComprovante, compUrl };
})();
