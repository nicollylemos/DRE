/* Clientes */
(function () {
  const cli = { lista: [], pagina: 1, editId: null };
  const ed = podeEditar('cad-clientes');
  $('cli-uf').innerHTML = '<option value="">UF</option>' + UFS.map(u => `<option>${u}</option>`).join('');

  async function carregar() {
    const r = await api('clientes');
    cli.lista = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    render();
  }
  function render() {
    const t = norm($('cli-f-busca').value.trim()), td = soDigitos(t);
    const lista = cli.lista.filter(x => !t || [x.nome, x.razao_social, x.cidade, x.email].some(v => norm(v).includes(t)) || (td && soDigitos(x.cnpj_cpf).includes(td)));
    const { itens, pagina, total, n } = paginar(lista, cli.pagina); cli.pagina = pagina;
    document.querySelector('#cli-table tbody').innerHTML = itens.length ? itens.map(x => {
      const menu = acoes('cli' + x.id, [
        { label: 'Visualizar', fn: () => ver(x) },
        ed && { label: 'Editar', fn: () => abrir(x) },
        ed && { label: 'Apagar', danger: true, fn: () => apagar(x) },
      ]);
      return `<tr>
        <td><div class="who">${avatar(x.nome)}<div style="min-width:0"><span class="ttl">${esc(x.nome)}</span>${x.razao_social ? `<small>${esc(x.razao_social)}</small>` : ''}</div></div></td>
        <td class="nowrap num">${esc(x.cnpj_cpf) || '<span class="dim">—</span>'}</td>
        <td class="nowrap">${x.cidade || x.estado ? esc([x.cidade, x.estado].filter(Boolean).join(' / ')) : '<span class="dim">—</span>'}</td>
        <td class="nowrap">${x.telefone ? linkWhats(x.telefone) : '<span class="dim">—</span>'}</td>
        <td class="wrap">${x.email ? `<a class="wa" href="mailto:${esc(x.email)}">${esc(x.email)}</a>` : '<span class="dim">—</span>'}</td>
        <td class="money r">${x.total_receitas ? fmtBRL(x.total_receitas) : '<span class="dim">—</span>'}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="7" class="empty">${cli.lista.length ? '<strong>Nada encontrado</strong>Tente outro termo.' : `<strong>Nenhum cliente cadastrado</strong>${ed ? 'Clique em “Novo cliente” ou importe uma planilha.' : ''}`}</td></tr>`;
    renderPager('cli-pager', pagina, total, p => { cli.pagina = p; render(); }, n);
    $('cli-count').textContent = n === 1 ? '1 cliente' : `${n} clientes`;
  }
  $('cli-toolbar').addEventListener('submit', e => { e.preventDefault(); carregar(); });
  $('cli-f-busca').addEventListener('input', () => { cli.pagina = 1; render(); });

  function hintDoc() {
    const v = $('cli-doc').value, n = soDigitos(v).length, h = $('cli-doc-hint');
    $('btn-cli-cnpj').disabled = n !== 14;
    if (!n) { h.textContent = ''; h.className = 'hint'; return; }
    if (n !== 11 && n !== 14) { h.textContent = `${n} dígitos`; h.className = 'hint'; return; }
    const ok = docOk(v);
    h.textContent = ok ? `✓ ${n === 11 ? 'CPF' : 'CNPJ'} válido` : `✕ ${n === 11 ? 'CPF' : 'CNPJ'} inválido`;
    h.className = 'hint ' + (ok ? 'ok' : 'err');
  }
  $('cli-doc').addEventListener('input', e => { e.target.value = maskDoc(e.target.value); hintDoc(); });
  $('cli-tel').addEventListener('input', e => { e.target.value = maskTel(e.target.value); });
  $('btn-cli-cnpj').addEventListener('click', async () => {
    const dig = soDigitos($('cli-doc').value), btn = $('btn-cli-cnpj'), h = $('cli-doc-hint');
    if (!cnpjValido(dig)) return hintDoc();
    btn.disabled = true; btn.textContent = '...';
    try {
      const d = await consultarCNPJ(dig);
      const set = (id, v) => { if (v && !$(id).value.trim()) $(id).value = v; };
      set('cli-razao', d.razao_social); set('cli-nome', d.nome_fantasia || d.razao_social); set('cli-email', (d.email || '').toLowerCase());
      set('cli-endereco', [[d.descricao_tipo_de_logradouro, d.logradouro].filter(Boolean).join(' '), d.numero, d.complemento, d.bairro, d.cep && `CEP ${d.cep}`].filter(Boolean).join(', '));
      set('cli-cidade', d.municipio); if (d.uf && !$('cli-uf').value) $('cli-uf').value = d.uf;
      if (d.ddd_telefone_1) set('cli-tel', maskTel(d.ddd_telefone_1));
      h.textContent = `✓ ${d.descricao_situacao_cadastral || 'Encontrado'} na Receita`; h.className = 'hint ' + ((d.descricao_situacao_cadastral || 'ATIVA') === 'ATIVA' ? 'ok' : 'err');
    } catch (e) { h.textContent = e.message || 'Consulta indisponível agora'; h.className = 'hint err'; }
    finally { btn.textContent = 'Buscar'; btn.disabled = false; }
  });

  function abrir(x = null) {
    cli.editId = x ? x.id : null;
    $('cli-modal-title').textContent = x ? 'Editar cliente' : 'Novo cliente';
    $('cli-error').textContent = '';
    $('cli-nome').value = x ? x.nome || '' : ''; $('cli-razao').value = x ? x.razao_social || '' : '';
    $('cli-doc').value = x ? maskDoc(x.cnpj_cpf || '') : ''; $('cli-email').value = x ? x.email || '' : '';
    $('cli-tel').value = x ? maskTel(x.telefone || '') : '';
    const uf = x && x.estado ? String(x.estado).toUpperCase() : '';
    if (uf && !UFS.includes(uf) && ![...$('cli-uf').options].some(o => o.value === uf)) $('cli-uf').insertAdjacentHTML('beforeend', `<option>${esc(uf)}</option>`);
    $('cli-uf').value = uf; $('cli-cidade').value = x ? x.cidade || '' : '';
    $('cli-endereco').value = x ? x.endereco || '' : ''; $('cli-obs').value = x ? x.observacoes || '' : '';
    hintDoc();
    abrirModal('modal-cli');
  }
  if ($('btn-novo-cliente')) $('btn-novo-cliente').addEventListener('click', () => abrir());
  $('cli-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-cli').click(); });
  $('btn-salvar-cli').addEventListener('click', async () => {
    const body = { nome: $('cli-nome').value.trim(), razao_social: $('cli-razao').value.trim(), cnpj_cpf: $('cli-doc').value.trim(),
      email: $('cli-email').value.trim(), telefone: $('cli-tel').value.trim(), estado: $('cli-uf').value, cidade: $('cli-cidade').value.trim(),
      endereco: $('cli-endereco').value.trim(), observacoes: $('cli-obs').value.trim() };
    if (!body.nome) { $('cli-error').textContent = 'Informe o nome do cliente.'; return; }
    if (body.cnpj_cpf && !docOk(body.cnpj_cpf)) { $('cli-error').textContent = 'CPF/CNPJ inválido — confira os números.'; return; }
    const btn = $('btn-salvar-cli'); carregando(btn, true);
    const r = cli.editId ? await api('clientes/' + cli.editId, 'PUT', body) : await api('clientes', 'POST', body);
    carregando(btn, false);
    if (r.error) { $('cli-error').textContent = r.error; return; }
    fecharModal('modal-cli'); toast(cli.editId ? 'Cliente atualizado' : 'Cliente cadastrado');
    carregar();
  });
  async function apagar(x) {
    if (!await confirmar(`Apagar o cliente "${x.nome}"?${x.qtd_lanc ? `\n\nOs ${x.qtd_lanc} lançamento(s) dele continuam no sistema com o nome gravado.` : ''}`, { ok: 'Apagar' })) return;
    const r = await api('clientes/' + x.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Cliente apagado'); carregar();
  }
  function ver(x) {
    const botoes = [];
    if (pode('lancamentos') && x.qtd_lanc) botoes.push({ label: 'Ver lançamentos', cls: 'btn-gold', fn: () => { location.href = url('lancamentos?busca=' + encodeURIComponent(x.nome)); } });
    if (ed) botoes.push({ label: 'Editar', fn: () => { fecharModal('modal-view'); abrir(x); } });
    mostrarDetalhes(x.nome, x.razao_social || '', [
      ['CPF / CNPJ', x.cnpj_cpf], ['Telefone', x.telefone], ['E-mail', x.email],
      ['Endereço', x.endereco], ['Cidade / UF', [x.cidade, x.estado].filter(Boolean).join(' / ')],
      ['Lançamentos', String(x.qtd_lanc || 0)], ['Total recebido', fmtBRL(x.total_receitas)],
      ['Observações', x.observacoes], ['Cadastrado em', fmtData(x.created_at)],
    ], botoes);
  }

  carregar();
})();
