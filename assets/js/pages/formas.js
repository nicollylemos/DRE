/* Formas de pagamento */
(function () {
  const fp = { lista: [], editId: null };
  const ed = podeEditar('cad-formas');
  const TIPO = { credito: 'Cartão de crédito', debito: 'Cartão de débito', pix: 'PIX', boleto: 'Boleto', transferencia: 'Transferência', dinheiro: 'Dinheiro' };
  const pct = v => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + '%';
  $('lista-bancos').innerHTML = BANCOS.map(b => `<option value="${esc(b)}">`).join('');

  async function carregar() {
    const r = await api('formas_pagamento');
    fp.lista = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    render();
  }
  function render() {
    const tipo = fv('fp-f-tipo'), sit = $('fp-f-sit').value;
    const lista = fp.lista.filter(x => (!tipo || x.tipo === tipo) && (sit === '' || String(x.ativo) === sit));
    document.querySelector('#fp-table tbody').innerHTML = lista.length ? lista.map(x => {
      const menu = acoes('fp' + x.id, [
        ed && { label: 'Editar', fn: () => abrir(x) },
        ed && (x.ativo ? { label: 'Desativar', fn: () => situacao(x, false) } : { label: 'Ativar', fn: () => situacao(x, true) }),
        ed && { label: 'Apagar', danger: true, fn: () => apagar(x) },
      ]);
      return `<tr class="${x.ativo ? '' : 'row-off'}">
        <td><span class="ttl">${esc(x.nome)}</span>${x.ativo ? '' : ' <span class="st off">Inativa</span>'}${x.qtd_lanc ? `<small>${x.qtd_lanc} lançamento(s)</small>` : ''}</td>
        <td>${x.tipo ? `<span class="tp ${esc(x.tipo)}">${TIPO[x.tipo] || esc(x.tipo)}</span>` : '<span class="tp boleto">Definir tipo</span>'}</td>
        <td>${x.banco || x.agencia ? esc([x.banco, x.agencia && 'ag. ' + x.agencia].filter(Boolean).join(' · ')) : '<span class="dim">—</span>'}</td>
        <td class="r num">${x.parcelas}x</td>
        <td class="r num">${x.dias_recebimento ? `D+${x.dias_recebimento}` : 'na hora'}</td>
        <td class="r num">${x.taxa_intermediacao ? pct(x.taxa_intermediacao) : '<span class="dim">—</span>'}</td>
        <td class="r num">${x.taxa_parcelamento ? pct(x.taxa_parcelamento) : '<span class="dim">—</span>'}</td>
        <td class="money r" style="font-weight:600">${x.tarifa_fixa ? fmtBRL(x.tarifa_fixa) : '<span class="dim">—</span>'}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="9" class="empty">${fp.lista.length ? '<strong>Nenhuma forma aqui</strong>Troque os filtros.' : '<strong>Nenhuma forma de pagamento</strong>Cadastre cartões, PIX, boleto, transferência e dinheiro.'}</td></tr>`;
    $('fp-count').textContent = lista.length === 1 ? '1 forma' : `${lista.length} formas`;
  }
  $('fp-f-tipo').addEventListener('change', render);
  $('fp-f-sit').addEventListener('change', render);
  async function situacao(x, ativo) {
    if (!ativo && !await confirmar(`Inativar "${x.nome}"? Ela some da lista ao criar lançamentos, mas os ${x.qtd_lanc || 0} lançamento(s) antigos e os relatórios continuam com ela. Dá pra reativar quando quiser.`, { titulo: 'Inativar forma de pagamento', ok: 'Inativar', perigo: false })) return;
    const r = await api(`formas_pagamento/${x.id}/situacao`, 'PATCH', { ativo });
    if (r.error) return toast(r.error, 'err');
    toast(ativo ? 'Forma de pagamento reativada' : 'Forma de pagamento inativada'); carregar();
  }

  function atualizarTipo() {
    const t = segValor('fp-tipo-seg');
    document.querySelectorAll('#modal-fp .fp-credito').forEach(el => el.classList.toggle('hidden', t !== 'credito'));
    document.querySelectorAll('#modal-fp .fp-banco').forEach(el => el.classList.toggle('hidden', t === 'dinheiro'));
    $('fp-nome').placeholder = { transferencia: 'Ex: TED Itaú, Transferência Nubank', debito: 'Ex: Débito Rede, Débito Itaú', pix: 'Ex: PIX Itaú' }[t] || 'Ex: Visa Crédito 3x, PIX Itaú';
    simular();
  }
  function simular() {
    const t = segValor('fp-tipo-seg'), taxa = numBR($('fp-taxa').value), tarifa = numBR($('fp-tarifa').value);
    const dias = parseInt($('fp-dias').value, 10) || 0, parc = parseInt($('fp-parcelas').value, 10) || 1, tparc = numBR($('fp-taxa-parc').value);
    const desc = taxa / 100 * 100 + tarifa;
    let html = `Numa venda de <strong>R$ 100,00</strong> a empresa recebe <strong>${fmtBRL(100 - desc)}</strong> ${dias ? `em <strong>D+${dias}</strong>` : '<strong>na hora</strong>'}`
      + (desc ? ` (desconto de ${fmtBRL(desc)} = intermediação${tarifa ? ' + tarifa fixa' : ''}).` : '.');
    if (t === 'credito' && (parc > 1 || tparc)) html += `<br/><span class="muted">Parcelado em até ${parc}x${tparc ? ` com taxa de parcelamento de ${pct(tparc)}` : ''}. A taxa de parcelamento fica registrada como referência — o desconto automático nos lançamentos usa intermediação + tarifa fixa.</span>`;
    $('fp-simula').innerHTML = html;
  }
  $('fp-tipo-seg').addEventListener('change', atualizarTipo);
  // Limites dos campos (o servidor confere de novo)
  ligarMascara('fp-agencia', maskAgencia);
  ligarMascara('fp-taxa', v => maskDecimal(v, 3, 2, 100));
  ligarMascara('fp-taxa-parc', v => maskDecimal(v, 3, 2, 100));
  ligarMascara('fp-tarifa', v => maskDecimal(v, 4, 2, 9999.99));
  ligarMascara('fp-parcelas', v => { const d = soDigitos(v).slice(0, 2); return d && Number(d) > 48 ? '48' : d; });
  ligarMascara('fp-dias', v => { const d = soDigitos(v).slice(0, 3); return d && Number(d) > 365 ? '365' : d; });
  ['fp-taxa', 'fp-tarifa', 'fp-dias', 'fp-parcelas', 'fp-taxa-parc'].forEach(id => $(id).addEventListener('input', simular));
  ['fp-taxa', 'fp-taxa-parc', 'fp-tarifa'].forEach(id => $(id).addEventListener('blur', () => { $(id).value = fmtNumBR(numBR($(id).value)); }));
  function abrir(x = null) {
    fp.editId = x ? x.id : null;
    $('fp-modal-title').textContent = x ? 'Editar forma de pagamento' : 'Nova forma de pagamento';
    $('fp-error').textContent = '';
    segSet('fp-tipo-seg', x && x.tipo ? x.tipo : (fv('fp-f-tipo') || 'credito'));
    $('fp-nome').value = x ? x.nome : ''; $('fp-banco').value = x ? x.banco || '' : ''; $('fp-agencia').value = x ? x.agencia || '' : '';
    $('fp-parcelas').value = x ? x.parcelas : 1; $('fp-dias').value = x ? x.dias_recebimento : 0;
    $('fp-taxa').value = x ? fmtNumBR(x.taxa_intermediacao) : ''; $('fp-taxa-parc').value = x ? fmtNumBR(x.taxa_parcelamento) : '';
    $('fp-tarifa').value = x ? fmtNumBR(x.tarifa_fixa) : '';
    atualizarTipo();
    abrirModal('modal-fp');
  }
  if ($('btn-nova-forma')) $('btn-nova-forma').addEventListener('click', () => abrir());
  $('fp-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-fp').click(); });
  $('btn-salvar-fp').addEventListener('click', async () => {
    const body = { tipo: segValor('fp-tipo-seg'), nome: $('fp-nome').value.trim(), banco: $('fp-banco').value.trim(), agencia: $('fp-agencia').value.trim(),
      parcelas: parseInt($('fp-parcelas').value, 10) || 1, dias_recebimento: parseInt($('fp-dias').value, 10) || 0,
      taxa_intermediacao: numBR($('fp-taxa').value), taxa_parcelamento: numBR($('fp-taxa-parc').value), tarifa_fixa: numBR($('fp-tarifa').value) };
    const parc = parseInt($('fp-parcelas').value, 10);
    if (body.tipo === 'credito' && (!parc || parc < 1)) { $('fp-error').textContent = 'Parcelas: de 1 a 48.'; return; }
    if (!body.nome) { $('fp-error').textContent = 'Informe o nome da forma de pagamento.'; return; }
    const r = fp.editId ? await api('formas_pagamento/' + fp.editId, 'PUT', body) : await api('formas_pagamento', 'POST', body);
    if (r.error) { $('fp-error').textContent = r.error; return; }
    fecharModal('modal-fp'); toast(fp.editId ? 'Forma de pagamento atualizada' : 'Forma de pagamento cadastrada');
    carregar();
  });
  async function apagar(x) {
    if (x.qtd_lanc) { toast(`"${x.nome}" está em ${x.qtd_lanc} lançamento(s). Inative em vez de apagar.`, 'err'); return; }
    if (!await confirmar(`Apagar a forma de pagamento "${x.nome}"?`, { ok: 'Apagar' })) return;
    const r = await api('formas_pagamento/' + x.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Forma de pagamento apagada'); carregar();
  }

  carregar();
})();
