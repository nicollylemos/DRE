/* Notas fiscais: lista, anexar/substituir/remover PDF e XML, visualizar, vincular a lançamento,
   gerar lançamento a partir do XML e importação em lote. */
(function () {
  const nf = { lista: [], pagina: 1, editId: null, atual: null, lidoXml: null, vincId: null, escolhido: null };
  const ed = podeEditar('notas-fiscais');
  const edLanc = podeEditar('lancamentos');
  const params = new URLSearchParams(location.search);
  let filtroTx = params.get('lancamento') && !params.get('novo') ? params.get('lancamento') : '';
  const TIPO = { entrada: 'Entrada', saida: 'Saída' };
  const arqUrl = (id, q, baixar) => url(`api/notas-fiscais/${id}/arquivo/${q}${baixar ? '?download=1' : ''}`);
  const fmtDoc = d => d ? maskDoc(d) : '';

  // ─── Filtros ───────────────────────────────────────────────────────────────
  const ano = new Date().getFullYear();
  $('nf-f-mes').innerHTML = '<option value="">Todos os meses</option>' + MESES.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
  $('nf-f-ano').innerHTML = '<option value="">Todos os anos</option>' + anosLista().map(a => `<option value="${a}" ${a === ano ? 'selected' : ''}>${a}</option>`).join('');
  if (filtroTx || params.get('ver')) $('nf-f-ano').value = '';

  async function carregar() {
    const qs = new URLSearchParams();
    [['tipo', 'nf-f-tipo'], ['vinculo', 'nf-f-vinculo'], ['month', 'nf-f-mes'], ['year', 'nf-f-ano']].forEach(([k, id]) => { if (fv(id)) qs.set(k, fv(id)); });
    if (filtroTx) qs.set('transaction_id', filtroTx);
    const r = await api('notas-fiscais?' + qs);
    nf.lista = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    render();
  }
  function filtrados() {
    const t = norm($('nf-f-busca').value.trim()), d = soDigitos($('nf-f-busca').value);
    if (!t) return nf.lista;
    return nf.lista.filter(x => [x.numero, x.emitente_nome, x.destinatario_nome, x.descricao, x.chave, x.tx_descricao].some(v => norm(v).includes(t))
      || (d.length >= 3 && [x.emitente_doc, x.destinatario_doc, x.chave].some(v => String(v || '').includes(d))));
  }
  function nomeNota(x) { return `${x.modelo || 'NF'} ${x.numero ? 'nº ' + x.numero : 's/ número'}${x.serie ? ' · série ' + x.serie : ''}`; }
  function contraparte(x) { return x.tipo === 'saida' ? [x.destinatario_nome, x.destinatario_doc] : [x.emitente_nome, x.emitente_doc]; }
  function chipTx(x) {
    if (!x.transaction_id) return '<span class="st off">Sem lançamento</span>';
    const venc = x.tx_status === 'em_aberto' && x.tx_vencimento && x.tx_vencimento < hojeISO();
    const st = x.tx_status === 'quitado' ? '<span class="st on">Quitado</span>' : venc ? '<span class="st late">Vencido</span>' : `<span class="st open">${x.tx_tipo === 'income' ? 'A receber' : 'A pagar'}</span>`;
    return `<div class="nf-tx">${st}<small title="${esc(x.tx_descricao)}">#${x.transaction_id} · ${esc(fmtBRL(x.tx_valor))}${Math.abs((x.tx_valor || 0) - x.valor) > 0.009 ? ' <b class="neg" title="Valor diferente do da nota">≠</b>' : ''}</small></div>`;
  }

  function render() {
    const lista = filtrados();
    let ent = 0, sai = 0, sem = 0;
    lista.forEach(x => { if (x.tipo === 'saida') sai += x.valor; else ent += x.valor; if (!x.transaction_id) sem++; });
    $('nf-kpi-ent').textContent = fmtBRL(ent); $('nf-kpi-sai').textContent = fmtBRL(sai);
    $('nf-kpi-sem').textContent = sem; $('nf-kpi-qtd').textContent = lista.length;

    const { itens, pagina, total, n } = paginar(lista, nf.pagina); nf.pagina = pagina;
    document.querySelector('#nf-table tbody').innerHTML = itens.length ? itens.map(x => {
      const [quem, doc] = contraparte(x);
      const menu = acoes('nf' + x.id, [
        { label: 'Visualizar', fn: () => ver(x.id) },
        ed && { label: 'Editar', fn: () => abrir(x) },
        ed && { label: 'Vincular', fn: () => abrirVinculo(x) },
        ed && edLanc && !x.transaction_id && { label: 'Gerar lançamento', fn: () => gerarLancamento(x) },
        ed && { label: 'Apagar', danger: true, fn: () => apagar(x) },
      ]);
      return `<tr>
        <td class="nowrap num">${x.data_emissao ? fmtData(x.data_emissao) : '<span class="dim">—</span>'}</td>
        <td class="nf-nota"><span class="tp ${x.tipo === 'saida' ? 'income' : 'expense'}">${TIPO[x.tipo]}</span> <span class="ttl">${esc(nomeNota(x))}</span>${x.descricao ? `<small class="nf-desc" title="${esc(x.descricao)}">${esc(x.descricao)}</small>` : ''}</td>
        <td class="wrap">${quem ? `<span class="ttl">${esc(quem)}</span>` : '<span class="dim">—</span>'}${doc ? `<small>${esc(fmtDoc(doc))}</small>` : ''}</td>
        <td class="money r">${x.valor ? fmtBRL(x.valor) : '<span class="dim">—</span>'}</td>
        <td class="nowrap">${x.tem_pdf ? `<a class="fbadge pdf" href="${arqUrl(x.id, 'pdf')}" target="_blank" rel="noopener" title="${esc(x.pdf_nome)}">PDF</a>` : ''}${x.tem_xml ? `<a class="fbadge xml" href="${arqUrl(x.id, 'xml', true)}" title="${esc(x.xml_nome)}">XML</a>` : ''}</td>
        <td>${chipTx(x)}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="7" class="empty">${nf.lista.length ? '<strong>Nenhuma nota com esses filtros</strong>Ajuste a busca.' : `<strong>Nenhuma nota fiscal aqui</strong>${ed ? 'Clique em "Nova nota" ou importe vários XML de uma vez.' : 'Ajuste os filtros.'}`}</td></tr>`;
    renderPager('nf-pager', pagina, total, p => { nf.pagina = p; render(); }, n);

    const chip = $('nf-filtro-tx');
    chip.classList.toggle('hidden', !filtroTx);
    if (filtroTx) {
      chip.innerHTML = `Mostrando só as notas do lançamento <b>#${esc(filtroTx)}</b> <button type="button" class="link" id="nf-limpa-tx">Mostrar todas</button>`;
      $('nf-limpa-tx').onclick = () => { filtroTx = ''; history.replaceState(null, '', location.pathname); $('nf-f-ano').value = String(ano); nf.pagina = 1; carregar(); };
    }
  }
  $('nf-toolbar').addEventListener('submit', e => { e.preventDefault(); nf.pagina = 1; carregar(); });
  ['nf-f-tipo', 'nf-f-vinculo', 'nf-f-mes', 'nf-f-ano'].forEach(id => $(id).addEventListener('change', () => { nf.pagina = 1; carregar(); }));
  $('nf-f-busca').addEventListener('input', () => { nf.pagina = 1; render(); });

  // ─── Área de arquivo (clique ou arrastar) ──────────────────────────────────
  function ligarDrop(dropId, inputId, aoEscolher) {
    const drop = $(dropId), inp = $(inputId);
    drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); inp.click(); } });
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
    drop.addEventListener('drop', e => { if (e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; inp.dispatchEvent(new Event('change')); } });
    inp.addEventListener('change', () => aoEscolher(inp.files));
  }

  // ─── Formulário (nova / editar) ────────────────────────────────────────────
  const campos = { modelo: 'nf-modelo', numero: 'nf-numero', serie: 'nf-serie', data_emissao: 'nf-emissao', valor: 'nf-valor', chave: 'nf-chave',
    emitente_nome: 'nf-emit-nome', emitente_doc: 'nf-emit-doc', destinatario_nome: 'nf-dest-nome', destinatario_doc: 'nf-dest-doc',
    descricao: 'nf-descricao', observacoes: 'nf-obs' };
  ['nf-emit-doc', 'nf-dest-doc'].forEach(id => $(id).addEventListener('input', e => { e.target.value = maskDoc(e.target.value); }));

  function arquivoAtual(qual) {
    const el = $('nf-atual-' + qual), drop = $('nf-drop-' + qual), inp = $('nf-' + qual);
    const novo = inp.files[0];
    const temSalvo = nf.atual && nf.atual['tem_' + qual];
    drop.classList.toggle('tem', !!novo);
    drop.querySelector('strong').textContent = novo ? novo.name : (qual === 'xml' ? 'XML da nota' : 'PDF / DANFE');
    drop.querySelector('small').textContent = novo ? (temSalvo ? 'vai substituir o arquivo atual ao salvar' : 'será anexado ao salvar') : (temSalvo ? 'clique para substituir' : (qual === 'xml' ? 'preenche os campos automaticamente' : 'até 15 MB'));
    if (!temSalvo) { el.classList.add('hidden'); el.innerHTML = ''; return; }
    const nome = nf.atual[qual + '_nome'] || qual.toUpperCase();
    el.classList.remove('hidden');
    el.innerHTML = `<span class="fbadge ${qual}">${qual.toUpperCase()}</span><a class="link nm" href="${arqUrl(nf.atual.id, qual, qual === 'xml')}" ${qual === 'pdf' ? 'target="_blank" rel="noopener"' : ''} title="${esc(nome)}">${esc(nome)}</a>`
      + `<button type="button" class="btn btn-ghost xs danger" data-rm="${qual}" title="Remover este arquivo">Remover</button>`;
    el.querySelector('[data-rm]').onclick = () => removerArquivo(qual);
  }

  function preencher(d, sobrescrever) {
    Object.entries(campos).forEach(([k, id]) => {
      const v = d[k];
      if (v === null || v === undefined || v === '') return;
      if (!sobrescrever && $(id).value) return;
      $(id).value = (k === 'emitente_doc' || k === 'destinatario_doc') ? fmtDoc(v) : v;
    });
    if (d.tipo) segSet('nf-tipo-seg', d.tipo);
  }

  ligarDrop('nf-drop-xml', 'nf-xml', async files => {
    arquivoAtual('xml');
    const f = files[0]; if (!f) return;
    $('nf-error').textContent = '';
    const fd = new FormData(); fd.append('xml', f);
    $('nf-lido').classList.remove('hidden'); $('nf-lido').textContent = 'Lendo o XML…';
    const r = await api('notas-fiscais/ler-xml', 'POST', fd);
    if (r.error) { $('nf-lido').classList.add('hidden'); $('nf-error').textContent = r.error; nf.lidoXml = null; return; }
    nf.lidoXml = r;
    preencher(r, true);
    const partes = [`<b>${esc(r.modelo || 'Nota')} ${esc(r.numero || '')}</b> lida do XML`];
    if (r.duplicatas && r.duplicatas.length) partes.push(`${r.duplicatas.length} duplicata(s), 1º vencimento ${fmtData(r.duplicatas[0].vencimento)}`);
    if (r.natureza) partes.push(esc(r.natureza));
    let html = partes.join(' · ') + '. Confira o tipo (entrada/saída) antes de salvar.';
    if (r.duplicada && r.duplicada.id !== (nf.editId || 0)) html += `<br/><b class="neg">Atenção: já existe uma nota com essa chave (nº ${esc(r.duplicada.numero || r.duplicada.id)}).</b>`;
    $('nf-lido').innerHTML = html;
  });
  ligarDrop('nf-drop-pdf', 'nf-pdf', () => arquivoAtual('pdf'));

  function boxVinculo() {
    const box = $('nf-vinc-box');
    const txId = nf.atual ? nf.atual.transaction_id : nf.preTx;
    if (!txId) { box.innerHTML = ''; box.classList.add('hidden'); return; }
    box.classList.remove('hidden');
    const desc = nf.atual && nf.atual.transaction_id ? `${esc(nf.atual.tx_descricao || 'Lançamento')} · ${fmtBRL(nf.atual.tx_valor)}` : 'lançamento escolhido';
    box.innerHTML = `<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg><span>Vinculada ao lançamento <b>#${txId}</b> — ${desc}</span>`;
  }

  function abrir(x = null, pre = {}) {
    nf.editId = x ? x.id : null; nf.atual = x; nf.lidoXml = null; nf.preTx = pre.transaction_id || null;
    $('nf-modal-title').textContent = x ? `Editar ${nomeNota(x)}` : 'Nova nota fiscal';
    $('nf-error').textContent = ''; $('nf-lido').classList.add('hidden');
    $('nf-xml').value = ''; $('nf-pdf').value = '';
    Object.values(campos).forEach(id => $(id).value = '');
    segSet('nf-tipo-seg', x ? x.tipo : (pre.tipo || 'entrada'));
    if (x) preencher(x, true);
    if (!x && pre.valor) $('nf-valor').value = pre.valor;
    arquivoAtual('xml'); arquivoAtual('pdf'); boxVinculo();
    if ($('nf-gerar-wrap')) { const mostra = !x && !nf.preTx; $('nf-gerar-wrap').classList.toggle('hidden', !mostra); $('nf-gerar').checked = mostra; }
    abrirModal('modal-nf');
  }
  if ($('btn-nf-nova')) $('btn-nf-nova').addEventListener('click', () => abrir());
  $('nf-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-nf').click(); });

  $('btn-salvar-nf').addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('tipo', segValor('nf-tipo-seg'));
    Object.entries(campos).forEach(([k, id]) => fd.append(k, (k.endsWith('_doc') || k === 'chave') ? soDigitos($(id).value) || $(id).value.trim() : $(id).value.trim()));
    const xml = $('nf-xml').files[0], pdf = $('nf-pdf').files[0];
    if (!nf.editId && !xml && !pdf) { $('nf-error').textContent = 'Anexe o XML e/ou o PDF da nota.'; return; }
    if (xml) fd.append('xml', xml);
    if (pdf) fd.append('pdf', pdf);
    if (!nf.editId && nf.preTx) fd.append('transaction_id', nf.preTx);
    const gerar = !nf.editId && $('nf-gerar') && !$('nf-gerar-wrap').classList.contains('hidden') && $('nf-gerar').checked;
    const btn = $('btn-salvar-nf'); carregando(btn, true);
    const r = nf.editId ? await api('notas-fiscais/' + nf.editId, 'POST', fd) : await api('notas-fiscais', 'POST', fd);
    carregando(btn, false);
    if (r.error) { $('nf-error').textContent = r.error; return; }
    fecharModal('modal-nf');
    toast(nf.editId ? 'Nota fiscal atualizada' : 'Nota fiscal salva');
    await carregar();
    if (gerar) gerarLancamento(r);
  });

  async function removerArquivo(qual) {
    const x = nf.atual; if (!x) return;
    if (!await confirmar(`Remover o ${qual.toUpperCase()} "${x[qual + '_nome'] || ''}" desta nota? Os dados da nota continuam.`, { ok: 'Remover' })) return;
    const r = await api(`notas-fiscais/${x.id}/arquivo/${qual}`, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    nf.atual = r; arquivoAtual(qual); toast(qual.toUpperCase() + ' removido'); carregar();
  }

  async function apagar(x) {
    const extra = x.transaction_id ? ' O lançamento vinculado não é apagado.' : '';
    if (!await confirmar(`Apagar a ${nomeNota(x)} e os arquivos dela?${extra}`, { ok: 'Apagar' })) return;
    const r = await api('notas-fiscais/' + x.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Nota fiscal apagada'); carregar();
  }

  // ─── Visualizar ────────────────────────────────────────────────────────────
  ligarAbas('nfv-tabs', aba => { if (aba === 'xml') carregarXml(); });
  let verAtual = null, xmlCarregado = false;
  async function ver(id) {
    const x = await api('notas-fiscais/' + id);
    if (x.error) return toast(x.error, 'err');
    verAtual = x; xmlCarregado = false;
    $('nfv-title').textContent = nomeNota(x);
    $('nfv-sub').textContent = `${TIPO[x.tipo]} · ${fmtBRL(x.valor)}${x.data_emissao ? ' · emitida em ' + fmtData(x.data_emissao) : ''}`;
    const linhas = [
      ['Tipo', x.tipo === 'saida' ? 'Saída (venda / serviço prestado)' : 'Entrada (compra / serviço tomado)'],
      ['Modelo / número / série', [x.modelo, x.numero, x.serie].filter(Boolean).join(' · ')],
      ['Emissão', fmtData(x.data_emissao)], ['Valor', fmtBRL(x.valor)],
      ['Chave / código', x.chave ? `<span class="mono">${esc(x.chave.replace(/(\d{4})(?=\d)/g, '$1 '))}</span>` : '—', true],
      ['Emitente', [x.emitente_nome, fmtDoc(x.emitente_doc)].filter(Boolean).join(' · ')],
      ['Destinatário', [x.destinatario_nome, fmtDoc(x.destinatario_doc)].filter(Boolean).join(' · ')],
      ['Descrição', x.descricao], ['Observações', x.observacoes],
      ['Lançamento', x.transaction_id ? `${chipTx(x)}<div class="muted" style="margin-top:4px">${esc(x.tx_descricao || '')}${pode('lancamentos') ? ` · <a class="link" href="${esc(url('lancamentos?busca=' + encodeURIComponent(x.tx_descricao || '')))}">ver em Lançamentos</a>` : ''}</div>` : 'Sem lançamento vinculado', true],
      ['Arquivos', [x.tem_pdf ? `PDF: ${esc(x.pdf_nome)}` : '', x.tem_xml ? `XML: ${esc(x.xml_nome)}` : ''].filter(Boolean).join('<br/>') || '—', true],
      ['Cadastrada em', fmtDataHora(x.created_at) + (x.updated_at ? ' · alterada em ' + fmtDataHora(x.updated_at) : '')],
    ];
    $('nfv-dados').innerHTML = linhas.map(([k, v, html]) => `<dt>${esc(k)}</dt><dd>${html ? v : (esc(v) || '—')}</dd>`).join('');
    $('nfv-tab-pdf').classList.toggle('hidden', !x.tem_pdf);
    $('nfv-tab-xml').classList.toggle('hidden', !x.tem_xml);
    $('nfv-pdf').innerHTML = x.tem_pdf ? `<iframe src="${arqUrl(x.id, 'pdf')}#view=FitH" title="PDF da nota"></iframe><p class="muted">Não apareceu? <a class="link" href="${arqUrl(x.id, 'pdf')}" target="_blank" rel="noopener">Abrir o PDF em outra aba</a>.</p>` : '';
    $('nfv-xml').textContent = ''; $('nfv-xml-resumo').innerHTML = '';
    // Abre na aba do PDF quando tem (é o que a pessoa quer ver), senão nos dados.
    document.querySelector(`#nfv-tabs button[data-tab="${x.tem_pdf ? 'pdf' : 'dados'}"]`).click();

    const l = $('nfv-foot-l'), rr = $('nfv-foot-r');
    l.innerHTML = (x.tem_pdf ? `<a class="btn btn-ghost" href="${arqUrl(x.id, 'pdf', true)}">Baixar PDF</a>` : '') + (x.tem_xml ? `<a class="btn btn-ghost" href="${arqUrl(x.id, 'xml', true)}">Baixar XML</a>` : '');
    rr.innerHTML = '';
    const botao = (label, cls, fn) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn ' + cls; b.textContent = label; b.onclick = fn; rr.appendChild(b); };
    if (ed && edLanc && !x.transaction_id) botao('Gerar lançamento', 'btn-gold', () => { fecharModal('modal-nf-ver'); gerarLancamento(x); });
    if (ed) botao(x.transaction_id ? 'Trocar vínculo' : 'Vincular', 'btn-ghost', () => { fecharModal('modal-nf-ver'); abrirVinculo(x); });
    if (ed) botao('Editar', 'btn-navy', () => { fecharModal('modal-nf-ver'); abrir(x); });
    abrirModal('modal-nf-ver');
  }
  async function carregarXml() {
    if (!verAtual || !verAtual.tem_xml || xmlCarregado) return;
    xmlCarregado = true;
    $('nfv-xml').textContent = 'Carregando…';
    const r = await api(`notas-fiscais/${verAtual.id}/xml`);
    if (r.error) { $('nfv-xml').textContent = r.error; return; }
    $('nfv-xml').textContent = r.xml;
    const d = r.dados;
    if (r.erro) $('nfv-xml-resumo').innerHTML = `<div class="form-error">${esc(r.erro)}</div>`;
    else if (d && (d.duplicatas.length || d.pagamentos.length)) {
      const dup = d.duplicatas.map(p => `<span class="chip">${esc(p.numero || 'parcela')} · ${fmtData(p.vencimento)} · ${fmtBRL(p.valor)}</span>`).join('');
      const pag = d.pagamentos.map(p => `<span class="chip gray">${esc({ dinheiro: 'Dinheiro', credito: 'Crédito', debito: 'Débito', boleto: 'Boleto', pix: 'PIX', transferencia: 'Transferência' }[p.tipo] || 'Cód. ' + p.codigo)} · ${fmtBRL(p.valor)}</span>`).join('');
      $('nfv-xml-resumo').innerHTML = `<div class="nfv-resumo">${dup ? `<div><b>Duplicatas</b><div class="chips">${dup}</div></div>` : ''}${pag ? `<div><b>Pagamento</b><div class="chips">${pag}</div></div>` : ''}</div>`;
    }
  }

  // ─── Gerar lançamento a partir da nota ─────────────────────────────────────
  async function gerarLancamento(x) {
    const s = await api(`notas-fiscais/${x.id}/lancamento`);
    if (s.error) return toast(s.error, 'err');
    LancForm.abrir(null, s, async () => { toast('Lançamento vinculado à nota'); await carregar(); });
  }

  // ─── Vincular ──────────────────────────────────────────────────────────────
  let tBusca = null;
  async function abrirVinculo(x) {
    nf.vincId = x.id; nf.escolhido = x.transaction_id || null;
    $('nfl-sub').textContent = `${nomeNota(x)} · ${fmtBRL(x.valor)} · ${x.tipo === 'entrada' ? 'mostra despesas' : 'mostra receitas'}`;
    $('nfl-busca').value = ''; $('nfl-aberto').checked = false; $('nfl-error').textContent = '';
    $('nfl-desvincular').classList.toggle('hidden', !x.transaction_id);
    abrirModal('modal-nf-vinc');
    buscarCandidatos();
  }
  async function buscarCandidatos() {
    const qs = new URLSearchParams({ busca: $('nfl-busca').value.trim(), so_aberto: $('nfl-aberto').checked ? '1' : '0' });
    $('nfl-lista').innerHTML = '<p class="muted">Procurando…</p>';
    const r = await api(`notas-fiscais/${nf.vincId}/candidatos?` + qs);
    if (r.error) { $('nfl-lista').innerHTML = ''; $('nfl-error').textContent = r.error; return; }
    $('nfl-lista').innerHTML = r.length ? r.map(t => {
      const venc = t.status === 'em_aberto' && t.data_vencimento && t.data_vencimento < hojeISO();
      const st = t.status === 'quitado' ? '<span class="st on">Quitado</span>' : venc ? '<span class="st late">Vencido</span>' : '<span class="st open">Em aberto</span>';
      return `<label class="nfl-item${t.id === nf.escolhido ? ' on' : ''}">
        <input type="radio" name="nfl" value="${t.id}" ${t.id === nf.escolhido ? 'checked' : ''}/>
        <div class="nfl-main"><b>${esc(t.descricao) || '<span class="dim">Sem descrição</span>'}</b><small>#${t.id} · ${fmtData(t.data)}${t.cliente_fornecedor ? ' · ' + esc(t.cliente_fornecedor) : ''}${t.data_vencimento ? ' · vence ' + fmtData(t.data_vencimento) : ''}${t.qtd_nf && t.id !== nf.escolhido ? ` · já tem ${t.qtd_nf} nota(s)` : ''}</small></div>
        <div class="nfl-v">${t.mesmo_valor ? '<span class="chip gold">mesmo valor</span>' : ''}<b class="money">${fmtBRL(t.valor)}</b>${st}</div>
      </label>`;
    }).join('') : '<p class="vazio-box" style="margin:0">Nenhum lançamento encontrado. Tire o filtro de "em aberto", mude a busca ou use "Gerar lançamento".</p>';
    $('nfl-lista').querySelectorAll('input[name=nfl]').forEach(i => i.addEventListener('change', () => {
      nf.escolhido = Number(i.value);
      $('nfl-lista').querySelectorAll('.nfl-item').forEach(l => l.classList.toggle('on', l.contains(i)));
    }));
  }
  $('nfl-busca').addEventListener('input', () => { clearTimeout(tBusca); tBusca = setTimeout(buscarCandidatos, 300); });
  $('nfl-aberto').addEventListener('change', buscarCandidatos);
  async function salvarVinculo(txId) {
    const r = await api(`notas-fiscais/${nf.vincId}/vinculo`, 'PATCH', { transaction_id: txId });
    if (r.error) { $('nfl-error').textContent = r.error; return; }
    fecharModal('modal-nf-vinc'); toast(txId ? 'Nota vinculada ao lançamento' : 'Vínculo removido'); carregar();
  }
  $('nfl-ok').addEventListener('click', () => { if (!nf.escolhido) { $('nfl-error').textContent = 'Escolha um lançamento da lista.'; return; } salvarVinculo(nf.escolhido); });
  $('nfl-desvincular').addEventListener('click', () => salvarVinculo(null));

  // ─── Importação em lote ────────────────────────────────────────────────────
  if ($('btn-nf-lote')) {
    const resetLote = () => {
      $('lote-arquivos').value = ''; $('lote-arqs').classList.add('hidden'); $('lote-arqs').innerHTML = '';
      $('lote-passo1').classList.remove('hidden'); $('lote-passo2').classList.add('hidden');
      $('lote-drop').classList.remove('tem'); $('lote-drop-t').textContent = 'Arraste os arquivos aqui ou clique para escolher';
      $('lote-drop-s').textContent = 'XML e PDF · até 20 arquivos por vez (10 notas com XML + PDF)';
      $('lote-ok').disabled = true; $('lote-ok').classList.remove('hidden'); $('lote-fechar').textContent = 'Cancelar'; $('lote-error').textContent = '';
    };
    $('btn-nf-lote').addEventListener('click', () => { resetLote(); abrirModal('modal-nf-lote'); });
    if ($('lote-gerar')) $('lote-gerar').addEventListener('change', () => $('lote-sit-wrap').classList.toggle('hidden', !$('lote-gerar').checked));
    ligarDrop('lote-drop', 'lote-arquivos', files => {
      const lista = [...files];
      const xml = lista.filter(f => /\.xml$/i.test(f.name)).length, pdf = lista.filter(f => /\.pdf$/i.test(f.name)).length, outros = lista.length - xml - pdf;
      $('lote-drop').classList.toggle('tem', lista.length > 0);
      $('lote-drop-t').textContent = lista.length ? `${lista.length} arquivo(s) escolhido(s)` : 'Arraste os arquivos aqui ou clique para escolher';
      $('lote-drop-s').textContent = lista.length ? `${xml} XML · ${pdf} PDF${outros ? ` · ${outros} ignorado(s)` : ''} — clique para trocar` : 'XML e PDF · até 20 arquivos por vez (10 notas com XML + PDF)';
      $('lote-arqs').classList.toggle('hidden', !lista.length);
      $('lote-arqs').innerHTML = lista.slice(0, 20).map(f => `<span class="chip ${/\.pdf$/i.test(f.name) ? 'gold' : /\.xml$/i.test(f.name) ? '' : 'gray'}">${esc(f.name)}</span>`).join('');
      $('lote-error').textContent = lista.length > 20 ? 'Escolha no máximo 20 arquivos por vez — importe em partes.' : '';
      $('lote-ok').disabled = !lista.length || lista.length > 20 || !(xml + pdf);
    });
    $('lote-ok').addEventListener('click', async () => {
      const files = [...$('lote-arquivos').files].filter(f => /\.(xml|pdf)$/i.test(f.name));
      const fd = new FormData();
      files.forEach(f => fd.append('arquivos[]', f));
      if ($('lote-gerar') && $('lote-gerar').checked) { fd.append('gerar_lancamentos', '1'); fd.append('situacao', $('lote-sit').value); }
      const btn = $('lote-ok'); carregando(btn, true);
      const r = await api('notas-fiscais/lote', 'POST', fd);
      carregando(btn, false);
      if (r.error) { $('lote-error').textContent = r.error; return; }
      $('lote-passo1').classList.add('hidden'); $('lote-passo2').classList.remove('hidden');
      $('lote-resumo').innerHTML = `<div class="imp-chips"><span class="ic ok"><b>${r.criadas}</b> nota(s) criada(s)</span>${r.lancamentos ? `<span class="ic ok"><b>${r.lancamentos}</b> lançamento(s)</span>` : ''}${r.duplicadas ? `<span class="ic dup"><b>${r.duplicadas}</b> já existia(m)</span>` : ''}${r.erros ? `<span class="ic erro"><b>${r.erros}</b> com erro</span>` : ''}</div>`;
      const SIT = { ok: ['Criada', 'on'], aviso: ['Criada (só PDF)', 'open'], duplicada: ['Já existia', 'off'], erro: ['Erro', 'late'] };
      document.querySelector('#lote-res tbody').innerHTML = r.resultado.map(x => `<tr><td class="wrap">${x.arquivos.map(esc).join('<br/>')}</td><td><span class="st ${SIT[x.situacao][1]}">${SIT[x.situacao][0]}</span><small>${esc(x.msg || '')}</small></td></tr>`).join('');
      $('lote-ok').classList.add('hidden'); $('lote-fechar').textContent = 'Fechar';
      carregar();
    });
  }

  // ─── Vindo de outra tela: ?ver=ID, ?novo=1&lancamento=ID ───────────────────
  carregar().then(() => {
    if (params.get('ver')) ver(Number(params.get('ver')));
    else if (params.get('novo') && ed) {
      abrir(null, { transaction_id: params.get('lancamento') ? Number(params.get('lancamento')) : null, tipo: params.get('tipo') || 'entrada', valor: params.get('valor') || '' });
      history.replaceState(null, '', location.pathname);
    }
  });
})();
