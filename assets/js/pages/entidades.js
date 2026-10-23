/* Parceiros e Fornecedores (mesma tela, configuração por tipo). */
(function () {
  const CFG = {
    parceiros: {
      api: 'parceiros', tela: 'cad-parceiros', nome: 'Parceiro', docTipo: 'contrato_parceiro', docKey: 'parceiro_id',
      docCampo: 'cnpj', docLabel: 'CNPJ', aceitaCPF: false, contatoCampo: 'contato', comissao: true,
      obrigatorios: { nome: 'Nome', razao: 'Razão Social', doc: 'CNPJ', resp: 'Responsável', contato: 'Contato' },
    },
    fornecedores: {
      api: 'fornecedores', tela: 'cad-fornecedores', nome: 'Fornecedor', docTipo: 'contrato_fornecedor', docKey: 'fornecedor_id',
      docCampo: 'cnpj_cpf', docLabel: 'CNPJ / CPF', aceitaCPF: true, contatoCampo: 'telefone', comissao: false,
      obrigatorios: { nome: 'Nome', doc: 'CNPJ/CPF' },
    },
  };
  const cfg = CFG[APP.pagina];
  const ed = podeEditar(cfg.tela);
  const st = { lista: [], pagina: 1, item: null, contrato: null };

  async function carregar() {
    const r = await api(cfg.api);
    st.lista = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    render();
  }
  function render() {
    const sit = fv('ent-f-sit');
    const t = norm($('ent-f-busca').value.trim()), td = soDigitos(t);
    const lista = st.lista.filter(x => (sit === '' || String(x.ativo) === sit) &&
      (!t || [x.nome, x.razao_social, x.responsavel].some(v => norm(v).includes(t)) || (td && soDigitos(x[cfg.docCampo]).includes(td))));
    const { itens, pagina, total, n } = paginar(lista, st.pagina); st.pagina = pagina;
    document.querySelector('#ent-table tbody').innerHTML = itens.length ? itens.map(x => {
      const menu = acoes('ent' + x.id, [
        { label: 'Visualizar', fn: () => ver(x) },
        { label: 'Contrato', fn: () => abrirContratos(x) },
        ed && { label: 'Editar', fn: () => abrir(x) },
        ed && { label: x.ativo ? 'Desativar' : 'Ativar', fn: () => alternar(x) },
        ed && { label: 'Apagar', danger: true, fn: () => apagar(x) },
      ]);
      return `<tr>
        <td><div class="who">${avatar(x.nome, x.foto_path)}<span class="ttl">${esc(x.nome)}</span>${x.contrato_path ? '<svg class="clip" viewBox="0 0 24 24" aria-label="Tem contrato"><title>Contrato anexado</title><path d="m21 11-8.6 8.6a5 5 0 0 1-7-7l8.5-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.3-2.3l7.9-8"/></svg>' : ''}</div></td>
        <td class="wrap">${esc(x.razao_social) || '<span class="dim">—</span>'}</td>
        <td class="nowrap num">${esc(x[cfg.docCampo]) || '<span class="dim">—</span>'}</td>
        <td class="wrap">${esc(x.responsavel) || '<span class="dim">—</span>'}</td>
        <td class="nowrap">${linkWhats(x.whatsapp)}</td>
        <td>${pill(x.ativo)}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="7" class="empty">${st.lista.length ? '<strong>Nada encontrado</strong>Tente outro termo ou troque o filtro de situação.' : `<strong>Nenhum ${cfg.nome.toLowerCase()} cadastrado</strong>${ed ? `Clique em “Novo ${cfg.nome.toLowerCase()}” para começar.` : ''}`}</td></tr>`;
    renderPager('ent-pager', pagina, total, p => { st.pagina = p; render(); }, n);
    $('ent-count').textContent = n === 1 ? '1 resultado' : `${n} resultados`;
  }
  $('ent-toolbar').addEventListener('submit', e => { e.preventDefault(); st.pagina = 1; carregar(); });
  $('ent-f-sit').addEventListener('change', () => { st.pagina = 1; render(); });
  $('ent-f-busca').addEventListener('input', () => { st.pagina = 1; render(); });
  if ($('btn-novo-ent')) $('btn-novo-ent').addEventListener('click', () => abrir());

  // ─── Formulário ───
  const mascaraDoc = v => cfg.aceitaCPF && soDigitos(v).length <= 11 ? maskCPF(v) : maskCNPJ(v);
  const docValido = v => { const n = soDigitos(v).length; return n === 14 ? cnpjValido(v) : (cfg.aceitaCPF && n === 11 && cpfValido(v)); };
  function abrir(x = null) {
    st.item = x;
    const ob = cfg.obrigatorios;
    $('ent-modal-title').textContent = (x ? 'Editar ' : 'Novo ') + cfg.nome.toLowerCase();
    $('ent-razao-label').textContent = 'Razão Social' + (ob.razao ? ' *' : '');
    $('ent-doc-label').textContent = cfg.docLabel + ' *';
    $('ent-resp-label').textContent = 'Responsável' + (ob.resp ? ' *' : '');
    $('ent-contato-label').textContent = (cfg.contatoCampo === 'telefone' ? 'Telefone' : 'Contato') + (ob.contato ? ' *' : '');
    $('ent-doc').placeholder = cfg.aceitaCPF ? 'CNPJ ou CPF' : '00.000.000/0000-00';
    $('ent-comissao-wrap').classList.toggle('hidden', !cfg.comissao);
    $('ent-error').textContent = '';
    $('ent-nome').value = x ? x.nome || '' : '';
    $('ent-razao').value = x ? x.razao_social || '' : '';
    $('ent-doc').value = x ? mascaraDoc(x[cfg.docCampo] || '') : '';
    $('ent-email').value = x ? x.email || '' : '';
    $('ent-resp').value = x ? x.responsavel || '' : '';
    $('ent-contato').value = x ? maskTel(x[cfg.contatoCampo] || '') : '';
    $('ent-whats').value = x ? maskTel(x.whatsapp || '') : '';
    $('ent-comissao').value = x && x.comissao ? Number(x.comissao).toFixed(2) : '';
    $('ent-endereco').value = x ? x.endereco || '' : '';
    $('ent-obs').value = x ? x.observacoes || '' : '';
    $('ent-contrato').value = '';
    $('ent-contrato-nome').textContent = x && x.contrato_nome ? `Atual: ${x.contrato_nome}` : 'Nenhum arquivo';
    segSet('ent-sit-seg', x ? String(x.ativo) : '1');
    hintDoc();
    abrirModal('modal-ent');
  }
  function hintDoc() {
    const v = $('ent-doc').value, n = soDigitos(v).length, h = $('ent-doc-hint');
    $('btn-buscar-cnpj').disabled = n !== 14;
    if (!n) { h.textContent = ''; h.className = 'hint'; return; }
    if (n < 11 || (n > 11 && n < 14) || (n === 11 && !cfg.aceitaCPF)) { h.textContent = `${n} dígitos`; h.className = 'hint'; return; }
    const ok = docValido(v);
    h.textContent = ok ? `✓ ${n === 11 ? 'CPF' : 'CNPJ'} válido` : `✕ ${n === 11 ? 'CPF' : 'CNPJ'} inválido`;
    h.className = 'hint ' + (ok ? 'ok' : 'err');
  }
  $('ent-doc').addEventListener('input', e => { e.target.value = mascaraDoc(e.target.value); hintDoc(); });
  ['ent-contato', 'ent-whats'].forEach(id => $(id).addEventListener('input', e => { e.target.value = maskTel(e.target.value); }));
  $('ent-contrato-btn').addEventListener('click', () => $('ent-contrato').click());
  $('ent-contrato').addEventListener('change', () => { const f = $('ent-contrato').files[0]; $('ent-contrato-nome').textContent = f ? f.name : 'Nenhum arquivo'; });
  $('btn-buscar-cnpj').addEventListener('click', async () => {
    const dig = soDigitos($('ent-doc').value), btn = $('btn-buscar-cnpj'), h = $('ent-doc-hint');
    if (!cnpjValido(dig)) return hintDoc();
    btn.disabled = true; btn.textContent = '...';
    try {
      const d = await consultarCNPJ(dig);
      const set = (id, v) => { if (v && !$(id).value.trim()) $(id).value = v; };
      set('ent-razao', d.razao_social); set('ent-nome', d.nome_fantasia || d.razao_social); set('ent-email', (d.email || '').toLowerCase());
      set('ent-endereco', [[d.descricao_tipo_de_logradouro, d.logradouro].filter(Boolean).join(' '), d.numero, d.bairro, d.municipio && `${d.municipio}/${d.uf}`, d.cep && `CEP ${d.cep}`].filter(Boolean).join(', '));
      if (d.ddd_telefone_1) set('ent-contato', maskTel(d.ddd_telefone_1));
      h.textContent = `✓ ${d.descricao_situacao_cadastral || 'Encontrado'} na Receita`; h.className = 'hint ' + ((d.descricao_situacao_cadastral || 'ATIVA') === 'ATIVA' ? 'ok' : 'err');
    } catch (e) { h.textContent = e.message || 'Consulta indisponível agora'; h.className = 'hint err'; }
    finally { btn.textContent = 'Buscar'; btn.disabled = false; }
  });
  $('ent-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-ent').click(); });
  $('btn-salvar-ent').addEventListener('click', async () => {
    const item = st.item, ob = cfg.obrigatorios;
    const val = { nome: $('ent-nome').value.trim(), razao: $('ent-razao').value.trim(), doc: $('ent-doc').value.trim(), resp: $('ent-resp').value.trim(), contato: $('ent-contato').value.trim() };
    const faltando = Object.keys(ob).filter(k => !val[k]).map(k => ob[k]);
    if (faltando.length) { $('ent-error').textContent = 'Preencha: ' + faltando.join(', ') + '.'; return; }
    if (!docValido(val.doc)) { $('ent-error').textContent = `${cfg.docLabel} inválido — confira os números.`; return; }
    const fd = new FormData();
    fd.append('nome', val.nome); fd.append('razao_social', val.razao); fd.append(cfg.docCampo, val.doc);
    fd.append('email', $('ent-email').value.trim()); fd.append('responsavel', val.resp); fd.append(cfg.contatoCampo, val.contato);
    fd.append('whatsapp', $('ent-whats').value.trim()); fd.append('endereco', $('ent-endereco').value.trim());
    fd.append('observacoes', $('ent-obs').value.trim()); fd.append('ativo', segValor('ent-sit-seg'));
    if (cfg.comissao) fd.append('comissao', Math.max(0, parseFloat($('ent-comissao').value) || 0));
    const arq = $('ent-contrato').files[0]; if (arq) fd.append('contrato', arq);
    const btn = $('btn-salvar-ent'); carregando(btn, true);
    const r = await api(item ? `${cfg.api}/${item.id}` : cfg.api, 'POST', fd);
    carregando(btn, false);
    if (r.error) { $('ent-error').textContent = r.error; return; }
    fecharModal('modal-ent');
    toast(`${cfg.nome} ${item ? 'atualizado' : 'cadastrado'}`);
    carregar();
  });

  async function alternar(x) {
    const r = await api(`${cfg.api}/${x.id}/situacao`, 'PATCH', { ativo: x.ativo ? 0 : 1 });
    if (r.error) return toast(r.error, 'err');
    toast(x.ativo ? `${cfg.nome} desativado` : `${cfg.nome} ativado`); carregar();
  }
  async function apagar(x) {
    if (!await confirmar(`Apagar o ${cfg.nome.toLowerCase()} "${x.nome}"? O contrato e os aditivos anexados também serão apagados.${x.ativo ? '\n\nSe só quiser tirar da lista, use "Desativar".' : ''}`, { ok: 'Apagar' })) return;
    const r = await api(`${cfg.api}/${x.id}`, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast(`${cfg.nome} apagado`); carregar();
  }
  function ver(x) {
    const contrato = x.contrato_path ? `<a href="${esc(url(x.contrato_path))}" target="_blank" rel="noopener" class="link">${esc(x.contrato_nome || 'Abrir contrato')}</a>` : 'Sem contrato anexado';
    const botoes = [{ label: 'Contrato', cls: 'btn-gold', fn: () => { fecharModal('modal-view'); abrirContratos(x); } }];
    if (ed) botoes.push({ label: 'Editar', fn: () => { fecharModal('modal-view'); abrir(x); } });
    mostrarDetalhes(x.nome, x.razao_social || '', [
      [cfg.docLabel, x[cfg.docCampo]], ['Responsável', x.responsavel], [cfg.contatoCampo === 'telefone' ? 'Telefone' : 'Contato', x[cfg.contatoCampo]],
      ['WhatsApp', x.whatsapp], ['E-mail', x.email], ['Endereço', x.endereco],
      cfg.comissao ? ['Comissão', x.comissao ? fmtBRL(x.comissao) : '—'] : null,
      ['Situação', x.ativo ? 'Ativo' : 'Desativado'], ['Contrato', contrato, true],
      ['Aditivos', x.qtd_contratos ? `${x.qtd_contratos} arquivo(s)` : 'Nenhum'], ['Observações', x.observacoes],
      ['Cadastrado em', fmtData(x.created_at)],
    ], botoes);
  }

  // ─── Contratos (principal + aditivos) ───
  async function abrirContratos(x) {
    st.contrato = x;
    $('contrato-sub').textContent = `${x.nome}${x[cfg.docCampo] ? ' · ' + x[cfg.docCampo] : ''}`;
    $('contrato-error').textContent = ''; if ($('contrato-desc')) $('contrato-desc').value = '';
    renderPrincipal();
    abrirModal('modal-contrato');
    carregarAditivos();
  }
  function renderPrincipal() {
    const x = st.contrato;
    $('contrato-principal').innerHTML = x.contrato_path
      ? `<div class="doc"><div style="min-width:0"><div class="nm">📄 ${esc(x.contrato_nome || 'Contrato')}</div><div class="meta">Contrato principal</div></div>
          <div class="acts"><a class="btn btn-ghost sm" href="${esc(url(x.contrato_path))}" target="_blank" rel="noopener">Abrir</a>
          ${ed ? '<button class="btn btn-ghost sm" type="button" id="btn-contrato-trocar">Substituir</button><button class="btn btn-ghost sm danger" type="button" id="btn-contrato-remover">Remover</button>' : ''}</div></div>`
      : `<div class="doc-empty">Nenhum contrato anexado ainda.${ed ? '<button class="btn btn-gold sm" type="button" id="btn-contrato-trocar">Anexar contrato</button>' : ''}</div>`;
    if ($('btn-contrato-trocar')) $('btn-contrato-trocar').onclick = () => $('contrato-main-file').click();
    if ($('btn-contrato-remover')) $('btn-contrato-remover').onclick = removerPrincipal;
  }
  $('contrato-main-file').addEventListener('change', async () => {
    const f = $('contrato-main-file').files[0]; if (!f) return;
    const item = st.contrato, fd = new FormData(); fd.append('contrato', f);
    const r = await api(`${cfg.api}/${item.id}/contrato`, 'POST', fd);
    $('contrato-main-file').value = '';
    if (r.error) { $('contrato-error').textContent = r.error; return; }
    Object.assign(item, r); renderPrincipal(); toast('Contrato anexado'); carregar();
  });
  async function removerPrincipal() {
    const item = st.contrato;
    if (!await confirmar(`Remover o contrato principal de "${item.nome}"?`, { ok: 'Remover' })) return;
    const r = await api(`${cfg.api}/${item.id}/contrato`, 'DELETE');
    if (r.error) { $('contrato-error').textContent = r.error; return; }
    item.contrato_path = item.contrato_nome = null; renderPrincipal(); toast('Contrato removido'); carregar();
  }
  async function carregarAditivos() {
    const item = st.contrato;
    const r = await api(`documentos?tipo=${cfg.docTipo}&${cfg.docKey}=${item.id}`);
    const docs = Array.isArray(r) ? r : [];
    $('contrato-extras').innerHTML = docs.length ? docs.map(d => `<div class="doc"><div style="min-width:0"><div class="nm">📄 ${esc(d.descricao || d.arquivo_nome)}</div><div class="meta">${esc(d.arquivo_nome)} · ${fmtData(d.created_at)}</div></div>
        <div class="acts"><a class="btn btn-ghost sm" href="${esc(url(d.arquivo_path))}" target="_blank" rel="noopener">Abrir</a>${ed ? `<button class="btn btn-ghost sm danger" type="button" data-doc="${d.id}">Apagar</button>` : ''}</div></div>`).join('')
      : '<div class="doc-empty">Nenhum aditivo anexado.</div>';
    $('contrato-extras').querySelectorAll('[data-doc]').forEach(b => b.onclick = async () => {
      if (!await confirmar('Apagar esse arquivo?', { ok: 'Apagar' })) return;
      const r2 = await api('documentos/' + b.dataset.doc, 'DELETE');
      if (r2.error) return toast(r2.error, 'err');
      carregarAditivos(); carregar();
    });
  }
  if ($('contrato-extra-btn')) $('contrato-extra-btn').addEventListener('click', () => $('contrato-extra-file').click());
  $('contrato-extra-file').addEventListener('change', async () => {
    const f = $('contrato-extra-file').files[0]; if (!f) return;
    const item = st.contrato, fd = new FormData();
    fd.append('tipo', cfg.docTipo); fd.append(cfg.docKey, item.id); fd.append('descricao', $('contrato-desc').value.trim()); fd.append('arquivo', f);
    const r = await api('documentos', 'POST', fd);
    $('contrato-extra-file').value = '';
    if (r.error) { $('contrato-error').textContent = r.error; return; }
    $('contrato-desc').value = ''; toast('Arquivo anexado'); carregarAditivos(); carregar();
  });

  carregar();
})();
