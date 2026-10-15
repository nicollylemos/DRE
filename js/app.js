/* ═══════════════════════════════════════════════════════════════════════════
   OAF — Controle Financeiro · front-end
   Telas: Lançamentos, Parceiros, Fornecedores, Categorias e Usuários (admin).
   ═══════════════════════════════════════════════════════════════════════════ */
'use strict';

// ─── UTILITÁRIOS ──────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const API = 'api'; // relativo: funciona na raiz ou em subpasta (ex: localhost/DRE-php/)
const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const POR_PAGINA = 10;

// Escapa texto vindo do banco antes de ir pro innerHTML (evita XSS).
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtBRL = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtData = d => d ? String(d).slice(0, 10).split('-').reverse().join('/') : '—';
const hojeISO = () => { const d = new Date(); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };
const soDigitos = v => String(v || '').replace(/\D/g, '');
const norm = v => String(v || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

async function api(path, method = 'GET', body = null) {
  const opts = { method, credentials: 'include', headers: {} };
  if (body instanceof FormData) opts.body = body;
  else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  try {
    const res = await fetch(`${API}/${path}`, opts);
    const data = await res.json().catch(() => ({}));
    if (res.status === 401 && path !== 'auth/login') { mostrarLogin(); return { error: data.error || 'Sessão expirada' }; }
    if (!res.ok && !data.error) data.error = `Erro ${res.status}`;
    return data;
  } catch (e) {
    console.error('API', path, e);
    return { error: 'Não foi possível falar com o servidor' };
  }
}

function toast(msg, tipo = 'ok') {
  const el = document.createElement('div');
  el.className = `toast ${tipo}`;
  el.textContent = msg;
  $('toasts').appendChild(el);
  setTimeout(() => el.remove(), tipo === 'err' ? 5000 : 2800);
}

// ─── MODAIS ───────────────────────────────────────────────────────────────────
function abrirModal(id) { $(id).classList.remove('hidden'); const f = $(id).querySelector('input:not([type=hidden]):not([hidden]),select'); if (f) setTimeout(() => f.focus(), 60); }
function fecharModal(id) { $(id).classList.add('hidden'); }
document.querySelectorAll('.modal').forEach(m => {
  m.addEventListener('mousedown', e => { if (e.target === m && m.id !== 'modal-confirm') fecharModal(m.id); });
  m.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); fecharModal(m.id); }));
});
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  if (!$('row-menu').classList.contains('hidden')) return fecharMenu();
  const abertos = [...document.querySelectorAll('.modal:not(.hidden)')];
  if (abertos.length) fecharModal(abertos[abertos.length - 1].id);
});

// Confirmação bonita no lugar do confirm() do navegador.
function confirmar(msg, { titulo = 'Tem certeza?', ok = 'Confirmar', perigo = true } = {}) {
  return new Promise(resolve => {
    $('confirm-title').textContent = titulo;
    $('confirm-msg').textContent = msg;
    $('confirm-sim').textContent = ok;
    $('confirm-sim').className = 'btn ' + (perigo ? 'btn-danger' : 'btn-navy');
    const fim = v => { fecharModal('modal-confirm'); $('confirm-sim').onclick = $('confirm-nao').onclick = null; resolve(v); };
    $('confirm-sim').onclick = () => fim(true);
    $('confirm-nao').onclick = () => fim(false);
    abrirModal('modal-confirm');
    setTimeout(() => $('confirm-sim').focus(), 60);
  });
}

// Pílulas de filtro (Ativos/Desativados/Todos...) — mesmo comportamento das segmentadas.
document.querySelectorAll('.pills').forEach(g => g.addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b || b.classList.contains('on')) return;
  g.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
  g.dispatchEvent(new Event('change'));
}));
// Valor de um filtro, seja <select>/<input> ou grupo de pílulas.
function fv(id) { const el = $(id); if ('value' in el && el.tagName !== 'DIV') return el.value; const b = el.querySelector('button.on'); return b ? b.dataset.v : ''; }
function fset(id, v) { const el = $(id); if (el.tagName !== 'DIV') el.value = v; else el.querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.v === String(v))); }

// Iniciais coloridas (avatar das linhas)
const TONS = [['#E8EAF6', '#3A3D78'], ['#F6EEDC', '#8A6A2E'], ['#E3F1EA', '#25704A'], ['#F5E6EC', '#8B3A5A'], ['#E4EEF5', '#2F5E7E'], ['#EFE9F6', '#5B4690']];
function iniciais(nome) {
  const p = String(nome || '?').trim().split(/\s+/).filter(w => w.length > 2 || /^[A-ZÀ-Ú]/.test(w));
  return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
}
function avatar(nome) {
  let h = 0; for (const c of String(nome)) h = (h * 31 + c.charCodeAt(0)) >>> 0;
  const [bg, fg] = TONS[h % TONS.length];
  return `<span class="av" style="background:${bg};color:${fg}">${esc(iniciais(nome))}</span>`;
}
function linkWhats(v) {
  const d = soDigitos(v); if (!d) return '<span class="dim">—</span>';
  return `<a class="wa" href="https://wa.me/55${d}" target="_blank" rel="noopener" title="Abrir conversa no WhatsApp">${esc(maskTel(v))}</a>`;
}
const pill = (ok, sim = 'Ativo', nao = 'Desativado') => `<span class="st ${ok ? 'on' : 'off'}">${ok ? sim : nao}</span>`;

// Botões segmentados (Receita/Despesa, Ativo/Desativado...)
function segValor(id) { const b = $(id).querySelector('button.on'); return b ? (b.dataset.v ?? b.dataset.tipo) : null; }
function segSet(id, v) { $(id).querySelectorAll('button').forEach(b => b.classList.toggle('on', (b.dataset.v ?? b.dataset.tipo) === String(v))); }
document.querySelectorAll('.seg').forEach(seg => seg.addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  segSet(seg.id, b.dataset.v ?? b.dataset.tipo);
  seg.dispatchEvent(new Event('change'));
}));

// ─── MENU DOS 3 PONTINHOS ─────────────────────────────────────────────────────
const ICO = {
  Contrato: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
  Editar: '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
  Apagar: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
  Visualizar: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  'Dar baixa': '<path d="M20 6 9 17l-5-5"/>', Reabrir: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
  Ativar: '<path d="M20 6 9 17l-5-5"/>', Desativar: '<circle cx="12" cy="12" r="9"/><path d="M5.7 5.7l12.6 12.6"/>',
};
const menuAcoes = {};  // id da linha → lista de ações (registrado no render)
let menuBtnAtual = null;
function kebab(chave) { return `<button class="kebab" type="button" data-menu="${esc(chave)}" aria-label="Ações"><i></i><i></i><i></i></button>`; }
function abrirMenu(btn) {
  const itens = (menuAcoes[btn.dataset.menu] || []).filter(Boolean);
  if (!itens.length) return;
  const menu = $('row-menu');
  if (menuBtnAtual === btn && !menu.classList.contains('hidden')) return fecharMenu();
  fecharMenu();
  menu.innerHTML = itens.map((it, i) => `${it.danger && i ? '<hr/>' : ''}<button type="button" data-i="${i}" class="${it.danger ? 'danger' : ''}" ${it.disabled ? 'disabled' : ''}><svg viewBox="0 0 24 24">${ICO[it.label] || ''}</svg>${esc(it.label)}</button>`).join('');
  menu.querySelectorAll('button').forEach(b => b.onclick = () => { const it = itens[b.dataset.i]; fecharMenu(); it.fn(); });
  menu.classList.remove('hidden');
  const r = btn.getBoundingClientRect(), mw = menu.offsetWidth, mh = menu.offsetHeight;
  let left = r.right - mw, top = r.bottom + 4;
  if (left < 8) left = 8;
  if (top + mh > window.innerHeight - 8) top = Math.max(8, r.top - mh - 4);
  menu.style.left = left + 'px'; menu.style.top = top + 'px';
  btn.classList.add('open'); menuBtnAtual = btn;
}
function fecharMenu() { $('row-menu').classList.add('hidden'); if (menuBtnAtual) menuBtnAtual.classList.remove('open'); menuBtnAtual = null; }
document.addEventListener('click', e => {
  const k = e.target.closest('.kebab');
  if (k) { e.stopPropagation(); abrirMenu(k); return; }
  if (!e.target.closest('#row-menu')) fecharMenu();
});
window.addEventListener('scroll', fecharMenu, true);
window.addEventListener('resize', fecharMenu);

// ─── PAGINAÇÃO ────────────────────────────────────────────────────────────────
function paginar(lista, pagina) {
  const total = Math.max(1, Math.ceil(lista.length / POR_PAGINA));
  const p = Math.min(Math.max(1, pagina), total);
  return { itens: lista.slice((p - 1) * POR_PAGINA, p * POR_PAGINA), pagina: p, total, n: lista.length };
}
function renderPager(elId, pagina, total, onChange, n = 0) {
  const el = $(elId);
  const ini = n ? (pagina - 1) * POR_PAGINA + 1 : 0, fim = Math.min(pagina * POR_PAGINA, n);
  const info = n ? `<span class="pg-info">Mostrando ${ini}–${fim} de ${n}</span>` : '';
  if (total <= 1) { el.innerHTML = info; el.classList.toggle('hidden', !n); return; }
  el.classList.remove('hidden');
  const nums = new Set([1, total, pagina - 1, pagina, pagina + 1].filter(n => n >= 1 && n <= total));
  const ordem = [...nums].sort((a, b) => a - b);
  let html = `<button type="button" data-p="${pagina - 1}" class="nav" ${pagina === 1 ? 'disabled' : ''} aria-label="Anterior">‹</button>`, ant = 0;
  ordem.forEach(k => { if (k - ant > 1) html += '<span class="gap">…</span>'; html += `<button type="button" data-p="${k}" class="${k === pagina ? 'on' : ''}" aria-label="Página ${k}">${k}</button>`; ant = k; });
  html += `<button type="button" data-p="${pagina + 1}" class="nav" ${pagina === total ? 'disabled' : ''} aria-label="Próxima">›</button>`;
  el.innerHTML = info + `<div class="pg">${html}</div>`;
  el.querySelectorAll('button[data-p]').forEach(b => b.onclick = () => onChange(Number(b.dataset.p)));
}

// ─── ESTADO ───────────────────────────────────────────────────────────────────
const state = { user: null, cad: {}, page: null };
const ehAdmin = () => state.user && state.user.role === 'admin';
const pode = perm => ehAdmin() || (state.user && (state.user.permissoes || []).includes(perm));

// ─── LOGIN ────────────────────────────────────────────────────────────────────
async function iniciar() {
  const u = await api('auth/me');
  if (u && u.id) { state.user = u; entrarNoApp(); } else mostrarLogin();
}
async function mostrarLogin() {
  state.user = null;
  $('app').classList.add('hidden');
  $('auth-screen').classList.remove('hidden');
  const st = await api('auth/setup-status');
  $('login-form').classList.toggle('hidden', !!st.needsSetup);
  $('setup-form').classList.toggle('hidden', !st.needsSetup);
  setTimeout(() => (st.needsSetup ? $('setup-user') : $('login-user')).focus(), 50);
}
$('login-form').addEventListener('submit', async e => {
  e.preventDefault();
  $('login-error').textContent = '';
  const r = await api('auth/login', 'POST', { username: $('login-user').value.trim(), password: $('login-pass').value });
  if (r.error) { $('login-error').textContent = r.error; return; }
  $('login-pass').value = '';
  state.user = r; entrarNoApp();
});
$('setup-form').addEventListener('submit', async e => {
  e.preventDefault();
  $('setup-error').textContent = '';
  const r = await api('auth/register', 'POST', { username: $('setup-user').value.trim(), password: $('setup-pass').value });
  if (r.error) { $('setup-error').textContent = r.error; return; }
  state.user = r; entrarNoApp();
});

function renderAvatar(el, u) {
  if (u && u.foto_path) el.innerHTML = `<img src="${esc(u.foto_path)}" alt=""/>`;
  else el.textContent = ((u && (u.nome || u.username)) || '?').trim()[0].toUpperCase();
}

async function entrarNoApp() {
  $('auth-screen').classList.add('hidden');
  $('app').classList.remove('hidden');
  renderAvatar($('user-avatar'), state.user);
  // Menu: só aparece o que a pessoa pode ver
  document.querySelectorAll('.side-btn').forEach(a => a.classList.toggle('hidden', !podeVerPagina(a.dataset.page)));
  await carregarCadastros();
  irPara(location.hash.slice(1));
}

// ─── NAVEGAÇÃO ────────────────────────────────────────────────────────────────
const PAGINAS = {
  lancamentos:  { perm: 'lancamentos',      load: () => carregarLancamentos() },
  parceiros:    { perm: 'cad-parceiros',    load: () => ENT.parceiros.carregar() },
  fornecedores: { perm: 'cad-fornecedores', load: () => ENT.fornecedores.carregar() },
  categorias:   { perm: 'cad-categorias',   load: () => carregarCategorias() },
  usuarios:     { admin: true,              load: () => carregarUsuarios() },
};
function podeVerPagina(p) { const c = PAGINAS[p]; return !!c && (c.admin ? ehAdmin() : pode(c.perm)); }
function primeiraPagina() { return Object.keys(PAGINAS).find(podeVerPagina) || 'vazio'; }

function irPara(page) {
  if (!podeVerPagina(page)) page = primeiraPagina();
  state.page = page;
  if (location.hash.slice(1) !== page && page !== 'vazio') history.replaceState(null, '', '#' + page);
  document.querySelectorAll('.side-btn').forEach(a => a.classList.toggle('active', a.dataset.page === page));
  document.querySelectorAll('.page').forEach(s => s.classList.toggle('active', s.id === 'page-' + page));
  fecharMenu();
  if (PAGINAS[page]) PAGINAS[page].load();
}
window.addEventListener('hashchange', () => { if (state.user) irPara(location.hash.slice(1)); });

async function carregarCadastros() {
  const c = await api('cadastros');
  state.cad = c && !c.error ? c : {};
  // Filtro de categorias dos lançamentos
  const sel = $('tx-f-categoria'), atual = sel.value;
  sel.innerHTML = '<option value="">Todas as categorias</option>' + (state.cad.categorias || []).map(c => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
  sel.value = atual;
}

// ═══════════════════════════ LANÇAMENTOS ═══════════════════════════
const tx = { lista: [], pagina: 1, editId: null };
const CENTROS_RECEITA = ['RECEITA VENDAS', 'RECEITA OPERACIONAL', 'RECEITA COMUM'];

(function montarFiltrosPeriodo() {
  const ano = new Date().getFullYear();
  $('tx-f-mes').innerHTML = '<option value="">Todos os meses</option>' + MESES.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
  $('tx-f-ano').innerHTML = '<option value="">Todos os anos</option>' + [ano + 1, ano, ano - 1, ano - 2, ano - 3].map(a => `<option value="${a}" ${a === ano ? 'selected' : ''}>${a}</option>`).join('');
})();

async function carregarLancamentos() {
  const qs = new URLSearchParams();
  [['year', 'tx-f-ano'], ['month', 'tx-f-mes'], ['type', 'tx-f-tipo'], ['status', 'tx-f-status'], ['categoria_id', 'tx-f-categoria']]
    .forEach(([k, id]) => { if (fv(id)) qs.set(k, fv(id)); });
  const r = await api('transactions?' + qs);
  tx.lista = Array.isArray(r) ? r : [];
  if (r.error) toast(r.error, 'err');
  renderLancamentos();
}
function txFiltrados() {
  const t = norm($('tx-f-busca').value.trim());
  return t ? tx.lista.filter(x => [x.descricao, x.cliente_fornecedor, x.categoria_nome, x.observacoes].some(v => norm(v).includes(t))) : tx.lista;
}
function renderLancamentos() {
  const lista = txFiltrados();
  // KPIs do que está filtrado
  let rec = 0, desp = 0, aberto = 0;
  lista.forEach(x => { if (x.tipo === 'income') rec += x.valor; else desp += x.valor; if (x.status === 'em_aberto') aberto += x.valor; });
  $('kpi-rec').textContent = fmtBRL(rec); $('kpi-desp').textContent = fmtBRL(desp); $('kpi-aberto').textContent = fmtBRL(aberto);
  $('kpi-saldo').textContent = (rec - desp < 0 ? '− ' : '') + fmtBRL(Math.abs(rec - desp));

  const { itens, pagina, total, n } = paginar(lista, tx.pagina); tx.pagina = pagina;
  const tbody = document.querySelector('#tx-table tbody');
  const hoje = hojeISO();
  const cor = id => ((state.cad.categorias || []).find(c => c.id === id) || {}).cor || '#C9CAD6';
  tbody.innerHTML = itens.length ? itens.map(x => {
    const vencido = x.status === 'em_aberto' && x.data_vencimento && x.data_vencimento < hoje;
    menuAcoes['tx' + x.id] = [
      { label: 'Visualizar', fn: () => verLancamento(x) },
      { label: 'Editar', fn: () => abrirLancamento(x) },
      x.status === 'em_aberto' ? { label: 'Dar baixa', fn: () => baixarLancamento(x, true) } : { label: 'Reabrir', fn: () => baixarLancamento(x, false) },
      { label: 'Apagar', danger: true, fn: () => apagarLancamento(x) },
    ];
    return `<tr>
      <td class="nowrap num">${fmtData(x.data)}</td>
      <td class="wrap"><span class="ttl">${esc(x.descricao) || '<span class="dim">Sem descrição</span>'}</span></td>
      <td class="nowrap">${x.categoria_nome ? `<span class="catg"><i style="background:${esc(cor(x.categoria_id))}"></i>${esc(x.categoria_nome)}</span>` : '<span class="dim">—</span>'}</td>
      <td class="wrap">${esc(x.cliente_fornecedor) || '<span class="dim">—</span>'}</td>
      <td class="money r ${x.tipo === 'income' ? 'pos' : 'neg'}">${x.tipo === 'income' ? '+ ' : '− '}${fmtBRL(x.valor)}</td>
      <td class="nowrap num ${vencido ? 'neg' : ''}">${x.data_vencimento ? fmtData(x.data_vencimento) : '<span class="dim">—</span>'}</td>
      <td>${x.status === 'quitado' ? '<span class="st on">Quitado</span>' : vencido ? '<span class="st late">Vencido</span>' : '<span class="st open">Em aberto</span>'}</td>
      <td class="r">${kebab('tx' + x.id)}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="8" class="empty"><strong>Nenhum lançamento aqui</strong>Ajuste os filtros ou registre um novo lançamento.</td></tr>`;
  renderPager('tx-pager', pagina, total, p => { tx.pagina = p; renderLancamentos(); }, n);
}
$('tx-toolbar').addEventListener('submit', e => { e.preventDefault(); tx.pagina = 1; carregarLancamentos(); });
['tx-f-tipo', 'tx-f-status', 'tx-f-categoria', 'tx-f-mes', 'tx-f-ano'].forEach(id => $(id).addEventListener('change', () => { tx.pagina = 1; carregarLancamentos(); }));
$('tx-f-busca').addEventListener('input', () => { tx.pagina = 1; renderLancamentos(); });

// Modal de lançamento
function opts(lista, atual, vazio = '—') {
  const arr = [...lista];
  if (atual && !arr.some(o => (o.v ?? o) === atual)) arr.push({ v: atual, l: atual });
  return `<option value="">${vazio}</option>` + arr.map(o => { const v = o.v ?? o, l = o.l ?? o; return `<option value="${esc(v)}" ${v === atual ? 'selected' : ''}>${esc(l)}</option>`; }).join('');
}
function preencherSelectsTx(x) {
  const tipo = segValor('tx-tipo-seg');
  const c = state.cad;
  // categorias do tipo (ativas) + a atual, mesmo se desativada
  let cats = (c.categorias || []).filter(k => k.tipo === 'ambos' || k.tipo === tipo).map(k => ({ v: String(k.id), l: k.nome }));
  const catAtual = x && x.categoria_id ? String(x.categoria_id) : $('tx-categoria').value;
  if (x && x.categoria_id && !cats.some(k => k.v === catAtual)) cats.push({ v: catAtual, l: x.categoria_nome + ' (desativada)' });
  $('tx-categoria').innerHTML = opts(cats, cats.some(k => k.v === catAtual) ? catAtual : '', 'Sem categoria');

  const pessoas = tipo === 'expense' ? (c.fornecedores || []) : [...new Set([...(c.clientes || []), ...(c.fornecedores || [])])].sort();
  $('tx-pessoa-label').textContent = tipo === 'expense' ? 'Fornecedor' : 'Cliente';
  $('tx-pessoa').innerHTML = opts(pessoas, x ? x.cliente_fornecedor : $('tx-pessoa').value);
  $('tx-forma').innerHTML = opts(c.formasPagamento || [], x ? x.forma_pagamento_nome : $('tx-forma').value);
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
$('tx-tipo-seg').addEventListener('change', () => preencherSelectsTx(null));
$('tx-forma').addEventListener('change', calcTaxa);
$('tx-valor').addEventListener('input', calcTaxa);
$('tx-status').addEventListener('change', () => { if ($('tx-status').value === 'quitado' && !$('tx-pag').value) $('tx-pag').value = $('tx-data').value || hojeISO(); });

function abrirLancamento(x = null) {
  tx.editId = x ? x.id : null;
  $('tx-modal-title').textContent = x ? 'Editar Lançamento' : 'Novo Lançamento';
  $('tx-error').textContent = '';
  ['tx-categoria', 'tx-pessoa', 'tx-forma', 'tx-caixa', 'tx-centro', 'tx-conta'].forEach(id => $(id).innerHTML = '');
  segSet('tx-tipo-seg', x ? x.tipo : (fv('tx-f-tipo') || 'expense'));
  $('tx-data').value = x ? x.data : hojeISO();
  $('tx-valor').value = x ? x.valor : '';
  $('tx-descricao').value = x ? (x.descricao || '') : '';
  $('tx-status').value = x ? x.status : 'quitado';
  $('tx-venc').value = x ? (x.data_vencimento || '') : '';
  $('tx-pag').value = x ? (x.data_pagamento || '') : '';
  $('tx-obs').value = x ? (x.observacoes || '') : '';
  preencherSelectsTx(x);
  if (!x && $('tx-f-categoria').value) { $('tx-categoria').value = $('tx-f-categoria').value; }
  abrirModal('modal-tx');
}
$('btn-novo-tx').addEventListener('click', () => abrirLancamento());
$('tx-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-tx').click(); });
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
  if (!body.date || !(body.amount > 0)) { $('tx-error').textContent = 'Informe a data e um valor maior que zero.'; return; }
  const btn = $('btn-salvar-tx'); btn.disabled = true;
  const r = tx.editId ? await api('transactions/' + tx.editId, 'PUT', body) : await api('transactions', 'POST', body);
  btn.disabled = false;
  if (r.error) { $('tx-error').textContent = r.error; return; }
  fecharModal('modal-tx');
  toast(tx.editId ? 'Lançamento atualizado' : 'Lançamento criado');
  carregarLancamentos();
});
async function baixarLancamento(x, quitar) {
  const r = await api('transactions/' + x.id, 'PATCH', quitar ? { status: 'quitado', data_pagamento: hojeISO() } : { status: 'em_aberto', data_pagamento: null });
  if (r.error) return toast(r.error, 'err');
  toast(quitar ? 'Baixa registrada' : 'Lançamento reaberto');
  carregarLancamentos();
}
async function apagarLancamento(x) {
  if (!await confirmar(`Apagar o lançamento "${x.descricao || fmtBRL(x.valor)}" de ${fmtData(x.data)}?`, { ok: 'Apagar' })) return;
  const r = await api('transactions/' + x.id, 'DELETE');
  if (r.error) return toast(r.error, 'err');
  toast('Lançamento apagado'); carregarLancamentos();
}
function verLancamento(x) {
  const linhas = [
    ['Tipo', x.tipo === 'income' ? 'Receita' : 'Despesa'], ['Data', fmtData(x.data)], ['Valor', fmtBRL(x.valor)],
    x.taxa_mdr ? ['Taxa / Valor líquido', `${fmtBRL(x.taxa_mdr)} · ${fmtBRL(x.valor_liquido)}`] : null,
    ['Categoria', x.categoria_nome], ['Descrição', x.descricao], [x.tipo === 'income' ? 'Cliente' : 'Fornecedor', x.cliente_fornecedor],
    ['Forma de pagamento', x.forma_pagamento_nome], ['Caixa / Banco', x.caixa_nome], ['Centro de custo', x.centro_custo_nome],
    ['Grupo / Conta', [x.grupo, x.conta_nome].filter(Boolean).join(' — ')],
    ['Situação', x.status === 'quitado' ? 'Quitado' : 'Em aberto'], ['Vencimento', fmtData(x.data_vencimento)], ['Pagamento', fmtData(x.data_pagamento)],
    ['Observações', x.observacoes],
  ];
  mostrarDetalhes(x.descricao || 'Lançamento', `${x.tipo === 'income' ? 'Receita' : 'Despesa'} · ${fmtBRL(x.valor)}`, linhas,
    [{ label: 'Editar', cls: 'btn-navy', fn: () => { fecharModal('modal-view'); abrirLancamento(x); } }]);
}

function mostrarDetalhes(titulo, sub, linhas, botoes = []) {
  $('view-title').textContent = titulo;
  $('view-sub').textContent = sub || '';
  $('view-body').innerHTML = linhas.filter(Boolean).map(([k, v, html]) => `<dt>${esc(k)}</dt><dd>${html ? v : (esc(v) || '—')}</dd>`).join('');
  $('view-foot').innerHTML = '<button class="btn btn-ghost" data-close>Fechar</button>';
  botoes.forEach(b => { const el = document.createElement('button'); el.className = 'btn ' + (b.cls || 'btn-navy'); el.textContent = b.label; el.onclick = b.fn; $('view-foot').appendChild(el); });
  $('view-foot').querySelector('[data-close]').onclick = () => fecharModal('modal-view');
  abrirModal('modal-view');
}

// ═══════════════════ PARCEIROS E FORNECEDORES (mesma tela) ═══════════════════
function maskCNPJ(v) {
  v = soDigitos(v).slice(0, 14);
  if (v.length <= 2) return v;
  if (v.length <= 5) return `${v.slice(0, 2)}.${v.slice(2)}`;
  if (v.length <= 8) return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5)}`;
  if (v.length <= 12) return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}/${v.slice(8)}`;
  return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}/${v.slice(8, 12)}-${v.slice(12)}`;
}
function maskCPF(v) {
  v = soDigitos(v).slice(0, 11);
  if (v.length <= 3) return v;
  if (v.length <= 6) return `${v.slice(0, 3)}.${v.slice(3)}`;
  if (v.length <= 9) return `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6)}`;
  return `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6, 9)}-${v.slice(9)}`;
}
function maskTel(v) {
  v = soDigitos(v).slice(0, 11);
  if (!v) return '';
  if (v.length <= 2) return `(${v}`;
  if (v.length <= 6) return `(${v.slice(0, 2)}) ${v.slice(2)}`;
  if (v.length <= 10) return `(${v.slice(0, 2)}) ${v.slice(2, 6)}-${v.slice(6)}`;
  return `(${v.slice(0, 2)}) ${v.slice(2, 7)}-${v.slice(7)}`;
}
function cnpjValido(v) {
  const c = soDigitos(v);
  if (c.length !== 14 || /^(\d)\1{13}$/.test(c)) return false;
  const calc = (b, p) => { const s = p.reduce((a, x, i) => a + Number(b[i]) * x, 0) % 11; return s < 2 ? 0 : 11 - s; };
  const d1 = calc(c, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]), d2 = calc(c.slice(0, 12) + d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
  return Number(c[12]) === d1 && Number(c[13]) === d2;
}
function cpfValido(v) {
  const c = soDigitos(v);
  if (c.length !== 11 || /^(\d)\1{10}$/.test(c)) return false;
  for (let t = 9; t < 11; t++) { let s = 0; for (let i = 0; i < t; i++) s += Number(c[i]) * (t + 1 - i); if (Number(c[t]) !== ((10 * s) % 11) % 10) return false; }
  return true;
}

// Configuração das duas telas — o resto do código é o mesmo pra ambas.
const ENT = {
  parceiros: {
    api: 'parceiros', nome: 'Parceiro', artigo: 'o', docTipo: 'contrato_parceiro', docKey: 'parceiro_id',
    docCampo: 'cnpj', docLabel: 'CNPJ', aceitaCPF: false, contatoCampo: 'contato', comissao: true,
    obrigatorios: { nome: 'Nome', razao: 'Razão Social', doc: 'CNPJ', resp: 'Responsável', contato: 'Contato' },
    lista: [], pagina: 1, prefixo: 'parc',
  },
  fornecedores: {
    api: 'fornecedores', nome: 'Fornecedor', artigo: 'o', docTipo: 'contrato_fornecedor', docKey: 'fornecedor_id',
    docCampo: 'cnpj_cpf', docLabel: 'CNPJ / CPF', aceitaCPF: true, contatoCampo: 'telefone', comissao: false,
    obrigatorios: { nome: 'Nome', doc: 'CNPJ/CPF' },
    lista: [], pagina: 1, prefixo: 'forn',
  },
};
Object.values(ENT).forEach(cfg => {
  cfg.carregar = async () => {
    const r = await api(cfg.api);
    cfg.lista = Array.isArray(r) ? r : [];
    if (r.error) toast(r.error, 'err');
    cfg.render();
  };
  cfg.render = () => renderEntidades(cfg);
  $(`${cfg.prefixo}-toolbar`).addEventListener('submit', e => { e.preventDefault(); cfg.pagina = 1; cfg.carregar(); });
  $(`${cfg.prefixo}-f-sit`).addEventListener('change', () => { cfg.pagina = 1; cfg.render(); });
  $(`${cfg.prefixo}-f-busca`).addEventListener('input', () => { cfg.pagina = 1; cfg.render(); });
});
$('btn-novo-parceiro').addEventListener('click', () => abrirEntidade(ENT.parceiros));
$('btn-novo-fornecedor').addEventListener('click', () => abrirEntidade(ENT.fornecedores));

function renderEntidades(cfg) {
  const sit = fv(`${cfg.prefixo}-f-sit`);
  const t = norm($(`${cfg.prefixo}-f-busca`).value.trim()), td = soDigitos(t);
  const lista = cfg.lista.filter(x => (sit === '' || String(x.ativo) === sit) &&
    (!t || [x.nome, x.razao_social, x.responsavel].some(v => norm(v).includes(t)) || (td && soDigitos(x[cfg.docCampo]).includes(td))));
  const { itens, pagina, total, n } = paginar(lista, cfg.pagina); cfg.pagina = pagina;
  const tbody = document.querySelector(`#${cfg.prefixo}-table tbody`);
  tbody.innerHTML = itens.length ? itens.map(x => {
    menuAcoes[cfg.prefixo + x.id] = [
      { label: 'Contrato', fn: () => abrirContratos(cfg, x) },
      { label: 'Editar', fn: () => abrirEntidade(cfg, x) },
      { label: 'Apagar', danger: true, fn: () => apagarEntidade(cfg, x) },
      { label: 'Visualizar', fn: () => verEntidade(cfg, x) },
    ];
    return `<tr>
      <td><div class="who">${avatar(x.nome)}<span class="ttl">${esc(x.nome)}</span>${x.contrato_path ? '<svg class="clip" viewBox="0 0 24 24" aria-label="Tem contrato"><title>Contrato anexado</title><path d="m21 11-8.6 8.6a5 5 0 0 1-7-7l8.5-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.3-2.3l7.9-8"/></svg>' : ''}</div></td>
      <td class="wrap">${esc(x.razao_social) || '<span class="dim">—</span>'}</td>
      <td class="nowrap num">${esc(x[cfg.docCampo]) || '<span class="dim">—</span>'}</td>
      <td class="wrap">${esc(x.responsavel) || '<span class="dim">—</span>'}</td>
      <td class="nowrap">${linkWhats(x.whatsapp)}</td>
      <td>${pill(x.ativo)}</td>
      <td class="r">${kebab(cfg.prefixo + x.id)}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="7" class="empty">${cfg.lista.length ? '<strong>Nada encontrado</strong>Tente outro termo ou troque o filtro de situação.' : `<strong>Nenhum ${cfg.nome.toLowerCase()} cadastrado</strong>Clique em “Novo ${cfg.nome.toLowerCase()}” para começar.`}</td></tr>`;
  renderPager(`${cfg.prefixo}-pager`, pagina, total, p => { cfg.pagina = p; cfg.render(); }, n);
  $(`${cfg.prefixo}-count`).textContent = n === 1 ? '1 resultado' : `${n} resultados`;
}

// Formulário compartilhado
let entAtual = { cfg: null, item: null };
function abrirEntidade(cfg, x = null) {
  entAtual = { cfg, item: x };
  const ob = cfg.obrigatorios;
  $('ent-modal-title').textContent = (x ? 'Editar ' : 'Novo ') + cfg.nome;
  $('ent-razao-label').textContent = 'Razão Social' + (ob.razao ? ' *' : '');
  $('ent-doc-label').textContent = cfg.docLabel + ' *';
  $('ent-resp-label').textContent = 'Responsável' + (ob.resp ? ' *' : '');
  $('ent-contato-label').textContent = (cfg.contatoCampo === 'telefone' ? 'Telefone' : 'Contato') + (ob.contato ? ' *' : '');
  $('ent-doc').placeholder = cfg.aceitaCPF ? 'CNPJ ou CPF' : '00.000.000/0000-00';
  $('ent-comissao-wrap').classList.toggle('hidden', !cfg.comissao);
  $('ent-error').textContent = '';
  $('ent-nome').value = x ? x.nome || '' : '';
  $('ent-razao').value = x ? x.razao_social || '' : '';
  $('ent-doc').value = x ? mascaraDoc(cfg, x[cfg.docCampo] || '') : '';
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
function mascaraDoc(cfg, v) { return cfg.aceitaCPF && soDigitos(v).length <= 11 ? maskCPF(v) : maskCNPJ(v); }
function docValido(cfg, v) { const n = soDigitos(v).length; return n === 14 ? cnpjValido(v) : (cfg.aceitaCPF && n === 11 && cpfValido(v)); }
function hintDoc() {
  const cfg = entAtual.cfg, v = $('ent-doc').value, n = soDigitos(v).length, h = $('ent-doc-hint');
  $('btn-buscar-cnpj').disabled = n !== 14;
  if (!n) { h.textContent = ''; h.className = 'hint'; return; }
  if (n < 11 || (n > 11 && n < 14) || (n === 11 && !cfg.aceitaCPF)) { h.textContent = `${n} dígitos`; h.className = 'hint'; return; }
  const ok = docValido(cfg, v);
  h.textContent = ok ? `✓ ${n === 11 ? 'CPF' : 'CNPJ'} válido` : `✕ ${n === 11 ? 'CPF' : 'CNPJ'} inválido`;
  h.className = 'hint ' + (ok ? 'ok' : 'err');
}
$('ent-doc').addEventListener('input', e => { e.target.value = mascaraDoc(entAtual.cfg, e.target.value); hintDoc(); });
['ent-contato', 'ent-whats'].forEach(id => $(id).addEventListener('input', e => { e.target.value = maskTel(e.target.value); }));
$('ent-contrato').addEventListener('change', () => { const f = $('ent-contrato').files[0]; $('ent-contrato-nome').textContent = f ? f.name : 'Nenhum arquivo'; });

// Preenche com dados públicos da Receita (BrasilAPI). Só completa campos vazios.
$('btn-buscar-cnpj').addEventListener('click', async () => {
  const dig = soDigitos($('ent-doc').value), btn = $('btn-buscar-cnpj'), h = $('ent-doc-hint');
  if (!cnpjValido(dig)) return hintDoc();
  btn.disabled = true; btn.textContent = '...';
  try {
    const r = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${dig}`);
    if (!r.ok) throw new Error(r.status === 404 ? 'CNPJ não encontrado na Receita' : 'Consulta indisponível agora');
    const d = await r.json();
    const set = (id, v) => { if (v && !$(id).value.trim()) $(id).value = v; };
    set('ent-razao', d.razao_social); set('ent-nome', d.nome_fantasia || d.razao_social); set('ent-email', (d.email || '').toLowerCase());
    const end = [[d.descricao_tipo_de_logradouro, d.logradouro].filter(Boolean).join(' '), d.numero, d.bairro, d.municipio && `${d.municipio}/${d.uf}`, d.cep && `CEP ${d.cep}`].filter(Boolean).join(', ');
    set('ent-endereco', end);
    if (d.ddd_telefone_1) set('ent-contato', maskTel(d.ddd_telefone_1));
    h.textContent = `✓ ${d.descricao_situacao_cadastral || 'Encontrado'} na Receita`; h.className = 'hint ' + ((d.descricao_situacao_cadastral || 'ATIVA') === 'ATIVA' ? 'ok' : 'err');
  } catch (e) { h.textContent = e.message || 'Consulta indisponível agora'; h.className = 'hint err'; }
  finally { btn.textContent = 'Buscar'; btn.disabled = false; }
});

$('ent-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-ent').click(); });
$('btn-salvar-ent').addEventListener('click', async () => {
  const { cfg, item } = entAtual, ob = cfg.obrigatorios;
  const val = { nome: $('ent-nome').value.trim(), razao: $('ent-razao').value.trim(), doc: $('ent-doc').value.trim(), resp: $('ent-resp').value.trim(), contato: $('ent-contato').value.trim() };
  const faltando = Object.keys(ob).filter(k => !val[k]).map(k => ob[k]);
  if (faltando.length) { $('ent-error').textContent = 'Preencha: ' + faltando.join(', ') + '.'; return; }
  if (!docValido(cfg, val.doc)) { $('ent-error').textContent = `${cfg.docLabel} inválido — confira os números.`; return; }
  const fd = new FormData();
  fd.append('nome', val.nome); fd.append('razao_social', val.razao); fd.append(cfg.docCampo, val.doc);
  fd.append('email', $('ent-email').value.trim()); fd.append('responsavel', val.resp); fd.append(cfg.contatoCampo, val.contato);
  fd.append('whatsapp', $('ent-whats').value.trim()); fd.append('endereco', $('ent-endereco').value.trim());
  fd.append('observacoes', $('ent-obs').value.trim()); fd.append('ativo', segValor('ent-sit-seg'));
  if (cfg.comissao) fd.append('comissao', Math.max(0, parseFloat($('ent-comissao').value) || 0));
  const arq = $('ent-contrato').files[0]; if (arq) fd.append('contrato', arq);
  const btn = $('btn-salvar-ent'); btn.disabled = true;
  const r = await api(item ? `${cfg.api}/${item.id}` : cfg.api, 'POST', fd);
  btn.disabled = false;
  if (r.error) { $('ent-error').textContent = r.error; return; }
  fecharModal('modal-ent');
  toast(`${cfg.nome} ${item ? 'atualizado' : 'cadastrado'}`);
  cfg.carregar();
  if (cfg === ENT.fornecedores) carregarCadastros();
});

async function apagarEntidade(cfg, x) {
  if (!await confirmar(`Apagar ${cfg.artigo} ${cfg.nome.toLowerCase()} "${x.nome}"? O contrato e os aditivos anexados também serão apagados.${x.ativo ? '\n\nSe só quiser tirar da lista, edite e marque como Desativado.' : ''}`, { ok: 'Apagar' })) return;
  const r = await api(`${cfg.api}/${x.id}`, 'DELETE');
  if (r.error) return toast(r.error, 'err');
  toast(`${cfg.nome} apagado`); cfg.carregar();
}

function verEntidade(cfg, x) {
  const contrato = x.contrato_path ? `<a href="${esc(x.contrato_path)}" target="_blank" rel="noopener" class="link">${esc(x.contrato_nome || 'Abrir contrato')}</a>` : 'Sem contrato anexado';
  mostrarDetalhes(x.nome, x.razao_social || '', [
    [cfg.docLabel, x[cfg.docCampo]], ['Responsável', x.responsavel], [cfg.contatoCampo === 'telefone' ? 'Telefone' : 'Contato', x[cfg.contatoCampo]],
    ['WhatsApp', x.whatsapp], ['E-mail', x.email], ['Endereço', x.endereco],
    cfg.comissao ? ['Comissão', x.comissao ? fmtBRL(x.comissao) : '—'] : null,
    ['Situação', x.ativo ? 'Ativo' : 'Desativado'], ['Contrato', contrato, true],
    ['Aditivos', x.qtd_contratos ? `${x.qtd_contratos} arquivo(s)` : 'Nenhum'], ['Observações', x.observacoes],
    ['Cadastrado em', fmtData(x.created_at)],
  ], [
    { label: 'Contrato', cls: 'btn-gold', fn: () => { fecharModal('modal-view'); abrirContratos(cfg, x); } },
    { label: 'Editar', fn: () => { fecharModal('modal-view'); abrirEntidade(cfg, x); } },
  ]);
}

// ─── Contratos (principal + aditivos) ──────────────────────────────────────────
let contratoAtual = { cfg: null, item: null };
async function abrirContratos(cfg, x) {
  contratoAtual = { cfg, item: x };
  $('contrato-sub').textContent = `${x.nome}${x[cfg.docCampo] ? ' · ' + x[cfg.docCampo] : ''}`;
  $('contrato-error').textContent = ''; $('contrato-desc').value = '';
  renderContratoPrincipal();
  abrirModal('modal-contrato');
  carregarAditivos();
}
function renderContratoPrincipal() {
  const x = contratoAtual.item;
  $('contrato-principal').innerHTML = x.contrato_path
    ? `<div class="doc"><div style="min-width:0"><div class="nm">📄 ${esc(x.contrato_nome || 'Contrato')}</div><div class="meta">Contrato principal</div></div>
        <div class="acts"><a class="btn btn-ghost sm" href="${esc(x.contrato_path)}" target="_blank" rel="noopener">Abrir</a>
        <button class="btn btn-ghost sm" type="button" id="btn-contrato-trocar">Substituir</button>
        <button class="btn btn-ghost sm danger" type="button" id="btn-contrato-remover">Remover</button></div></div>`
    : `<div class="doc-empty">Nenhum contrato anexado ainda.<button class="btn btn-gold sm" type="button" id="btn-contrato-trocar">Anexar contrato</button></div>`;
  $('btn-contrato-trocar').onclick = () => $('contrato-main-file').click();
  if ($('btn-contrato-remover')) $('btn-contrato-remover').onclick = removerContratoPrincipal;
}
$('contrato-main-file').addEventListener('change', async () => {
  const f = $('contrato-main-file').files[0]; if (!f) return;
  const { cfg, item } = contratoAtual, fd = new FormData(); fd.append('contrato', f);
  const r = await api(`${cfg.api}/${item.id}/contrato`, 'POST', fd);
  $('contrato-main-file').value = '';
  if (r.error) { $('contrato-error').textContent = r.error; return; }
  Object.assign(item, r); renderContratoPrincipal(); toast('Contrato anexado'); cfg.carregar();
});
async function removerContratoPrincipal() {
  const { cfg, item } = contratoAtual;
  if (!await confirmar(`Remover o contrato principal de "${item.nome}"?`, { ok: 'Remover' })) return;
  const r = await api(`${cfg.api}/${item.id}/contrato`, 'DELETE');
  if (r.error) { $('contrato-error').textContent = r.error; return; }
  item.contrato_path = item.contrato_nome = null; renderContratoPrincipal(); toast('Contrato removido'); cfg.carregar();
}
async function carregarAditivos() {
  const { cfg, item } = contratoAtual;
  const r = await api(`documentos?tipo=${cfg.docTipo}&${cfg.docKey}=${item.id}`);
  const docs = Array.isArray(r) ? r : [];
  $('contrato-extras').innerHTML = docs.length ? docs.map(d => `<div class="doc"><div style="min-width:0"><div class="nm">📄 ${esc(d.descricao || d.arquivo_nome)}</div><div class="meta">${esc(d.arquivo_nome)} · ${fmtData(d.created_at)}</div></div>
      <div class="acts"><a class="btn btn-ghost sm" href="${esc(d.arquivo_path)}" target="_blank" rel="noopener">Abrir</a><button class="btn btn-ghost sm danger" type="button" data-doc="${d.id}">Apagar</button></div></div>`).join('')
    : '<div class="doc-empty">Nenhum aditivo anexado.</div>';
  $('contrato-extras').querySelectorAll('[data-doc]').forEach(b => b.onclick = async () => {
    if (!await confirmar('Apagar esse arquivo?', { ok: 'Apagar' })) return;
    const r = await api('documentos/' + b.dataset.doc, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    carregarAditivos(); cfg.carregar();
  });
}
$('contrato-extra-file').addEventListener('change', async () => {
  const f = $('contrato-extra-file').files[0]; if (!f) return;
  const { cfg, item } = contratoAtual, fd = new FormData();
  fd.append('tipo', cfg.docTipo); fd.append(cfg.docKey, item.id); fd.append('descricao', $('contrato-desc').value.trim()); fd.append('arquivo', f);
  const r = await api('documentos', 'POST', fd);
  $('contrato-extra-file').value = '';
  if (r.error) { $('contrato-error').textContent = r.error; return; }
  $('contrato-desc').value = ''; toast('Arquivo anexado'); carregarAditivos(); cfg.carregar();
});

// ═══════════════════════════ CATEGORIAS ═══════════════════════════
const cat = { lista: [], pagina: 1, editId: null };
const CAT_CORES = ['#147A4A', '#0E6E7A', '#3F4172', '#5A5CB4', '#6B5CA5', '#B0577D', '#C0504D', '#CFAE73', '#B98B2E', '#5E6E63'];
const TIPO_LABEL = { income: 'Receita', expense: 'Despesa', ambos: 'Receita e despesa' };
const anoAtual = new Date().getFullYear();
$('cat-ano-label').textContent = `(${anoAtual})`; $('cat-ano-label2').textContent = `(${anoAtual})`;

async function carregarCategorias() {
  const r = await api('categorias?year=' + anoAtual);
  cat.lista = Array.isArray(r) ? r : [];
  if (r.error) toast(r.error, 'err');
  renderCategorias();
}
function renderCategorias() {
  const sit = fv('cat-f-sit'), tipo = fv('cat-f-tipo'), t = norm($('cat-f-busca').value.trim());
  const lista = cat.lista.filter(c => (sit === '' || String(c.ativo) === sit) && (!tipo || c.tipo === tipo || c.tipo === 'ambos') && (!t || norm(c.nome).includes(t) || norm(c.descricao).includes(t)));
  const { itens, pagina, total, n } = paginar(lista, cat.pagina); cat.pagina = pagina;
  document.querySelector('#cat-table tbody').innerHTML = itens.length ? itens.map(c => {
    menuAcoes['cat' + c.id] = [
      { label: 'Editar', fn: () => abrirCategoria(c) },
      { label: c.ativo ? 'Desativar' : 'Ativar', fn: () => alternarCategoria(c) },
      { label: 'Apagar', danger: true, fn: () => apagarCategoria(c) },
      { label: 'Visualizar', fn: () => verCategoria(c) },
    ];
    return `<tr>
      <td class="nowrap"><div class="who"><span class="sw" style="background:${esc(c.cor)}"></span><span class="ttl">${esc(c.nome)}</span></div></td>
      <td><span class="tp ${c.tipo}">${TIPO_LABEL[c.tipo]}</span></td>
      <td class="wrap">${esc(c.descricao) || '<span class="dim">—</span>'}</td>
      <td class="r num">${c.qtd_ano || '<span class="dim">0</span>'}</td>
      <td class="money r">${c.total_ano ? fmtBRL(c.total_ano) : '<span class="dim">—</span>'}</td>
      <td>${pill(c.ativo, 'Ativa', 'Desativada')}</td>
      <td class="r">${kebab('cat' + c.id)}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="7" class="empty">${cat.lista.length ? '<strong>Nada encontrado</strong>Tente outro termo ou troque os filtros.' : '<strong>Nenhuma categoria ainda</strong>Crie categorias como Aluguel, Consultas ou Marketing.'}</td></tr>`;
  renderPager('cat-pager', pagina, total, p => { cat.pagina = p; renderCategorias(); }, n);
  $('cat-count').textContent = n === 1 ? '1 categoria' : `${n} categorias`;
}
$('cat-toolbar').addEventListener('submit', e => { e.preventDefault(); cat.pagina = 1; carregarCategorias(); });
['cat-f-sit', 'cat-f-tipo'].forEach(id => $(id).addEventListener('change', () => { cat.pagina = 1; renderCategorias(); }));
$('cat-f-busca').addEventListener('input', () => { cat.pagina = 1; renderCategorias(); });

function renderCores(sel) {
  const lista = CAT_CORES.includes(sel.toUpperCase()) ? CAT_CORES : [...CAT_CORES, sel];
  $('cat-cores').innerHTML = lista.map(c => `<button type="button" data-cor="${c}" style="background:${c}" class="${c.toUpperCase() === sel.toUpperCase() ? 'on' : ''}" title="${c}"></button>`).join('')
    + `<input type="color" id="cat-cor-livre" value="${sel}" title="Outra cor"/>`;
  $('cat-cores').querySelectorAll('button').forEach(b => b.onclick = () => renderCores(b.dataset.cor));
  $('cat-cor-livre').oninput = e => renderCores(e.target.value);
  $('cat-cores').dataset.cor = sel;
}
function abrirCategoria(c = null) {
  cat.editId = c ? c.id : null;
  $('cat-modal-title').textContent = c ? 'Editar Categoria' : 'Nova Categoria';
  $('cat-error').textContent = '';
  $('cat-nome').value = c ? c.nome : '';
  $('cat-desc').value = c ? (c.descricao || '') : '';
  segSet('cat-tipo-seg', c ? c.tipo : (fv('cat-f-tipo') || 'expense'));
  segSet('cat-sit-seg', c ? String(c.ativo) : '1');
  renderCores(c ? c.cor : CAT_CORES[cat.lista.length % CAT_CORES.length]);
  abrirModal('modal-cat');
}
$('btn-nova-categoria').addEventListener('click', () => abrirCategoria());
$('cat-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-cat').click(); });
async function salvarCategoria(id, body) {
  const r = id ? await api('categorias/' + id, 'PUT', body) : await api('categorias', 'POST', body);
  if (!r.error) { carregarCategorias(); carregarCadastros(); }
  return r;
}
$('btn-salvar-cat').addEventListener('click', async () => {
  const body = { nome: $('cat-nome').value.trim(), tipo: segValor('cat-tipo-seg'), cor: $('cat-cores').dataset.cor, descricao: $('cat-desc').value.trim(), ativo: Number(segValor('cat-sit-seg')) };
  if (!body.nome) { $('cat-error').textContent = 'Informe o nome da categoria.'; return; }
  const r = await salvarCategoria(cat.editId, body);
  if (r.error) { $('cat-error').textContent = r.error; return; }
  fecharModal('modal-cat'); toast(cat.editId ? 'Categoria atualizada' : 'Categoria criada');
});
async function alternarCategoria(c) {
  const r = await salvarCategoria(c.id, { nome: c.nome, tipo: c.tipo, cor: c.cor, descricao: c.descricao || '', ativo: c.ativo ? 0 : 1 });
  if (r.error) return toast(r.error, 'err');
  toast(c.ativo ? 'Categoria desativada' : 'Categoria ativada');
}
async function apagarCategoria(c) {
  if (c.qtd_total) {
    if (await confirmar(`"${c.nome}" está em ${c.qtd_total} lançamento(s), então não pode ser apagada sem perder o histórico. Quer desativá-la? Ela some da lista na hora de lançar, mas os lançamentos antigos continuam com ela.`, { titulo: 'Categoria em uso', ok: 'Desativar', perigo: false })) {
      if (c.ativo) alternarCategoria(c);
    }
    return;
  }
  if (!await confirmar(`Apagar a categoria "${c.nome}"?`, { ok: 'Apagar' })) return;
  const r = await api('categorias/' + c.id, 'DELETE');
  if (r.error) return toast(r.error, 'err');
  toast('Categoria apagada'); carregarCategorias(); carregarCadastros();
}
function verCategoria(c) {
  const botoes = [{ label: 'Editar', fn: () => { fecharModal('modal-view'); abrirCategoria(c); } }];
  if (podeVerPagina('lancamentos')) botoes.unshift({ label: 'Ver lançamentos', cls: 'btn-gold', fn: () => {
    fecharModal('modal-view');
    $('tx-f-categoria').value = String(c.id); $('tx-f-ano').value = ''; $('tx-f-mes').value = ''; fset('tx-f-tipo', ''); $('tx-f-status').value = '';
    location.hash = 'lancamentos'; if (state.page === 'lancamentos') carregarLancamentos();
  } });
  mostrarDetalhes(c.nome, TIPO_LABEL[c.tipo], [
    ['Cor', `<span class="dot" style="background:${esc(c.cor)}"></span>${esc(c.cor)}`, true], ['Descrição', c.descricao],
    ['Situação', c.ativo ? 'Ativa' : 'Desativada'], [`Lançamentos em ${anoAtual}`, String(c.qtd_ano)], [`Total em ${anoAtual}`, fmtBRL(c.total_ano)],
    ['Lançamentos (todos)', String(c.qtd_total)], ['Criada em', fmtData(c.created_at)],
  ], botoes);
}

// ═══════════════════════════ USUÁRIOS (admin) ═══════════════════════════
// Só as telas que existem hoje. Permissões antigas (de telas que ainda vão voltar)
// são preservadas ao salvar, pra não sumirem sem querer.
const PERM_TELAS = [
  { key: 'lancamentos', label: 'Lançamentos' }, { key: 'cad-parceiros', label: 'Parceiros' },
  { key: 'cad-fornecedores', label: 'Fornecedores' }, { key: 'cad-categorias', label: 'Categorias' },
];
const usr = { lista: [], pagina: 1, editId: null, extras: [] };
async function carregarUsuarios() {
  const r = await api('users');
  usr.lista = Array.isArray(r) ? r : [];
  if (r.error) toast(r.error, 'err');
  renderUsuarios();
}
function renderUsuarios() {
  const t = norm($('usr-f-busca').value.trim());
  const lista = usr.lista.filter(u => !t || norm(u.username).includes(t) || norm(u.nome).includes(t));
  const { itens, pagina, total, n } = paginar(lista, usr.pagina); usr.pagina = pagina;
  document.querySelector('#usr-table tbody').innerHTML = itens.length ? itens.map(u => {
    const eu = u.id === state.user.id;
    menuAcoes['usr' + u.id] = [
      { label: 'Editar', fn: () => abrirUsuario(u) },
      { label: 'Apagar', danger: true, disabled: eu, fn: () => apagarUsuario(u) },
    ];
    const telas = u.role === 'admin' ? '<span class="chip gold">Todas as telas</span>'
      : (PERM_TELAS.filter(p => (u.permissoes || []).includes(p.key)).map(p => `<span class="chip">${p.label}</span>`).join('') || '<span class="chip gray">Nenhuma</span>');
    return `<tr>
      <td><div class="who">${u.foto_path ? `<span class="av"><img src="${esc(u.foto_path)}" alt=""/></span>` : avatar(u.nome || u.username)}<div><span class="ttl">${esc(u.nome || u.username)}${eu ? ' <span class="you">você</span>' : ''}</span><small>@${esc(u.username)}</small></div></div></td>
      <td><span class="tp ${u.role === 'admin' ? 'admin' : 'ambos'}">${u.role === 'admin' ? 'Administrador' : 'Usuário'}</span></td>
      <td><div class="chips">${telas}</div></td>
      <td class="nowrap num">${fmtData(u.created_at)}</td>
      <td class="r">${kebab('usr' + u.id)}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="5" class="empty"><strong>Nenhum usuário encontrado</strong>Tente outro termo.</td></tr>';
  renderPager('usr-pager', pagina, total, p => { usr.pagina = p; renderUsuarios(); }, n);
  $('usr-count').textContent = n === 1 ? '1 usuário' : `${n} usuários`;
}
$('usr-toolbar').addEventListener('submit', e => { e.preventDefault(); carregarUsuarios(); });
$('usr-f-busca').addEventListener('input', () => { usr.pagina = 1; renderUsuarios(); });

function abrirUsuario(u = null) {
  usr.editId = u ? u.id : null;
  const perms = u ? (u.permissoes || []) : ['lancamentos'];
  usr.extras = perms.filter(p => !PERM_TELAS.some(t => t.key === p));
  $('usr-modal-title').textContent = u ? `Editar ${u.username}` : 'Novo Usuário';
  $('usr-senha-label').textContent = u ? 'Nova senha' : 'Senha *';
  $('usr-senha').placeholder = u ? 'deixe em branco para não alterar' : 'mínimo 6 caracteres';
  $('usr-username').value = u ? u.username : ''; $('usr-nome').value = u ? (u.nome || '') : ''; $('usr-senha').value = '';
  $('usr-error').textContent = '';
  segSet('usr-role-seg', u ? u.role : 'admin'); // por enquanto toda conta nova é admin
  $('usr-perms').innerHTML = PERM_TELAS.map(p => `<label class="perm"><input type="checkbox" value="${p.key}" ${perms.includes(p.key) ? 'checked' : ''}/>${p.label}</label>`).join('');
  atualizarRoleUsr();
  abrirModal('modal-usr');
}
function atualizarRoleUsr() { const admin = segValor('usr-role-seg') === 'admin'; $('usr-perms-wrap').classList.toggle('hidden', admin); $('usr-admin-hint').hidden = !admin; }
$('usr-role-seg').addEventListener('change', atualizarRoleUsr);
$('usr-perms-todas').addEventListener('click', () => { const cbs = [...$('usr-perms').querySelectorAll('input')]; const todas = cbs.every(c => c.checked); cbs.forEach(c => c.checked = !todas); });
$('btn-novo-usuario').addEventListener('click', () => abrirUsuario());
$('usr-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-usr').click(); });
$('btn-salvar-usr').addEventListener('click', async () => {
  const body = { username: $('usr-username').value.trim(), nome: $('usr-nome').value.trim(), password: $('usr-senha').value, role: segValor('usr-role-seg') };
  if (body.role !== 'admin') body.permissoes = [...[...$('usr-perms').querySelectorAll('input:checked')].map(i => i.value), ...usr.extras];
  if (!body.username) { $('usr-error').textContent = 'Informe o usuário.'; return; }
  if (!usr.editId && !body.password) { $('usr-error').textContent = 'Defina uma senha.'; return; }
  if (body.password && body.password.length < 6) { $('usr-error').textContent = 'A senha precisa ter pelo menos 6 caracteres.'; return; }
  const r = usr.editId ? await api('users/' + usr.editId, 'PUT', body) : await api('users', 'POST', body);
  if (r.error) { $('usr-error').textContent = r.error; return; }
  fecharModal('modal-usr'); toast(usr.editId ? 'Usuário atualizado' : 'Usuário criado'); carregarUsuarios();
});
async function apagarUsuario(u) {
  if (!await confirmar(`Apagar o usuário "${u.username}"? Ele perde o acesso na hora.`, { ok: 'Apagar' })) return;
  const r = await api('users/' + u.id, 'DELETE');
  if (r.error) return toast(r.error, 'err');
  toast('Usuário apagado'); carregarUsuarios();
}

// ═══════════════════════════ PERFIL ═══════════════════════════
$('btn-perfil').addEventListener('click', () => {
  const u = state.user;
  $('perfil-username').textContent = u.username;
  $('perfil-role').textContent = u.role === 'admin' ? 'Administrador' : 'Usuário';
  $('perfil-nome').value = u.nome || ''; $('perfil-senha').value = ''; $('perfil-foto').value = ''; $('perfil-error').textContent = '';
  renderAvatar($('perfil-avatar'), u);
  abrirModal('modal-perfil');
});
$('perfil-foto').addEventListener('change', () => { const f = $('perfil-foto').files[0]; if (f) $('perfil-avatar').innerHTML = `<img src="${URL.createObjectURL(f)}" alt=""/>`; });
$('perfil-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-perfil').click(); });
$('btn-salvar-perfil').addEventListener('click', async () => {
  const senha = $('perfil-senha').value;
  if (senha && senha.length < 6) { $('perfil-error').textContent = 'A nova senha precisa ter pelo menos 6 caracteres.'; return; }
  const fd = new FormData();
  fd.append('nome', $('perfil-nome').value.trim());
  if (senha) fd.append('password', senha);
  const f = $('perfil-foto').files[0]; if (f) fd.append('foto', f);
  const r = await api('auth/profile', 'POST', fd);
  if (r.error) { $('perfil-error').textContent = r.error; return; }
  state.user = r; renderAvatar($('user-avatar'), r);
  fecharModal('modal-perfil'); toast('Perfil atualizado');
});
$('btn-sair').addEventListener('click', async () => {
  await api('auth/logout', 'POST');
  fecharModal('modal-perfil');
  history.replaceState(null, '', location.pathname);
  mostrarLogin();
});

iniciar();
