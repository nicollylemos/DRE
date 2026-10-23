/* Logs de atividades */
(function () {
  const ACOES = { create: ['Criação', 'act-create'], update: ['Alteração', 'act-update'], delete: ['Exclusão', 'act-delete'],
    baixa: ['Baixa', 'act-create'], update_status: ['Situação', 'act-update'], update_role: ['Nível de acesso', 'act-warn'],
    reset_senha: ['Senha redefinida', 'act-warn'], import: ['Importação', 'act-create'], undo_import: ['Importação desfeita', 'act-delete'],
    export: ['Exportação', 'act-auth'],
    login: ['Login', 'act-auth'], logout: ['Logout', 'act-auth'], login_failed: ['Login falhou', 'act-delete'], setup_admin: ['Config. inicial', 'act-warn'] };
  const AREAS = { transactions: 'Lançamentos', transfers: 'Transferências', clientes: 'Clientes', fornecedores: 'Fornecedores', parceiros: 'Parceiros',
    categorias: 'Categorias', caixas: 'Caixas / Banco', formas_pagamento: 'Formas de pagamento', users: 'Usuários', perfis: 'Níveis de acesso',
    auth: 'Autenticação', documentos: 'Documentos', importacoes: 'Importações', export: 'Exportações',
    contas_receita: 'Contas de receita', contas_despesa: 'Contas de despesa', centros_custo: 'Centros de custo' };
  const POR = 20;
  // "income - Aluguel - R$1200" (formato gravado pelos lançamentos) → "Receita · Aluguel · R$ 1.200,00"
  function detalhe(d) {
    return String(d)
      .replace(/^(income|expense) - /, (_, t) => (t === 'income' ? 'Receita' : 'Despesa') + ' · ')
      .replace(/ - R\$(-?[\d.]+)/g, (_, v) => ' · ' + fmtBRL(parseFloat(v)))
      .replace(/R\$(-?\d+(?:\.\d+)?)(?=\s|$|;)/g, (_, v) => fmtBRL(parseFloat(v)))
      .replace(/tipo: (income|expense) → (income|expense)/, (_, a, b) => `tipo: ${a === 'income' ? 'receita' : 'despesa'} → ${b === 'income' ? 'receita' : 'despesa'}`)
      .replace(/em_aberto/g, 'em aberto');
  }
  const lg = { pagina: 1 };
  $('log-f-acao').innerHTML = '<option value="">Todas as ações</option>' + Object.entries(ACOES).map(([k, [l]]) => `<option value="${k}">${l}</option>`).join('');
  $('log-f-area').innerHTML = '<option value="">Todas as áreas</option>' + Object.entries(AREAS).map(([k, l]) => `<option value="${k}">${l}</option>`).join('');
  async function carregar() {
    const qs = new URLSearchParams({ page: lg.pagina, limit: POR });
    [['action', 'log-f-acao'], ['entity', 'log-f-area'], ['username', 'log-f-user'], ['from', 'log-f-de'], ['to', 'log-f-ate']].forEach(([k, id]) => { const v = $(id).value.trim(); if (v) qs.set(k, v); });
    const r = await api('logs?' + qs);
    if (r.error) { toast(r.error, 'err'); return; }
    const rows = r.rows || [];
    document.querySelector('#log-table tbody').innerHTML = rows.length ? rows.map(x => {
      const [lbl, cls] = ACOES[x.action] || [x.action, 'act-auth'];
      const dt = new Date(String(x.created_at).replace(' ', 'T'));
      return `<tr>
        <td class="nowrap num">${dt.toLocaleDateString('pt-BR')} <span class="dim">${dt.toLocaleTimeString('pt-BR')}</span></td>
        <td class="nowrap">${x.username ? `<span class="ttl">${esc(x.username)}</span>` : '<span class="dim">—</span>'}</td>
        <td><span class="st ${cls}">${esc(lbl)}</span></td>
        <td class="nowrap">${esc(AREAS[x.entity] || x.entity)}${x.entity_id ? ` <span class="dim">#${x.entity_id}</span>` : ''}</td>
        <td class="det">${x.details ? esc(detalhe(x.details)) : '<span class="dim">—</span>'}</td>
        <td class="nowrap num dim">${esc(x.ip) || '—'}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="empty"><strong>Nenhum registro</strong>Ajuste os filtros.</td></tr>';
    renderPager('log-pager', r.page, r.pages, p => { lg.pagina = p; carregar(); }, r.total, POR);
    $('log-count').textContent = r.total === 1 ? '1 registro' : `${r.total} registros`;
  }
  ['log-f-acao', 'log-f-area', 'log-f-de', 'log-f-ate'].forEach(id => $(id).addEventListener('change', () => { lg.pagina = 1; carregar(); }));
  let t = null;
  $('log-f-user').addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { lg.pagina = 1; carregar(); }, 300); });
  $('log-toolbar').addEventListener('submit', e => { e.preventDefault(); lg.pagina = 1; carregar(); });
  $('log-limpar').addEventListener('click', () => { ['log-f-acao', 'log-f-area', 'log-f-user', 'log-f-de', 'log-f-ate'].forEach(id => $(id).value = ''); lg.pagina = 1; carregar(); });
  carregar();
})();
