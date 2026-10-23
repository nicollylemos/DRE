/* Importar / Exportar: assistente de importação em 4 passos + exportações + histórico. */
(function () {
  const ed = podeEditar('importacao');
  const TIPOS = { lancamentos: 'Lançamentos', clientes: 'Clientes', fornecedores: 'Fornecedores' };
  const SIT = { ok: ['Pronta', 'on'], aviso: ['Com aviso', 'open'], erro: ['Com erro', 'late'], duplicado: ['Duplicada', 'off'] };
  const st = { tipo: 'lancamentos', arquivo: null, analise: null, previa: null, filtro: '', pagina: 1 };

  // ─── Exportar ───
  const ano = new Date().getFullYear();
  $('exp-ano').innerHTML = anosLista().map(a => `<option value="${a}" ${a === ano ? 'selected' : ''}>${a}</option>`).join('');
  document.querySelectorAll('.exp-item').forEach(a => a.addEventListener('click', e => { e.preventDefault(); baixar(`export/${a.dataset.exp}?year=${$('exp-ano').value}`); }));

  // ─── Histórico ───
  async function carregarHistorico() {
    const r = await api('importacao');
    const lista = Array.isArray(r) ? r : [];
    document.querySelector('#imp-hist tbody').innerHTML = lista.length ? lista.map(h => {
      const desfeita = !!h.desfeita_em;
      const menu = acoes('imp' + h.id, [
        !desfeita && h.restantes && { label: 'Ver registros', fn: () => verRegistros(h.tipo, h.id) },
        ed && !desfeita && h.restantes && { label: 'Desfazer', danger: true, fn: () => desfazer(h) },
      ]);
      return `<tr>
        <td class="nowrap num">${fmtDataHora(h.created_at)}</td>
        <td><span class="tp ${h.tipo === 'lancamentos' ? 'ambos' : 'banco'}">${TIPOS[h.tipo] || esc(h.tipo)}</span></td>
        <td class="wrap"><span class="ttl">${esc(h.arquivo_nome)}</span><small>${h.total_linhas} linha(s) na planilha</small></td>
        <td class="r num">${h.importados}</td>
        <td class="r num">${h.ignorados || '<span class="dim">0</span>'}</td>
        <td class="money r ${h.valor_total < 0 ? 'neg' : h.valor_total > 0 ? 'pos' : ''}">${h.tipo === 'lancamentos' ? fmtBRLs(h.valor_total) : '<span class="dim">—</span>'}</td>
        <td class="nowrap">${esc(h.username || '—')}</td>
        <td>${desfeita ? `<span class="st off" title="${esc(fmtDataHora(h.desfeita_em))}">Desfeita</span>` : h.restantes < h.importados ? `<span class="st open">${h.restantes} restante(s)</span>` : '<span class="st on">Importada</span>'}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="9" class="empty"><strong>Nenhuma importação ainda</strong>As planilhas importadas aparecem aqui.</td></tr>';
  }
  function verRegistros(tipo, id) { location.href = tipo === 'lancamentos' ? url('lancamentos?importacao=' + id) : url(tipo); }
  async function desfazer(h) {
    if (!await confirmar(`Desfazer a importação "${h.arquivo_nome}"? ${h.restantes} registro(s) criados por ela serão apagados. Isso não tem volta.`, { titulo: 'Desfazer importação', ok: 'Desfazer' })) return;
    const r = await api('importacao/' + h.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast(`${r.removidos} registro(s) removido(s)`); carregarHistorico();
  }
  carregarHistorico();

  if (!ed) return; // sem permissão de importar: só exportação e histórico

  // ─── Passos ───
  function passo(n) {
    document.querySelectorAll('.imp-step').forEach(s => s.classList.toggle('hidden', Number(s.dataset.step) !== n));
    document.querySelectorAll('#imp-steps li').forEach(li => { const k = Number(li.dataset.step); li.classList.toggle('on', k === n); li.classList.toggle('done', k < n); });
    document.querySelector('.imp-card').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  document.querySelectorAll('[data-voltar]').forEach(b => b.addEventListener('click', () => passo(Number(b.dataset.voltar))));

  // ─── Passo 1: arquivo ───
  function atualizarModelos() {
    $('imp-modelo-xlsx').href = url(`api/importacao/modelo?tipo=${st.tipo}&formato=xlsx`);
    $('imp-modelo-csv').href = url(`api/importacao/modelo?tipo=${st.tipo}&formato=csv`);
  }
  $('imp-tipo').addEventListener('change', () => { st.tipo = segValor('imp-tipo'); atualizarModelos(); });
  atualizarModelos();
  function escolher(f) {
    if (!f) return;
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'csv', 'txt'].includes(ext)) { $('imp-erro-1').textContent = ext === 'xls' ? 'Arquivo .xls antigo não é suportado. No Excel use "Salvar como" → .xlsx ou CSV.' : 'Envie um arquivo .xlsx ou .csv.'; return; }
    if (f.size > 10 * 1024 * 1024) { $('imp-erro-1').textContent = 'Arquivo maior que 10 MB.'; return; }
    st.arquivo = f; $('imp-erro-1').textContent = '';
    $('imp-drop').classList.add('tem');
    $('imp-drop-t').textContent = f.name;
    $('imp-drop-s').textContent = `${(f.size / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 0 })} KB · clique para trocar`;
    $('imp-ler').disabled = false;
  }
  const drop = $('imp-drop');
  drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('imp-arquivo').click(); } });
  $('imp-arquivo').addEventListener('change', () => escolher($('imp-arquivo').files[0]));
  ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
  ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
  drop.addEventListener('drop', e => escolher(e.dataTransfer.files[0]));

  $('imp-ler').addEventListener('click', async () => {
    if (!st.arquivo) return;
    const fd = new FormData();
    fd.append('tipo', st.tipo); fd.append('cabecalho', $('imp-cabecalho').checked ? '1' : '0'); fd.append('arquivo', st.arquivo);
    carregando($('imp-ler'), true);
    const r = await api('importacao/analisar', 'POST', fd);
    carregando($('imp-ler'), false);
    if (r.error) { $('imp-erro-1').textContent = r.error; return; }
    st.analise = r;
    montarMapa();
    passo(2);
  });

  // ─── Passo 2: colunas ───
  const letra = i => { let s = ''; i++; while (i > 0) { const m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - 1) / 26); } return s; };
  function montarMapa() {
    const a = st.analise;
    $('imp-info-2').innerHTML = `<b>${esc(a.arquivo)}</b> · ${a.total} linha(s) com dados · ${a.colunas.length} coluna(s). Confira qual coluna da planilha vai em cada campo — já sugerimos pelo nome do cabeçalho.`;
    const opcoes = '<option value="">— não importar —</option>' + a.colunas.map((c, i) => `<option value="${i}">${letra(i)} · ${esc(c)}</option>`).join('');
    $('imp-map').innerHTML = `<div class="map-h"><span>Campo do sistema</span><span>Coluna da planilha</span><span>Exemplo (1ª linha)</span></div>` + a.campos.map(c => `
      <div class="map-l ${c.obrigatorio ? 'obrig' : ''}">
        <label for="map-${c.chave}"><b>${esc(c.label)}</b>${c.obrigatorio ? ' <span class="req">obrigatório</span>' : ''}</label>
        <div class="select"><select id="map-${c.chave}" data-campo="${c.chave}">${opcoes}</select></div>
        <span class="map-ex" id="map-ex-${c.chave}"></span>
      </div>`).join('');
    a.campos.forEach(c => {
      const sel = $('map-' + c.chave);
      sel.value = a.mapeamento[c.chave] === null || a.mapeamento[c.chave] === undefined ? '' : String(a.mapeamento[c.chave]);
      const ex = () => { const v = sel.value === '' ? '' : (a.amostra[0] || [])[Number(sel.value)]; $('map-ex-' + c.chave).textContent = v || (sel.value === '' ? '' : '(vazio)'); sel.closest('.map-l').classList.toggle('mapeado', sel.value !== ''); };
      sel.addEventListener('change', ex); ex();
    });
    document.querySelectorAll('#imp-opcoes .so-lanc').forEach(el => el.classList.toggle('hidden', a.tipo !== 'lancamentos'));
    $('imp-opcoes').classList.toggle('hidden', a.tipo !== 'lancamentos');
    $('imp-erro-2').textContent = '';
  }
  function corpo() {
    const mapeamento = {};
    st.analise.campos.forEach(c => { const v = $('map-' + c.chave).value; mapeamento[c.chave] = v === '' ? null : Number(v); });
    return {
      tipo: st.analise.tipo, token: st.analise.token, mapeamento,
      opcoes: { tipo_padrao: $('imp-op-tipo').value, status_padrao: $('imp-op-status').value, criar_categorias: $('imp-op-cat').checked, ignorar_duplicados: $('imp-op-dup').checked },
    };
  }
  $('imp-validar').addEventListener('click', async () => {
    const b = corpo();
    const falta = st.analise.campos.filter(c => c.obrigatorio && b.mapeamento[c.chave] === null).map(c => c.label);
    if (falta.length) { $('imp-erro-2').textContent = 'Escolha a coluna de: ' + falta.join(', ') + '.'; return; }
    const usadas = Object.values(b.mapeamento).filter(v => v !== null);
    if (new Set(usadas).size !== usadas.length) { $('imp-erro-2').textContent = 'A mesma coluna foi escolhida para dois campos.'; return; }
    carregando($('imp-validar'), true);
    const r = await api('importacao/validar', 'POST', b);
    carregando($('imp-validar'), false);
    if (r.error) { $('imp-erro-2').textContent = r.error; if (r.error.includes('expirou')) passo(1); return; }
    st.previa = r; st.filtro = ''; st.pagina = 1; fset('imp-f', '');
    renderPrevia();
    passo(3);
  });

  // ─── Passo 3: conferir ───
  function renderPrevia() {
    const p = st.previa, c = p.contagem, lanc = p.tipo === 'lancamentos';
    const ignoraDup = p.opcoes.ignorar_duplicados || !lanc;
    $('imp-resumo').innerHTML = `
      <div class="imp-chips">
        <span class="ic ok"><b>${c.ok}</b> prontas</span>
        <span class="ic aviso"><b>${c.aviso}</b> com aviso</span>
        <span class="ic dup"><b>${c.duplicado}</b> duplicadas${c.duplicado ? (ignoraDup ? ' (serão puladas)' : ' (serão importadas)') : ''}</span>
        <span class="ic erro"><b>${c.erro}</b> com erro${c.erro ? ' (serão puladas)' : ''}</span>
      </div>
      ${lanc ? `<p class="imp-somas">Vai entrar: <span class="pos">${fmtBRL(p.soma.income)} em receitas</span> · <span class="neg">${fmtBRL(p.soma.expense)} em despesas</span></p>` : ''}`;
    $('imp-confirmar').querySelector('.lbl').textContent = p.importaveis ? `Importar ${p.importaveis} ${p.importaveis === 1 ? 'linha' : 'linhas'}` : 'Nada para importar';
    $('imp-confirmar').disabled = !p.importaveis;

    const cab = lanc
      ? ['Linha', 'Situação', 'Data', 'Tipo', 'Descrição', 'Categoria', 'Valor', 'Status', 'Observação']
      : ['Linha', 'Situação', 'Nome', 'CPF/CNPJ', 'E-mail', 'Cidade/UF', 'Observação'];
    document.querySelector('#imp-prev thead').innerHTML = '<tr>' + cab.map((h, i) => `<th class="${lanc && i === 6 ? 'r' : ''}">${h}</th>`).join('') + '</tr>';
    const lista = st.filtro ? p.linhas.filter(l => l.situacao === st.filtro) : p.linhas;
    const { itens, pagina, total, n } = paginar(lista, st.pagina, 25); st.pagina = pagina;
    document.querySelector('#imp-prev tbody').innerHTML = itens.length ? itens.map(l => {
      const d = l.dados || {}, [sl, sc] = SIT[l.situacao];
      const msgs = [...l.erros.map(m => `<span class="neg">${esc(m)}</span>`), ...l.avisos.map(m => esc(m))].join('<br/>') || '<span class="dim">—</span>';
      const cols = lanc
        ? [fmtData(d.data), d.tipo ? `<span class="tp ${d.tipo}">${d.tipo === 'income' ? 'Receita' : 'Despesa'}</span>` : '—', esc(d.descricao) || '<span class="dim">—</span>',
           esc(d.categoria_nome) || '<span class="dim">—</span>', d.valor ? `<span class="money ${d.tipo === 'income' ? 'pos' : 'neg'}">${fmtBRL(d.valor)}</span>` : '—',
           d.status ? (d.status === 'quitado' ? 'Quitado' : 'Em aberto') : '—']
        : [esc(d.nome) || '<span class="dim">—</span>', esc(d.cnpj_cpf) || '<span class="dim">—</span>', esc(d.email) || '<span class="dim">—</span>', esc([d.cidade, d.estado].filter(Boolean).join('/')) || '<span class="dim">—</span>'];
      return `<tr class="lp-${l.situacao}"><td class="num dim">${l.linha}</td><td><span class="st ${sc}">${sl}</span></td>${cols.map((v, i) => `<td class="${lanc && i === 4 ? 'r nowrap' : 'wrap'}">${v}</td>`).join('')}<td class="msgs">${msgs}</td></tr>`;
    }).join('') : `<tr><td colspan="${cab.length}" class="empty">Nenhuma linha nessa situação.</td></tr>`;
    renderPager('imp-pager', pagina, total, pg => { st.pagina = pg; renderPrevia(); }, n, 25);
  }
  $('imp-f').addEventListener('change', () => { st.filtro = fv('imp-f'); st.pagina = 1; renderPrevia(); });

  $('imp-confirmar').addEventListener('click', async () => {
    const p = st.previa;
    if (!await confirmar(`Importar ${p.importaveis} linha(s) de "${p.arquivo}" em ${TIPOS[p.tipo]}?`, { titulo: 'Confirmar importação', ok: 'Importar', perigo: false })) return;
    carregando($('imp-confirmar'), true);
    const r = await api('importacao/confirmar', 'POST', corpo());
    carregando($('imp-confirmar'), false);
    if (r.error) { $('imp-erro-3').textContent = r.error; return; }
    $('imp-ok').innerHTML = `<div class="imp-ok-ico"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></div>
      <h3>${r.importados} ${r.importados === 1 ? 'registro importado' : 'registros importados'}</h3>
      <p>${esc(r.arquivo)} → ${TIPOS[r.tipo]}${r.ignorados ? ` · ${r.ignorados} linha(s) pulada(s)` : ''}${r.categorias_criadas ? ` · ${r.categorias_criadas} categoria(s) criada(s)` : ''}</p>
      ${r.tipo === 'lancamentos' ? `<p>Saldo importado: <b class="${r.saldo < 0 ? 'neg' : 'pos'}">${fmtBRLs(r.saldo)}</b></p>` : ''}
      <p class="muted">Errou alguma coisa? Use "Desfazer" no histórico abaixo — apaga só o que essa importação criou.</p>`;
    $('imp-ver').href = r.tipo === 'lancamentos' ? url('lancamentos?importacao=' + r.id) : url(r.tipo);
    passo(4);
    toast('Importação concluída');
    carregarHistorico();
  });
  $('imp-nova').addEventListener('click', () => {
    st.arquivo = null; st.analise = null; st.previa = null;
    $('imp-arquivo').value = ''; $('imp-drop').classList.remove('tem');
    $('imp-drop-t').textContent = 'Arraste a planilha aqui ou clique para escolher';
    $('imp-drop-s').textContent = 'Excel (.xlsx) ou CSV · até 10 MB · até 5.000 linhas';
    $('imp-ler').disabled = true; ['imp-erro-1', 'imp-erro-2', 'imp-erro-3'].forEach(id => $(id).textContent = '');
    passo(1);
  });
})();
