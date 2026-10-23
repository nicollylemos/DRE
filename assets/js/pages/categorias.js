/* Categorias */
(function () {
  const cat = { lista: [], pagina: 1, editId: null };
  const ed = podeEditar('cad-categorias');
  const CORES = ['#147A4A', '#0E6E7A', '#3F4172', '#5A5CB4', '#6B5CA5', '#B0577D', '#C0504D', '#CFAE73', '#B98B2E', '#5E6E63'];
  const TIPO_LABEL = { income: 'Receita', expense: 'Despesa', ambos: 'Receita e despesa' };
  const ano = new Date().getFullYear();
  $('cat-ano-label').textContent = `(${ano})`; $('cat-ano-label2').textContent = `(${ano})`;

  async function carregar() {
    const r = await api('categorias?year=' + ano);
    cat.lista = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    render();
  }
  function render() {
    const sit = fv('cat-f-sit'), tipo = fv('cat-f-tipo'), t = norm($('cat-f-busca').value.trim());
    const lista = cat.lista.filter(c => (sit === '' || String(c.ativo) === sit) && (!tipo || c.tipo === tipo || c.tipo === 'ambos') && (!t || norm(c.nome).includes(t) || norm(c.descricao).includes(t)));
    const { itens, pagina, total, n } = paginar(lista, cat.pagina); cat.pagina = pagina;
    document.querySelector('#cat-table tbody').innerHTML = itens.length ? itens.map(c => {
      const menu = acoes('cat' + c.id, [
        { label: 'Visualizar', fn: () => ver(c) },
        ed && { label: 'Editar', fn: () => abrir(c) },
        ed && { label: c.ativo ? 'Desativar' : 'Ativar', fn: () => alternar(c) },
        ed && { label: 'Apagar', danger: true, fn: () => apagar(c) },
      ]);
      return `<tr>
        <td class="nowrap"><div class="who"><span class="sw" style="background:${esc(c.cor)}"></span><span class="ttl">${esc(c.nome)}</span></div></td>
        <td><span class="tp ${c.tipo}">${TIPO_LABEL[c.tipo]}</span></td>
        <td class="wrap">${esc(c.descricao) || '<span class="dim">—</span>'}</td>
        <td class="r num">${c.qtd_ano || '<span class="dim">0</span>'}</td>
        <td class="money r">${c.total_ano ? fmtBRL(c.total_ano) : '<span class="dim">—</span>'}</td>
        <td>${pill(c.ativo, 'Ativa', 'Desativada')}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : `<tr><td colspan="7" class="empty">${cat.lista.length ? '<strong>Nada encontrado</strong>Tente outro termo ou troque os filtros.' : '<strong>Nenhuma categoria ainda</strong>Crie categorias como Aluguel, Consultas ou Marketing.'}</td></tr>`;
    renderPager('cat-pager', pagina, total, p => { cat.pagina = p; render(); }, n);
    $('cat-count').textContent = n === 1 ? '1 categoria' : `${n} categorias`;
  }
  $('cat-toolbar').addEventListener('submit', e => { e.preventDefault(); cat.pagina = 1; carregar(); });
  ['cat-f-sit', 'cat-f-tipo'].forEach(id => $(id).addEventListener('change', () => { cat.pagina = 1; render(); }));
  $('cat-f-busca').addEventListener('input', () => { cat.pagina = 1; render(); });

  function renderCores(sel) {
    const lista = CORES.includes(sel.toUpperCase()) ? CORES : [...CORES, sel];
    $('cat-cores').innerHTML = lista.map(c => `<button type="button" data-cor="${c}" style="background:${c}" class="${c.toUpperCase() === sel.toUpperCase() ? 'on' : ''}" title="${c}"></button>`).join('')
      + `<input type="color" id="cat-cor-livre" value="${sel}" title="Outra cor"/>`;
    $('cat-cores').querySelectorAll('button').forEach(b => b.onclick = () => renderCores(b.dataset.cor));
    $('cat-cor-livre').oninput = e => renderCores(e.target.value);
    $('cat-cores').dataset.cor = sel;
  }
  function abrir(c = null) {
    cat.editId = c ? c.id : null;
    $('cat-modal-title').textContent = c ? 'Editar categoria' : 'Nova categoria';
    $('cat-error').textContent = '';
    $('cat-nome').value = c ? c.nome : '';
    $('cat-desc').value = c ? (c.descricao || '') : '';
    segSet('cat-tipo-seg', c ? c.tipo : (fv('cat-f-tipo') || 'expense'));
    segSet('cat-sit-seg', c ? String(c.ativo) : '1');
    renderCores(c ? c.cor : CORES[cat.lista.length % CORES.length]);
    abrirModal('modal-cat');
  }
  if ($('btn-nova-categoria')) $('btn-nova-categoria').addEventListener('click', () => abrir());
  $('cat-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-cat').click(); });
  async function salvar(id, body) {
    const r = id ? await api('categorias/' + id, 'PUT', body) : await api('categorias', 'POST', body);
    if (!r.error) carregar();
    return r;
  }
  $('btn-salvar-cat').addEventListener('click', async () => {
    const body = { nome: $('cat-nome').value.trim(), tipo: segValor('cat-tipo-seg'), cor: $('cat-cores').dataset.cor, descricao: $('cat-desc').value.trim(), ativo: Number(segValor('cat-sit-seg')) };
    if (!body.nome) { $('cat-error').textContent = 'Informe o nome da categoria.'; return; }
    const r = await salvar(cat.editId, body);
    if (r.error) { $('cat-error').textContent = r.error; return; }
    fecharModal('modal-cat'); toast(cat.editId ? 'Categoria atualizada' : 'Categoria criada');
  });
  async function alternar(c) {
    const r = await salvar(c.id, { nome: c.nome, tipo: c.tipo, cor: c.cor, descricao: c.descricao || '', ativo: c.ativo ? 0 : 1 });
    if (r.error) return toast(r.error, 'err');
    toast(c.ativo ? 'Categoria desativada' : 'Categoria ativada');
  }
  async function apagar(c) {
    if (c.qtd_total) {
      if (await confirmar(`"${c.nome}" está em ${c.qtd_total} lançamento(s), então não pode ser apagada sem perder o histórico. Quer desativá-la? Ela some da lista na hora de lançar, mas os lançamentos antigos continuam com ela.`, { titulo: 'Categoria em uso', ok: 'Desativar', perigo: false })) {
        if (c.ativo) alternar(c);
      }
      return;
    }
    if (!await confirmar(`Apagar a categoria "${c.nome}"?`, { ok: 'Apagar' })) return;
    const r = await api('categorias/' + c.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Categoria apagada'); carregar();
  }
  function ver(c) {
    const botoes = [];
    if (pode('lancamentos')) botoes.push({ label: 'Ver lançamentos', cls: 'btn-gold', fn: () => { location.href = url('lancamentos?categoria=' + c.id); } });
    if (ed) botoes.push({ label: 'Editar', fn: () => { fecharModal('modal-view'); abrir(c); } });
    mostrarDetalhes(c.nome, TIPO_LABEL[c.tipo], [
      ['Cor', `<span class="dot" style="background:${esc(c.cor)}"></span>${esc(c.cor)}`, true], ['Descrição', c.descricao],
      ['Situação', c.ativo ? 'Ativa' : 'Desativada'], [`Lançamentos em ${ano}`, String(c.qtd_ano)], [`Total em ${ano}`, fmtBRL(c.total_ano)],
      ['Lançamentos (todos)', String(c.qtd_total)], ['Criada em', fmtData(c.created_at)],
    ], botoes);
  }

  carregar();
})();
