/* Caixas / Banco */
(function () {
  const cx = { lista: [], editId: null, sem: { saldo: 0, qtd: 0 } };
  const ed = podeEditar('cad-caixas');
  const TIPO = { banco: 'Conta bancária', caixa: 'Caixa físico', investimento: 'Investimento' };
  $('lista-bancos').innerHTML = BANCOS.map(b => `<option value="${esc(b)}">`).join('');

  async function carregar() {
    const r = await api('caixas');
    cx.lista = r && Array.isArray(r.caixas) ? r.caixas : [];
    cx.sem = r && r.sem_caixa ? r.sem_caixa : { saldo: 0, qtd: 0 };
    if (r.error) toast(r.error, 'err');
    render();
  }
  function render() {
    const soma = (f, k) => cx.lista.filter(f).reduce((s, x) => s + x[k], 0);
    const tot = soma(() => true, 'saldo_atual');
    $('cx-total').textContent = fmtBRLs(tot); $('cx-total').classList.toggle('neg-on-dark', tot < 0);
    $('cx-bancos').textContent = fmtBRLs(soma(x => x.tipo !== 'caixa', 'saldo_atual'));
    $('cx-fisico').textContent = fmtBRLs(soma(x => x.tipo === 'caixa', 'saldo_atual'));
    $('cx-previsto').textContent = fmtBRLs(tot + soma(() => true, 'a_receber') - soma(() => true, 'a_pagar'));
    document.querySelector('#cx-table tbody').innerHTML = cx.lista.length ? cx.lista.map(x => {
      const menu = acoes('cx' + x.id, [
        ed && { label: 'Editar', fn: () => abrir(x) },
        ed && { label: 'Apagar', danger: true, fn: () => apagar(x) },
      ]);
      return `<tr>
        <td><div class="who">${avatar(x.nome)}<div><span class="ttl">${esc(x.nome)}</span><small><span class="tp ${esc(x.tipo)}" style="padding:0 7px;font-size:11.5px">${TIPO[x.tipo] || esc(x.tipo)}</span></small></div></div></td>
        <td>${esc(x.banco) || '<span class="dim">—</span>'}</td>
        <td class="num">${esc(x.agencia) || '<span class="dim">—</span>'}</td>
        <td class="num">${esc(x.conta) || '<span class="dim">—</span>'}</td>
        <td class="money r" style="font-weight:600">${fmtBRLs(x.saldo_inicial)}</td>
        <td class="money r ${x.saldo_atual < 0 ? 'neg' : ''}">${fmtBRLs(x.saldo_atual)}</td>
        <td class="money r">${x.a_receber ? `<span class="pos">${fmtBRL(x.a_receber)}</span>` : '<span class="dim">—</span>'}</td>
        <td class="money r">${x.a_pagar ? `<span class="neg">${fmtBRL(x.a_pagar)}</span>` : '<span class="dim">—</span>'}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="9" class="empty"><strong>Nenhuma conta ou caixa</strong>Cadastre as contas bancárias e caixas da empresa.</td></tr>';
    $('cx-aviso').innerHTML = cx.sem.qtd ? `⚠ ${cx.sem.qtd} lançamento(s) estão sem caixa/banco definido (${fmtBRLs(cx.sem.saldo)} já quitados). Eles aparecem no Fluxo de Caixa “Todos os caixas”, mas não no saldo de nenhuma conta — edite esses lançamentos e escolha o caixa.` : '';
  }
  function atualizarTipo() { $('cx-banco-wrap').classList.toggle('hidden', segValor('cx-tipo-seg') === 'caixa'); }
  $('cx-tipo-seg').addEventListener('change', atualizarTipo);
  function abrir(x = null) {
    cx.editId = x ? x.id : null;
    $('cx-modal-title').textContent = x ? 'Editar conta / caixa' : 'Nova conta / caixa';
    $('cx-error').textContent = '';
    segSet('cx-tipo-seg', x ? x.tipo : 'banco');
    $('cx-nome').value = x ? x.nome : ''; $('cx-banco').value = x ? x.banco || '' : '';
    $('cx-agencia').value = x ? x.agencia || '' : ''; $('cx-conta').value = x ? x.conta || '' : '';
    $('cx-saldo').value = x ? x.saldo_inicial : ''; $('cx-obs').value = x ? x.observacoes || '' : '';
    atualizarTipo();
    abrirModal('modal-cx');
  }
  if ($('btn-novo-caixa')) $('btn-novo-caixa').addEventListener('click', () => abrir());
  $('cx-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-cx').click(); });
  $('btn-salvar-cx').addEventListener('click', async () => {
    const body = { tipo: segValor('cx-tipo-seg'), nome: $('cx-nome').value.trim(), banco: $('cx-banco').value.trim(), agencia: $('cx-agencia').value.trim(),
      conta: $('cx-conta').value.trim(), saldo_inicial: parseFloat($('cx-saldo').value) || 0, observacoes: $('cx-obs').value.trim() };
    if (!body.nome) { $('cx-error').textContent = 'Informe o nome da conta ou caixa.'; return; }
    const r = cx.editId ? await api('caixas/' + cx.editId, 'PUT', body) : await api('caixas', 'POST', body);
    if (r.error) { $('cx-error').textContent = r.error; return; }
    fecharModal('modal-cx'); toast(cx.editId ? 'Conta atualizada' : 'Conta cadastrada');
    carregar();
  });
  async function apagar(x) {
    if (x.qtd_lanc) { toast(`"${x.nome}" tem lançamentos. Renomeie em vez de apagar.`, 'err'); return; }
    if (!await confirmar(`Apagar "${x.nome}"?`, { ok: 'Apagar' })) return;
    const r = await api('caixas/' + x.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Conta apagada'); carregar();
  }

  carregar();
})();
