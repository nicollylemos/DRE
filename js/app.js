/* ═══════════════════════════════════════════════════════════════════════════
   OAF — Controle Financeiro · front-end
   Telas: Dashboard, Lançamentos, Fluxo de Caixa, Clientes, Fornecedores, Parceiros,
   Logs e — só admin — Categorias, Caixas/Banco, Formas de pagamento e Usuários.
   ═══════════════════════════════════════════════════════════════════════════ */
'use strict';

// ─── UTILITÁRIOS ──────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const API = 'api'; // relativo: funciona na raiz ou em subpasta (ex: localhost/DRE-php/)
const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const POR_PAGINA = 10;
const MESES_CURTOS = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// Escapa texto vindo do banco antes de ir pro innerHTML (evita XSS).
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtBRL = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtBRLs = v => (Number(v) < 0 ? '− ' : '') + fmtBRL(Math.abs(Number(v) || 0)); // com sinal
const fmtBRLk = v => { const a = Math.abs(v); return a >= 1e6 ? (v / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : a >= 1e3 ? (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : String(Math.round(v)); };
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
function renderPager(elId, pagina, total, onChange, n = 0, porPagina = POR_PAGINA) {
  const el = $(elId);
  const ini = n ? (pagina - 1) * porPagina + 1 : 0, fim = Math.min(pagina * porPagina, n);
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
  $('auth-version').textContent = st.version ? `Versão ${st.version}` : '';
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
  $('side-brand').href = '#' + primeiraPagina(); // a logo leva pra primeira tela liberada
  await carregarCadastros();
  irPara(location.hash.slice(1));
}

// ─── NAVEGAÇÃO ────────────────────────────────────────────────────────────────
// perm = mesma chave que o backend usa em permitir(). Usuário comum só tem
// dashboard, lancamentos, fluxo-caixa, cad-clientes, cad-fornecedores, cad-parceiros e logs.
const PAGINAS = {
  dashboard:    { perm: 'dashboard',        load: () => carregarDashboard() },
  lancamentos:  { perm: 'lancamentos',      load: () => carregarLancamentos() },
  fluxo:        { perm: 'fluxo-caixa',      load: () => carregarFluxo() },
  clientes:     { perm: 'cad-clientes',     load: () => carregarClientes() },
  fornecedores: { perm: 'cad-fornecedores', load: () => ENT.fornecedores.carregar() },
  parceiros:    { perm: 'cad-parceiros',    load: () => ENT.parceiros.carregar() },
  categorias:   { perm: 'cad-categorias',   load: () => carregarCategorias() },
  caixas:       { perm: 'cad-caixas',       load: () => carregarCaixas() },
  formas:       { perm: 'cad-formas',       load: () => carregarFormas() },
  usuarios:     { admin: true,              load: () => carregarUsuarios() },
  logs:         { perm: 'logs',             load: () => carregarLogs() },
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
  // Sugestões de banco (Caixas e Formas de pagamento)
  const bancos = new Set(['Itaú', 'Bradesco', 'Banco do Brasil', 'Caixa Econômica', 'Santander', 'Nubank', 'Inter', 'C6 Bank', 'Sicoob', 'Sicredi', 'Mercado Pago', 'PagBank', 'Stone', 'Cielo', 'Rede', 'Getnet']);
  $('lista-bancos').innerHTML = [...bancos].map(b => `<option value="${esc(b)}">`).join('');
  // Filtro de caixa do fluxo
  const fx = $('fx-caixa'), fxAtual = fx.value;
  fx.innerHTML = '<option value="">Todos os caixas</option>' + (state.cad.caixasBancos || []).map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('');
  fx.value = fxAtual;
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
// Dois níveis fixos: o administrador vê tudo; o usuário comum só as telas abaixo.
const TELAS_USUARIO = ['Dashboard', 'Lançamentos', 'Fluxo de Caixa', 'Clientes', 'Fornecedores', 'Parceiros', 'Logs'];
const TELAS_SO_ADMIN = ['Categorias', 'Caixas / Banco', 'Formas de pagamento', 'Usuários'];
const usr = { lista: [], pagina: 1, editId: null };
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
      : TELAS_USUARIO.map(l => `<span class="chip">${l}</span>`).join('');
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
  $('usr-modal-title').textContent = u ? `Editar ${u.username}` : 'Novo Usuário';
  $('usr-senha-label').textContent = u ? 'Nova senha' : 'Senha *';
  $('usr-senha').placeholder = u ? 'deixe em branco para não alterar' : 'mínimo 6 caracteres';
  $('usr-username').value = u ? u.username : ''; $('usr-nome').value = u ? (u.nome || '') : ''; $('usr-senha').value = '';
  $('usr-error').textContent = '';
  segSet('usr-role-seg', u ? u.role : 'user');
  atualizarRoleUsr();
  abrirModal('modal-usr');
}
function atualizarRoleUsr() {
  const admin = segValor('usr-role-seg') === 'admin';
  $('usr-nivel').innerHTML = admin
    ? `<p>Administrador acessa <strong>todas as telas</strong>, inclusive:</p><div class="chips">${[...TELAS_USUARIO, ...TELAS_SO_ADMIN].map(l => `<span class="chip ${TELAS_SO_ADMIN.includes(l) ? 'gold' : ''}">${l}</span>`).join('')}</div>`
    : `<p>Usuário acessa somente estas telas (o resto fica escondido e bloqueado):</p><div class="chips">${TELAS_USUARIO.map(l => `<span class="chip">${l}</span>`).join('')}</div>`;
}
$('usr-role-seg').addEventListener('change', atualizarRoleUsr);
$('btn-novo-usuario').addEventListener('click', () => abrirUsuario());
$('usr-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-usr').click(); });
$('btn-salvar-usr').addEventListener('click', async () => {
  const body = { username: $('usr-username').value.trim(), nome: $('usr-nome').value.trim(), password: $('usr-senha').value, role: segValor('usr-role-seg') };
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

// ═══════════════════════════ DASHBOARD ═══════════════════════════
const hoje0 = new Date();
const dash = { ano: hoje0.getFullYear(), calAno: hoje0.getFullYear(), calMes: hoje0.getMonth() + 1, dia: null, agenda: [] };
const anosLista = () => { const a = new Date().getFullYear(); return [a + 1, a, a - 1, a - 2, a - 3, a - 4]; };
$('dash-ano').innerHTML = anosLista().map(a => `<option value="${a}" ${a === dash.ano ? 'selected' : ''}>${a}</option>`).join('');
$('dash-ano').addEventListener('change', () => {
  dash.ano = Number($('dash-ano').value);
  dash.calAno = dash.ano; dash.calMes = dash.ano === new Date().getFullYear() ? new Date().getMonth() + 1 : 1; dash.dia = null;
  carregarDashboard();
});
$('btn-dash-excel').addEventListener('click', () => { location.href = `${API}/export/excel?year=${dash.ano}`; });

async function carregarDashboard() {
  const r = await api('dre?year=' + dash.ano);
  if (r.error) { toast(r.error, 'err'); return; }
  const L = r.dreLines || {};
  const bruta = L['Receita Bruta'] || 0, ded = L['(-) Impostos e Deduções'] || 0, liq = L['Receita Líquida'] || 0;
  const desp = L['(-) Despesas Operacionais'] || 0, lucro = L['Lucro / Prejuízo Líquido'] || 0;
  $('dash-rec').textContent = fmtBRL(bruta);
  $('dash-rec-sub').textContent = ded ? `Taxas/deduções ${fmtBRL(ded)}` : 'sem taxas no período';
  $('dash-desp').textContent = fmtBRL(desp);
  $('dash-desp-sub').textContent = liq ? `${Math.round(desp / liq * 100)}% da receita líquida` : '';
  $('dash-lucro').textContent = fmtBRLs(lucro);
  $('dash-lucro').classList.toggle('neg-on-dark', lucro < 0);
  $('dash-lucro-sub').textContent = lucro < 0 ? 'prejuízo no exercício' : 'resultado do exercício';
  $('dash-margem').textContent = liq ? (lucro / liq * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%' : '—';

  const ab = r.emAberto || {};
  $('dash-areceber').textContent = fmtBRL(ab.a_receber);
  $('dash-apagar').textContent = fmtBRL(ab.a_pagar);
  $('dash-vencidos').textContent = String(Math.round(ab.qtd_vencidos || 0));
  $('dash-vencidos').className = ab.qtd_vencidos ? 'neg' : '';
  $('dash-vencidos-sub').textContent = ab.qtd_vencidos ? `${fmtBRL(ab.pagar_vencido)} a pagar · ${fmtBRL(ab.receber_vencido)} a receber` : 'nada vencido';
  $('dash-saldo-cx').textContent = fmtBRLs(r.saldoCaixas);
  $('dash-saldo-cx').className = r.saldoCaixas < 0 ? 'neg' : '';

  $('dash-dre-ano').textContent = `Exercício ${dash.ano}`;
  $('dash-cat-ano').textContent = `Exercício ${dash.ano}`;
  const linha = (cls, k, v) => `<div class="${cls}"><dt>${k}</dt><dd>${v}</dd></div>`;
  $('dash-dre').innerHTML =
    linha('tot', 'Receita bruta', fmtBRL(bruta)) +
    linha('sub', '(−) Impostos, taxas e deduções', fmtBRL(ded)) +
    linha('tot', 'Receita líquida', fmtBRL(liq)) +
    linha('sub', '(−) Despesas operacionais', fmtBRL(desp)) +
    linha('res' + (lucro < 0 ? ' neg-res' : ''), lucro < 0 ? 'Prejuízo líquido' : 'Lucro líquido', fmtBRLs(lucro));

  const meses = r.summary || [];
  renderChartMeses($('dash-chart'), meses.map(m => m.receitas - m.deducoes), meses.map(m => m.despesas));
  renderCategoriasDash(r.expensesByCategory || {}, r.categoryColors || {});
  carregarAgenda();
}

// Barras agrupadas por mês: receita líquida x despesas. Tooltip por mês (hover/foco).
function renderChartMeses(el, rec, desp) {
  const max = Math.max(...rec, ...desp, 0);
  if (!max) { el.innerHTML = '<div class="empty-chart">Sem lançamentos neste exercício.</div>'; return; }
  const W = 720, H = 250, pl = 54, pr = 6, pt = 10, pb = 26, ih = H - pt - pb;
  const passo = Math.pow(10, Math.floor(Math.log10(max))) / 2;
  const topo = Math.ceil(max / passo / 4) * passo * 4 || 1;   // 4 faixas "redondas"
  const y = v => pt + ih - (v / topo) * ih;
  const gw = (W - pl - pr) / 12, bw = Math.min(18, (gw - 12) / 2);
  const barra = (x, v, cor) => {
    if (!v) return '';
    const h = Math.max(2, ih * v / topo), yy = pt + ih - h, r = Math.min(4, h, bw / 2);
    return `<path class="bar" fill="${cor}" d="M${x},${pt + ih}V${yy + r}Q${x},${yy} ${x + r},${yy}H${x + bw - r}Q${x + bw},${yy} ${x + bw},${yy + r}V${pt + ih}Z"/>`;
  };
  let g = '';
  for (let i = 0; i <= 4; i++) {
    const v = topo / 4 * i, yy = y(v);
    g += `<line class="grid-l" x1="${pl}" x2="${W - pr}" y1="${yy}" y2="${yy}"/><text class="axis-t" x="${pl - 8}" y="${yy + 4}" text-anchor="end">${fmtBRLk(v)}</text>`;
  }
  MESES_CURTOS.forEach((m, i) => {
    const x0 = pl + gw * i, cx = x0 + gw / 2;
    g += `<g class="col" data-i="${i}" tabindex="0"><rect class="hit" x="${x0 + 2}" y="${pt}" width="${gw - 4}" height="${ih}" rx="6"/>`
      + barra(cx - bw - 1, rec[i], 'var(--c-rec)') + barra(cx + 1, desp[i], 'var(--c-desp)')
      + `<text class="axis-t" x="${cx}" y="${H - 6}" text-anchor="middle">${m}</text></g>`;
  });
  el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Receita líquida e despesas por mês">${g}</svg><div class="chart-tip hidden"></div>`;
  const tip = el.querySelector('.chart-tip');
  const mostra = col => {
    const i = Number(col.dataset.i), saldo = rec[i] - desp[i];
    tip.innerHTML = `<b>${MESES[i]}</b><div><i style="background:var(--c-rec)"></i>Receita líquida<span class="r">${fmtBRL(rec[i])}</span></div>`
      + `<div><i style="background:var(--c-desp)"></i>Despesas<span class="r">${fmtBRL(desp[i])}</span></div>`
      + `<div style="margin-top:4px;border-top:1px solid rgba(255,255,255,.15);padding-top:4px">Resultado<span class="r">${fmtBRLs(saldo)}</span></div>`;
    tip.classList.remove('hidden');
    const cr = el.getBoundingClientRect(), rr = col.querySelector('.hit').getBoundingClientRect();
    let left = rr.left - cr.left + rr.width / 2;
    left = Math.min(Math.max(left, tip.offsetWidth / 2 + 4), cr.width - tip.offsetWidth / 2 - 4);
    // Ancora acima da barra mais alta do mês, sem sair do card.
    const tops = [...col.querySelectorAll('.bar')].map(b => b.getBoundingClientRect().top);
    const topo = tops.length ? Math.min(...tops) : rr.bottom;
    tip.style.left = left + 'px'; tip.style.top = Math.max(topo - cr.top, tip.offsetHeight + 8) + 'px';
  };
  el.querySelectorAll('.col').forEach(c => {
    c.addEventListener('mouseenter', () => mostra(c)); c.addEventListener('focus', () => mostra(c));
    c.addEventListener('mouseleave', () => tip.classList.add('hidden')); c.addEventListener('blur', () => tip.classList.add('hidden'));
  });
}

function renderCategoriasDash(porCat, cores) {
  const itens = Object.entries(porCat).filter(([, v]) => v > 0).sort((a, b) => b[1] - a[1]);
  const total = itens.reduce((s, [, v]) => s + v, 0), max = itens.length ? itens[0][1] : 0;
  $('dash-cats').innerHTML = itens.length ? itens.slice(0, 10).map(([nome, v]) => {
    const cor = cores[nome] || 'var(--navy-2)';
    return `<div class="cat-row"><span class="nm"><i style="background:${esc(cor)}"></i>${esc(nome)}</span>
      <span class="vl">${fmtBRL(v)}<small>${Math.round(v / total * 100)}%</small></span>
      <div class="trk"><span style="width:${(v / max * 100).toFixed(1)}%;background:${esc(cor)}"></span></div></div>`;
  }).join('') : '<div class="empty-chart" style="grid-column:1/-1;padding:30px">Nenhuma despesa neste exercício.</div>';
}

// ─── Agenda (calendário de vencimentos) ───
async function carregarAgenda() {
  $('cal-titulo').textContent = `${MESES[dash.calMes - 1]} de ${dash.calAno}`;
  const r = await api(`agenda?year=${dash.calAno}&month=${dash.calMes}`);
  dash.agenda = Array.isArray(r) ? r : [];
  if (r.error) toast(r.error, 'err');
  renderCalendario(); renderAgendaLista();
}
function mudarMesAgenda(delta) {
  let m = dash.calMes + delta, a = dash.calAno;
  if (m < 1) { m = 12; a--; } if (m > 12) { m = 1; a++; }
  dash.calMes = m; dash.calAno = a; dash.dia = null; carregarAgenda();
}
$('cal-prev').addEventListener('click', () => mudarMesAgenda(-1));
$('cal-next').addEventListener('click', () => mudarMesAgenda(1));
$('cal-hoje').addEventListener('click', () => { const d = new Date(); dash.calAno = d.getFullYear(); dash.calMes = d.getMonth() + 1; dash.dia = hojeISO(); carregarAgenda(); });

function renderCalendario() {
  const { calAno: a, calMes: m } = dash;
  const primeiro = new Date(a, m - 1, 1).getDay(), dias = new Date(a, m, 0).getDate(), hoje = hojeISO();
  let html = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map(d => `<div class="dow">${d}</div>`).join('');
  for (let i = 0; i < primeiro; i++) html += '<button type="button" class="out" tabindex="-1" aria-hidden="true"></button>';
  for (let d = 1; d <= dias; d++) {
    const iso = `${a}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const its = dash.agenda.filter(x => x.venc === iso);
    const abertos = its.filter(x => x.status === 'em_aberto');
    const rec = abertos.filter(x => x.tipo === 'income').reduce((s, x) => s + x.valor, 0);
    const pag = abertos.filter(x => x.tipo === 'expense').reduce((s, x) => s + x.valor, 0);
    const quitados = its.length - abertos.length;
    const cls = [iso === hoje ? 'today' : '', iso === dash.dia ? 'sel' : '', its.some(x => x.vencido) ? 'late' : '', rec ? 'has-in' : '', pag ? 'has-out' : ''].join(' ');
    const tags = (rec ? `<span class="tag in">+${fmtBRLk(rec)}</span>` : '') + (pag ? `<span class="tag out">−${fmtBRLk(pag)}</span>` : '')
      + (quitados && !rec && !pag ? `<span class="tag done">✓ ${quitados}</span>` : '');
    const titulo = its.length ? `${its.length} título(s)` + (rec ? ` · a receber ${fmtBRL(rec)}` : '') + (pag ? ` · a pagar ${fmtBRL(pag)}` : '') : 'Sem vencimentos';
    html += `<button type="button" class="${cls}" data-dia="${iso}" title="${esc(titulo)}"><span class="d">${d}</span>${tags}</button>`;
  }
  $('cal').innerHTML = html;
  $('cal').querySelectorAll('button[data-dia]').forEach(b => b.onclick = () => { dash.dia = dash.dia === b.dataset.dia ? null : b.dataset.dia; renderCalendario(); renderAgendaLista(); });
}
function renderAgendaLista() {
  const lista = dash.dia ? dash.agenda.filter(x => x.venc === dash.dia) : dash.agenda;
  if (dash.dia) { const [, mm, dd] = dash.dia.split('-'); $('agenda-dia-titulo').textContent = `${Number(dd)} de ${MESES[Number(mm) - 1].toLowerCase()}`; }
  else $('agenda-dia-titulo').textContent = `${MESES[dash.calMes - 1]} — mês inteiro`;
  const ab = lista.filter(x => x.status === 'em_aberto');
  const rec = ab.filter(x => x.tipo === 'income').reduce((s, x) => s + x.valor, 0), pag = ab.filter(x => x.tipo === 'expense').reduce((s, x) => s + x.valor, 0);
  $('agenda-dia-tot').innerHTML = lista.length ? `<span class="pos">+${esc(fmtBRL(rec))}</span> · <span class="neg">−${esc(fmtBRL(pag))}</span>` : '';
  if (!lista.length) { $('agenda-itens').innerHTML = `<div class="ag-empty">${dash.dia ? 'Nenhuma conta vence neste dia.' : 'Nenhuma conta a pagar ou a receber neste mês.'}</div>`; return; }
  let html = '', diaAnt = '';
  lista.forEach((x, i) => {
    if (!dash.dia && x.venc !== diaAnt) { diaAnt = x.venc; const dt = new Date(x.venc + 'T12:00'); html += `<div class="ag-day">${dt.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })}</div>`; }
    const st = x.status === 'quitado' ? '<span class="st on">Quitado</span>' : x.vencido ? '<span class="st late">Vencido</span>' : '<span class="st open">Em aberto</span>';
    const quem = [x.cliente_fornecedor, x.categoria_nome].filter(Boolean).join(' · ') || (x.tipo === 'income' ? 'A receber' : 'A pagar');
    html += `<div class="ag-item ${x.status === 'quitado' ? 'done' : ''}" data-i="${dash.agenda.indexOf(x)}" tabindex="0">
      <span class="bar-s ${x.tipo === 'income' ? 'in' : 'out'}"></span>
      <div class="tx"><b>${esc(x.descricao) || '<span class="dim">Sem descrição</span>'}</b><small>${esc(quem)}</small></div>
      <div class="vl ${x.tipo === 'income' ? 'pos' : 'neg'}">${x.tipo === 'income' ? '+' : '−'} ${fmtBRL(x.valor)}<div>${st}</div></div></div>`;
  });
  $('agenda-itens').innerHTML = html;
  $('agenda-itens').querySelectorAll('.ag-item').forEach(el => {
    const abre = () => verTituloAgenda(dash.agenda[Number(el.dataset.i)]);
    el.onclick = abre; el.onkeydown = e => { if (e.key === 'Enter') abre(); };
  });
}
function verTituloAgenda(x) {
  const botoes = [];
  if (x.status === 'em_aberto' && pode('lancamentos')) botoes.push({ label: 'Dar baixa', cls: 'btn-gold', fn: async () => {
    const r = await api('transactions/' + x.id, 'PATCH', { status: 'quitado', data_pagamento: hojeISO() });
    if (r.error) return toast(r.error, 'err');
    fecharModal('modal-view'); toast('Baixa registrada'); carregarDashboard();
  } });
  mostrarDetalhes(x.descricao || 'Título', `${x.tipo === 'income' ? 'Conta a receber' : 'Conta a pagar'} · ${fmtBRL(x.valor)}`, [
    ['Vencimento', fmtData(x.venc)], ['Situação', x.status === 'quitado' ? `Quitado em ${fmtData(x.data_pagamento)}` : x.vencido ? 'Vencido' : 'Em aberto'],
    [x.tipo === 'income' ? 'Cliente' : 'Fornecedor', x.cliente_fornecedor], ['Categoria', x.categoria_nome],
    ['Forma de pagamento', x.forma_pagamento_nome], ['Caixa / Banco', x.caixa_nome], ['Data do lançamento', fmtData(x.data)],
  ], botoes);
}

// ═══════════════════════════ FLUXO DE CAIXA ═══════════════════════════
const fx = { dias: [], abertura: 0 };
$('fx-mes').innerHTML = MESES.map((m, i) => `<option value="${i + 1}" ${i === hoje0.getMonth() ? 'selected' : ''}>${m}</option>`).join('');
$('fx-ano').innerHTML = anosLista().map(a => `<option value="${a}" ${a === hoje0.getFullYear() ? 'selected' : ''}>${a}</option>`).join('');
['fx-mes', 'fx-ano', 'fx-caixa'].forEach(id => $(id).addEventListener('change', carregarFluxo));
$('fx-dias').addEventListener('change', renderFluxo);
$('fx-toolbar').addEventListener('submit', e => { e.preventDefault(); carregarFluxo(); });
function passoFluxo(delta) {
  let m = Number($('fx-mes').value) + delta, a = Number($('fx-ano').value);
  if (m < 1) { m = 12; a--; } if (m > 12) { m = 1; a++; }
  if (![...$('fx-ano').options].some(o => Number(o.value) === a)) $('fx-ano').insertAdjacentHTML('beforeend', `<option value="${a}">${a}</option>`);
  $('fx-mes').value = String(m); $('fx-ano').value = String(a); carregarFluxo();
}
$('fx-prev').addEventListener('click', () => passoFluxo(-1));
$('fx-next').addEventListener('click', () => passoFluxo(1));

async function carregarFluxo() {
  const qs = new URLSearchParams({ year: $('fx-ano').value, month: $('fx-mes').value });
  if ($('fx-caixa').value) qs.set('caixa', $('fx-caixa').value);
  const r = await api('dre/fluxo-caixa?' + qs);
  if (r.error) { toast(r.error, 'err'); fx.dias = []; fx.abertura = 0; }
  else { fx.dias = r.dias || []; fx.abertura = r.saldo_abertura || 0; }
  renderFluxo();
}
function renderFluxo() {
  const d = fx.dias, ult = d[d.length - 1] || { saldo: fx.abertura, saldo_projetado: fx.abertura };
  const soma = k => d.reduce((s, x) => s + (x[k] || 0), 0);
  $('fx-abertura').textContent = fmtBRLs(fx.abertura);
  $('fx-rec').textContent = fmtBRL(soma('receita'));
  $('fx-desp').textContent = fmtBRL(soma('despesa'));
  $('fx-saldo').textContent = fmtBRLs(ult.saldo);
  $('fx-saldo').classList.toggle('neg-on-dark', ult.saldo < 0);
  $('fx-proj').textContent = fmtBRLs(ult.saldo_projetado);
  const prev = soma('previsto');
  $('fx-proj-sub').textContent = prev ? `${prev > 0 ? '+' : '−'} ${fmtBRL(Math.abs(prev))} em aberto no mês` : 'nada em aberto no mês';
  const so = fv('fx-dias') === 'mov', hoje = hojeISO();
  const linhas = d.filter(x => !so || x.receita || x.despesa || x.transferencia || x.receita_prevista || x.despesa_prevista || x.data === hoje);
  const v = (n, cls = '') => n ? `<td class="money r ${cls}">${fmtBRL(n)}</td>` : '<td class="r dim">—</td>';
  const vs = n => n ? `<td class="money r ${n < 0 ? 'neg' : 'pos'}">${fmtBRLs(n)}</td>` : '<td class="r dim">—</td>';
  document.querySelector('#fx-table tbody').innerHTML = linhas.length ? linhas.map(x => {
    const dt = new Date(x.data + 'T12:00'), fds = dt.getDay() === 0 || dt.getDay() === 6;
    return `<tr class="${x.data === hoje ? 'today' : ''} ${fds ? 'weekend' : ''}">
      <td class="nowrap num"><span class="ttl">${fmtData(x.data).slice(0, 5)}</span> <span class="dim">${dt.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '')}</span></td>
      ${v(x.receita, 'pos')}${v(x.despesa, 'neg')}${v(x.transferencia)}${vs(x.resultado)}
      <td class="money r ${x.saldo < 0 ? 'neg' : ''}">${fmtBRLs(x.saldo)}</td>
      ${x.previsto ? `<td class="money r ${x.previsto < 0 ? 'neg' : 'pos'}" title="A receber ${fmtBRL(x.receita_prevista)} · a pagar ${fmtBRL(x.despesa_prevista)}">${fmtBRLs(x.previsto)}</td>` : '<td class="r dim">—</td>'}
      <td class="money r ${x.saldo_projetado < 0 ? 'neg' : ''}">${fmtBRLs(x.saldo_projetado)}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="8" class="empty"><strong>Nenhuma movimentação neste mês</strong>Troque o mês ou veja “Todos os dias”.</td></tr>`;
  document.querySelector('#fx-table tfoot').innerHTML = d.length ? `<tr><td>Total do mês</td><td class="r pos">${fmtBRL(soma('receita'))}</td><td class="r neg">${fmtBRL(soma('despesa'))}</td>
    <td class="r">${fmtBRL(soma('transferencia'))}</td><td class="r">${fmtBRLs(soma('resultado'))}</td><td class="r">${fmtBRLs(ult.saldo)}</td><td class="r">${fmtBRLs(prev)}</td><td class="r">${fmtBRLs(ult.saldo_projetado)}</td></tr>` : '';
}

// ═══════════════════════════ CLIENTES ═══════════════════════════
const cli = { lista: [], pagina: 1, editId: null };
$('cli-uf').innerHTML = '<option value="">UF</option>' + UFS.map(u => `<option>${u}</option>`).join('');
const maskDoc = v => soDigitos(v).length <= 11 ? maskCPF(v) : maskCNPJ(v);
const docOk = v => { const n = soDigitos(v).length; return n === 14 ? cnpjValido(v) : n === 11 && cpfValido(v); };

async function carregarClientes() {
  const r = await api('clientes');
  cli.lista = Array.isArray(r) ? r : [];
  if (r.error) toast(r.error, 'err');
  renderClientes();
}
function renderClientes() {
  const t = norm($('cli-f-busca').value.trim()), td = soDigitos(t);
  const lista = cli.lista.filter(x => !t || [x.nome, x.razao_social, x.cidade, x.email].some(v => norm(v).includes(t)) || (td && soDigitos(x.cnpj_cpf).includes(td)));
  const { itens, pagina, total, n } = paginar(lista, cli.pagina); cli.pagina = pagina;
  document.querySelector('#cli-table tbody').innerHTML = itens.length ? itens.map(x => {
    menuAcoes['cli' + x.id] = [
      { label: 'Visualizar', fn: () => verCliente(x) },
      { label: 'Editar', fn: () => abrirCliente(x) },
      { label: 'Apagar', danger: true, fn: () => apagarCliente(x) },
    ];
    return `<tr>
      <td><div class="who">${avatar(x.nome)}<div style="min-width:0"><span class="ttl">${esc(x.nome)}</span>${x.razao_social ? `<small>${esc(x.razao_social)}</small>` : ''}</div></div></td>
      <td class="nowrap num">${esc(x.cnpj_cpf) || '<span class="dim">—</span>'}</td>
      <td class="nowrap">${x.cidade || x.estado ? esc([x.cidade, x.estado].filter(Boolean).join(' / ')) : '<span class="dim">—</span>'}</td>
      <td class="nowrap">${x.telefone ? linkWhats(x.telefone) : '<span class="dim">—</span>'}</td>
      <td class="wrap">${x.email ? `<a class="wa" href="mailto:${esc(x.email)}">${esc(x.email)}</a>` : '<span class="dim">—</span>'}</td>
      <td class="money r">${x.total_receitas ? fmtBRL(x.total_receitas) : '<span class="dim">—</span>'}</td>
      <td class="r">${kebab('cli' + x.id)}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="7" class="empty">${cli.lista.length ? '<strong>Nada encontrado</strong>Tente outro termo.' : '<strong>Nenhum cliente cadastrado</strong>Clique em “Novo cliente” para começar.'}</td></tr>`;
  renderPager('cli-pager', pagina, total, p => { cli.pagina = p; renderClientes(); }, n);
  $('cli-count').textContent = n === 1 ? '1 cliente' : `${n} clientes`;
}
$('cli-toolbar').addEventListener('submit', e => { e.preventDefault(); carregarClientes(); });
$('cli-f-busca').addEventListener('input', () => { cli.pagina = 1; renderClientes(); });

function hintDocCli() {
  const v = $('cli-doc').value, n = soDigitos(v).length, h = $('cli-doc-hint');
  $('btn-cli-cnpj').disabled = n !== 14;
  if (!n) { h.textContent = ''; h.className = 'hint'; return; }
  if (n !== 11 && n !== 14) { h.textContent = `${n} dígitos`; h.className = 'hint'; return; }
  const ok = docOk(v);
  h.textContent = ok ? `✓ ${n === 11 ? 'CPF' : 'CNPJ'} válido` : `✕ ${n === 11 ? 'CPF' : 'CNPJ'} inválido`;
  h.className = 'hint ' + (ok ? 'ok' : 'err');
}
$('cli-doc').addEventListener('input', e => { e.target.value = maskDoc(e.target.value); hintDocCli(); });
$('cli-tel').addEventListener('input', e => { e.target.value = maskTel(e.target.value); });
$('btn-cli-cnpj').addEventListener('click', async () => {
  const dig = soDigitos($('cli-doc').value), btn = $('btn-cli-cnpj'), h = $('cli-doc-hint');
  if (!cnpjValido(dig)) return hintDocCli();
  btn.disabled = true; btn.textContent = '...';
  try {
    const r = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${dig}`);
    if (!r.ok) throw new Error(r.status === 404 ? 'CNPJ não encontrado na Receita' : 'Consulta indisponível agora');
    const d = await r.json();
    const set = (id, v) => { if (v && !$(id).value.trim()) $(id).value = v; };
    set('cli-razao', d.razao_social); set('cli-nome', d.nome_fantasia || d.razao_social); set('cli-email', (d.email || '').toLowerCase());
    set('cli-endereco', [[d.descricao_tipo_de_logradouro, d.logradouro].filter(Boolean).join(' '), d.numero, d.complemento, d.bairro, d.cep && `CEP ${d.cep}`].filter(Boolean).join(', '));
    set('cli-cidade', d.municipio); if (d.uf && !$('cli-uf').value) $('cli-uf').value = d.uf;
    if (d.ddd_telefone_1) set('cli-tel', maskTel(d.ddd_telefone_1));
    h.textContent = `✓ ${d.descricao_situacao_cadastral || 'Encontrado'} na Receita`; h.className = 'hint ' + ((d.descricao_situacao_cadastral || 'ATIVA') === 'ATIVA' ? 'ok' : 'err');
  } catch (e) { h.textContent = e.message || 'Consulta indisponível agora'; h.className = 'hint err'; }
  finally { btn.textContent = 'Buscar'; btn.disabled = false; }
});

function abrirCliente(x = null) {
  cli.editId = x ? x.id : null;
  $('cli-modal-title').textContent = x ? 'Editar Cliente' : 'Novo Cliente';
  $('cli-error').textContent = '';
  $('cli-nome').value = x ? x.nome || '' : ''; $('cli-razao').value = x ? x.razao_social || '' : '';
  $('cli-doc').value = x ? maskDoc(x.cnpj_cpf || '') : ''; $('cli-email').value = x ? x.email || '' : '';
  $('cli-tel').value = x ? maskTel(x.telefone || '') : '';
  const uf = x && x.estado ? String(x.estado).toUpperCase() : '';
  if (uf && !UFS.includes(uf) && ![...$('cli-uf').options].some(o => o.value === uf)) $('cli-uf').insertAdjacentHTML('beforeend', `<option>${esc(uf)}</option>`);
  $('cli-uf').value = uf; $('cli-cidade').value = x ? x.cidade || '' : '';
  $('cli-endereco').value = x ? x.endereco || '' : ''; $('cli-obs').value = x ? x.observacoes || '' : '';
  hintDocCli();
  abrirModal('modal-cli');
}
$('btn-novo-cliente').addEventListener('click', () => abrirCliente());
$('cli-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-cli').click(); });
$('btn-salvar-cli').addEventListener('click', async () => {
  const body = { nome: $('cli-nome').value.trim(), razao_social: $('cli-razao').value.trim(), cnpj_cpf: $('cli-doc').value.trim(),
    email: $('cli-email').value.trim(), telefone: $('cli-tel').value.trim(), estado: $('cli-uf').value, cidade: $('cli-cidade').value.trim(),
    endereco: $('cli-endereco').value.trim(), observacoes: $('cli-obs').value.trim() };
  if (!body.nome) { $('cli-error').textContent = 'Informe o nome do cliente.'; return; }
  if (body.cnpj_cpf && !docOk(body.cnpj_cpf)) { $('cli-error').textContent = 'CPF/CNPJ inválido — confira os números.'; return; }
  const btn = $('btn-salvar-cli'); btn.disabled = true;
  const r = cli.editId ? await api('clientes/' + cli.editId, 'PUT', body) : await api('clientes', 'POST', body);
  btn.disabled = false;
  if (r.error) { $('cli-error').textContent = r.error; return; }
  fecharModal('modal-cli'); toast(cli.editId ? 'Cliente atualizado' : 'Cliente cadastrado');
  carregarClientes(); carregarCadastros();
});
async function apagarCliente(x) {
  if (!await confirmar(`Apagar o cliente "${x.nome}"?${x.qtd_lanc ? `\n\nOs ${x.qtd_lanc} lançamento(s) dele continuam no sistema com o nome gravado.` : ''}`, { ok: 'Apagar' })) return;
  const r = await api('clientes/' + x.id, 'DELETE');
  if (r.error) return toast(r.error, 'err');
  toast('Cliente apagado'); carregarClientes(); carregarCadastros();
}
function verCliente(x) {
  const botoes = [{ label: 'Editar', fn: () => { fecharModal('modal-view'); abrirCliente(x); } }];
  if (pode('lancamentos') && x.qtd_lanc) botoes.unshift({ label: 'Ver lançamentos', cls: 'btn-gold', fn: () => {
    fecharModal('modal-view');
    $('tx-f-busca').value = x.nome; $('tx-f-ano').value = ''; $('tx-f-mes').value = ''; $('tx-f-categoria').value = ''; fset('tx-f-tipo', ''); $('tx-f-status').value = '';
    location.hash = 'lancamentos'; if (state.page === 'lancamentos') carregarLancamentos();
  } });
  mostrarDetalhes(x.nome, x.razao_social || '', [
    ['CPF / CNPJ', x.cnpj_cpf], ['Telefone', x.telefone], ['E-mail', x.email],
    ['Endereço', x.endereco], ['Cidade / UF', [x.cidade, x.estado].filter(Boolean).join(' / ')],
    ['Lançamentos', String(x.qtd_lanc || 0)], ['Total recebido', fmtBRL(x.total_receitas)],
    ['Observações', x.observacoes], ['Cadastrado em', fmtData(x.created_at)],
  ], botoes);
}

// ═══════════════════════════ CAIXAS / BANCO (admin) ═══════════════════════════
const cx = { lista: [], editId: null, sem: { saldo: 0, qtd: 0 } };
const CX_TIPO = { banco: 'Conta bancária', caixa: 'Caixa físico', investimento: 'Investimento' };
async function carregarCaixas() {
  const r = await api('caixas');
  cx.lista = r && Array.isArray(r.caixas) ? r.caixas : [];
  cx.sem = r && r.sem_caixa ? r.sem_caixa : { saldo: 0, qtd: 0 };
  if (r.error) toast(r.error, 'err');
  renderCaixas();
}
function renderCaixas() {
  const soma = (f, k) => cx.lista.filter(f).reduce((s, x) => s + x[k], 0);
  const tot = soma(() => true, 'saldo_atual');
  $('cx-total').textContent = fmtBRLs(tot); $('cx-total').classList.toggle('neg-on-dark', tot < 0);
  $('cx-bancos').textContent = fmtBRLs(soma(x => x.tipo !== 'caixa', 'saldo_atual'));
  $('cx-fisico').textContent = fmtBRLs(soma(x => x.tipo === 'caixa', 'saldo_atual'));
  $('cx-previsto').textContent = fmtBRLs(tot + soma(() => true, 'a_receber') - soma(() => true, 'a_pagar'));
  document.querySelector('#cx-table tbody').innerHTML = cx.lista.length ? cx.lista.map(x => {
    menuAcoes['cx' + x.id] = [
      { label: 'Editar', fn: () => abrirCaixa(x) },
      { label: 'Apagar', danger: true, fn: () => apagarCaixa(x) },
    ];
    return `<tr>
      <td><div class="who">${avatar(x.nome)}<div><span class="ttl">${esc(x.nome)}</span><small><span class="tp ${esc(x.tipo)}" style="padding:0 7px;font-size:11.5px">${CX_TIPO[x.tipo] || esc(x.tipo)}</span></small></div></div></td>
      <td>${esc(x.banco) || '<span class="dim">—</span>'}</td>
      <td class="num">${esc(x.agencia) || '<span class="dim">—</span>'}</td>
      <td class="num">${esc(x.conta) || '<span class="dim">—</span>'}</td>
      <td class="money r" style="font-weight:600">${fmtBRLs(x.saldo_inicial)}</td>
      <td class="money r ${x.saldo_atual < 0 ? 'neg' : ''}">${fmtBRLs(x.saldo_atual)}</td>
      <td class="money r">${x.a_receber ? `<span class="pos">${fmtBRL(x.a_receber)}</span>` : '<span class="dim">—</span>'}</td>
      <td class="money r">${x.a_pagar ? `<span class="neg">${fmtBRL(x.a_pagar)}</span>` : '<span class="dim">—</span>'}</td>
      <td class="r">${kebab('cx' + x.id)}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="9" class="empty"><strong>Nenhuma conta ou caixa</strong>Cadastre as contas bancárias e caixas da empresa.</td></tr>';
  $('cx-aviso').innerHTML = cx.sem.qtd ? `⚠ ${cx.sem.qtd} lançamento(s) estão sem caixa/banco definido (${fmtBRLs(cx.sem.saldo)} já quitados). Eles aparecem no Fluxo de Caixa “Todos os caixas”, mas não no saldo de nenhuma conta — edite esses lançamentos e escolha o caixa.` : '';
}
function atualizarTipoCaixa() { $('cx-banco-wrap').classList.toggle('hidden', segValor('cx-tipo-seg') === 'caixa'); }
$('cx-tipo-seg').addEventListener('change', atualizarTipoCaixa);
function abrirCaixa(x = null) {
  cx.editId = x ? x.id : null;
  $('cx-modal-title').textContent = x ? 'Editar conta / caixa' : 'Nova conta / caixa';
  $('cx-error').textContent = '';
  segSet('cx-tipo-seg', x ? x.tipo : 'banco');
  $('cx-nome').value = x ? x.nome : ''; $('cx-banco').value = x ? x.banco || '' : '';
  $('cx-agencia').value = x ? x.agencia || '' : ''; $('cx-conta').value = x ? x.conta || '' : '';
  $('cx-saldo').value = x ? x.saldo_inicial : ''; $('cx-obs').value = x ? x.observacoes || '' : '';
  atualizarTipoCaixa();
  abrirModal('modal-cx');
}
$('btn-novo-caixa').addEventListener('click', () => abrirCaixa());
$('cx-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-cx').click(); });
$('btn-salvar-cx').addEventListener('click', async () => {
  const body = { tipo: segValor('cx-tipo-seg'), nome: $('cx-nome').value.trim(), banco: $('cx-banco').value.trim(), agencia: $('cx-agencia').value.trim(),
    conta: $('cx-conta').value.trim(), saldo_inicial: parseFloat($('cx-saldo').value) || 0, observacoes: $('cx-obs').value.trim() };
  if (!body.nome) { $('cx-error').textContent = 'Informe o nome da conta ou caixa.'; return; }
  const r = cx.editId ? await api('caixas/' + cx.editId, 'PUT', body) : await api('caixas', 'POST', body);
  if (r.error) { $('cx-error').textContent = r.error; return; }
  fecharModal('modal-cx'); toast(cx.editId ? 'Conta atualizada' : 'Conta cadastrada');
  carregarCaixas(); carregarCadastros();
});
async function apagarCaixa(x) {
  if (x.qtd_lanc) { toast(`"${x.nome}" tem lançamentos. Renomeie em vez de apagar.`, 'err'); return; }
  if (!await confirmar(`Apagar "${x.nome}"?`, { ok: 'Apagar' })) return;
  const r = await api('caixas/' + x.id, 'DELETE');
  if (r.error) return toast(r.error, 'err');
  toast('Conta apagada'); carregarCaixas(); carregarCadastros();
}

// ═══════════════════════════ FORMAS DE PAGAMENTO (admin) ═══════════════════════════
const fp = { lista: [], editId: null };
const FP_TIPO = { credito: 'Cartão de crédito', debito: 'Cartão de débito', pix: 'PIX', boleto: 'Boleto', dinheiro: 'Dinheiro' };
const pct = v => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + '%';
async function carregarFormas() {
  const r = await api('formas_pagamento');
  fp.lista = Array.isArray(r) ? r : [];
  if (r.error) toast(r.error, 'err');
  renderFormas();
}
function renderFormas() {
  const tipo = fv('fp-f-tipo');
  const lista = fp.lista.filter(x => !tipo || x.tipo === tipo);
  document.querySelector('#fp-table tbody').innerHTML = lista.length ? lista.map(x => {
    menuAcoes['fp' + x.id] = [
      { label: 'Editar', fn: () => abrirForma(x) },
      { label: 'Apagar', danger: true, fn: () => apagarForma(x) },
    ];
    return `<tr>
      <td><span class="ttl">${esc(x.nome)}</span>${x.qtd_lanc ? `<small>${x.qtd_lanc} lançamento(s)</small>` : ''}</td>
      <td>${x.tipo ? `<span class="tp ${esc(x.tipo)}">${FP_TIPO[x.tipo] || esc(x.tipo)}</span>` : '<span class="tp boleto">Definir tipo</span>'}</td>
      <td>${x.banco || x.agencia ? esc([x.banco, x.agencia && 'ag. ' + x.agencia].filter(Boolean).join(' · ')) : '<span class="dim">—</span>'}</td>
      <td class="r num">${x.parcelas}x</td>
      <td class="r num">${x.dias_recebimento ? `D+${x.dias_recebimento}` : 'na hora'}</td>
      <td class="r num">${x.taxa_intermediacao ? pct(x.taxa_intermediacao) : '<span class="dim">—</span>'}</td>
      <td class="r num">${x.taxa_parcelamento ? pct(x.taxa_parcelamento) : '<span class="dim">—</span>'}</td>
      <td class="money r" style="font-weight:600">${x.tarifa_fixa ? fmtBRL(x.tarifa_fixa) : '<span class="dim">—</span>'}</td>
      <td class="r">${kebab('fp' + x.id)}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="9" class="empty">${fp.lista.length ? '<strong>Nenhuma forma desse tipo</strong>Troque o filtro.' : '<strong>Nenhuma forma de pagamento</strong>Cadastre cartões, PIX, boleto e dinheiro.'}</td></tr>`;
  $('fp-count').textContent = lista.length === 1 ? '1 forma' : `${lista.length} formas`;
}
$('fp-f-tipo').addEventListener('change', renderFormas);

function atualizarTipoForma() {
  const t = segValor('fp-tipo-seg');
  document.querySelectorAll('#modal-fp .fp-credito').forEach(el => el.classList.toggle('hidden', t !== 'credito'));
  document.querySelectorAll('#modal-fp .fp-banco').forEach(el => el.classList.toggle('hidden', t === 'dinheiro'));
  simularForma();
}
function simularForma() {
  const t = segValor('fp-tipo-seg'), taxa = parseFloat($('fp-taxa').value) || 0, tarifa = parseFloat($('fp-tarifa').value) || 0;
  const dias = parseInt($('fp-dias').value, 10) || 0, parc = parseInt($('fp-parcelas').value, 10) || 1, tparc = parseFloat($('fp-taxa-parc').value) || 0;
  const desc = taxa / 100 * 100 + tarifa;
  let html = `Numa venda de <strong>R$ 100,00</strong> a empresa recebe <strong>${fmtBRL(100 - desc)}</strong> ${dias ? `em <strong>D+${dias}</strong>` : '<strong>na hora</strong>'}`
    + (desc ? ` (desconto de ${fmtBRL(desc)} = intermediação${tarifa ? ' + tarifa fixa' : ''}).` : '.');
  if (t === 'credito' && (parc > 1 || tparc)) html += `<br/><span class="muted">Parcelado em até ${parc}x${tparc ? ` com taxa de parcelamento de ${pct(tparc)}` : ''}. A taxa de parcelamento fica registrada como referência — o desconto automático nos lançamentos usa intermediação + tarifa fixa.</span>`;
  $('fp-simula').innerHTML = html;
}
$('fp-tipo-seg').addEventListener('change', atualizarTipoForma);
['fp-taxa', 'fp-tarifa', 'fp-dias', 'fp-parcelas', 'fp-taxa-parc'].forEach(id => $(id).addEventListener('input', simularForma));
function abrirForma(x = null) {
  fp.editId = x ? x.id : null;
  $('fp-modal-title').textContent = x ? 'Editar forma de pagamento' : 'Nova forma de pagamento';
  $('fp-error').textContent = '';
  segSet('fp-tipo-seg', x && x.tipo ? x.tipo : (fv('fp-f-tipo') || 'credito'));
  $('fp-nome').value = x ? x.nome : ''; $('fp-banco').value = x ? x.banco || '' : ''; $('fp-agencia').value = x ? x.agencia || '' : '';
  $('fp-parcelas').value = x ? x.parcelas : 1; $('fp-dias').value = x ? x.dias_recebimento : 0;
  $('fp-taxa').value = x && x.taxa_intermediacao ? x.taxa_intermediacao : ''; $('fp-taxa-parc').value = x && x.taxa_parcelamento ? x.taxa_parcelamento : '';
  $('fp-tarifa').value = x && x.tarifa_fixa ? x.tarifa_fixa : '';
  atualizarTipoForma();
  abrirModal('modal-fp');
}
$('btn-nova-forma').addEventListener('click', () => abrirForma());
$('fp-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-fp').click(); });
$('btn-salvar-fp').addEventListener('click', async () => {
  const body = { tipo: segValor('fp-tipo-seg'), nome: $('fp-nome').value.trim(), banco: $('fp-banco').value.trim(), agencia: $('fp-agencia').value.trim(),
    parcelas: parseInt($('fp-parcelas').value, 10) || 1, dias_recebimento: parseInt($('fp-dias').value, 10) || 0,
    taxa_intermediacao: parseFloat($('fp-taxa').value) || 0, taxa_parcelamento: parseFloat($('fp-taxa-parc').value) || 0, tarifa_fixa: parseFloat($('fp-tarifa').value) || 0 };
  if (!body.nome) { $('fp-error').textContent = 'Informe o nome da forma de pagamento.'; return; }
  const r = fp.editId ? await api('formas_pagamento/' + fp.editId, 'PUT', body) : await api('formas_pagamento', 'POST', body);
  if (r.error) { $('fp-error').textContent = r.error; return; }
  fecharModal('modal-fp'); toast(fp.editId ? 'Forma de pagamento atualizada' : 'Forma de pagamento cadastrada');
  carregarFormas(); carregarCadastros();
});
async function apagarForma(x) {
  if (x.qtd_lanc) { toast(`"${x.nome}" está em ${x.qtd_lanc} lançamento(s). Edite em vez de apagar.`, 'err'); return; }
  if (!await confirmar(`Apagar a forma de pagamento "${x.nome}"?`, { ok: 'Apagar' })) return;
  const r = await api('formas_pagamento/' + x.id, 'DELETE');
  if (r.error) return toast(r.error, 'err');
  toast('Forma de pagamento apagada'); carregarFormas(); carregarCadastros();
}

// ═══════════════════════════ LOGS ═══════════════════════════
const LOG_ACOES = { create: ['Criação', 'act-create'], update: ['Alteração', 'act-update'], delete: ['Exclusão', 'act-delete'],
  baixa: ['Baixa', 'act-create'], update_status: ['Situação', 'act-update'], update_role: ['Nível de acesso', 'act-warn'],
  login: ['Login', 'act-auth'], logout: ['Logout', 'act-auth'], login_failed: ['Login falhou', 'act-delete'], setup_admin: ['Config. inicial', 'act-warn'] };
const LOG_AREAS = { transactions: 'Lançamentos', transfers: 'Transferências', clientes: 'Clientes', fornecedores: 'Fornecedores', parceiros: 'Parceiros',
  categorias: 'Categorias', caixas: 'Caixas / Banco', formas_pagamento: 'Formas de pagamento', users: 'Usuários', auth: 'Autenticação',
  documentos: 'Documentos', contas_receita: 'Contas de receita', contas_despesa: 'Contas de despesa', centros_custo: 'Centros de custo' };
const LOG_POR_PAGINA = 20;
// "income - Aluguel - R$1200" (formato gravado pelos lançamentos) → "Receita · Aluguel · R$ 1.200,00"
function detalheLog(d) {
  return String(d)
    .replace(/^(income|expense) - /, (_, t) => (t === 'income' ? 'Receita' : 'Despesa') + ' · ')
    .replace(/ - R\$(-?[\d.]+)/g, (_, v) => ' · ' + fmtBRL(parseFloat(v)))
    .replace(/R\$(-?\d+(?:\.\d+)?)(?=\s|$|;)/g, (_, v) => fmtBRL(parseFloat(v)))
    .replace(/tipo: (income|expense) → (income|expense)/, (_, a, b) => `tipo: ${a === 'income' ? 'receita' : 'despesa'} → ${b === 'income' ? 'receita' : 'despesa'}`)
    .replace(/em_aberto/g, 'em aberto');
}
const lg = { pagina: 1 };
$('log-f-acao').innerHTML = '<option value="">Todas as ações</option>' + Object.entries(LOG_ACOES).map(([k, [l]]) => `<option value="${k}">${l}</option>`).join('');
$('log-f-area').innerHTML = '<option value="">Todas as áreas</option>' + Object.entries(LOG_AREAS).map(([k, l]) => `<option value="${k}">${l}</option>`).join('');
async function carregarLogs() {
  const qs = new URLSearchParams({ page: lg.pagina, limit: LOG_POR_PAGINA });
  [['action', 'log-f-acao'], ['entity', 'log-f-area'], ['username', 'log-f-user'], ['from', 'log-f-de'], ['to', 'log-f-ate']].forEach(([k, id]) => { const v = $(id).value.trim(); if (v) qs.set(k, v); });
  const r = await api('logs?' + qs);
  if (r.error) { toast(r.error, 'err'); return; }
  const rows = r.rows || [];
  document.querySelector('#log-table tbody').innerHTML = rows.length ? rows.map(x => {
    const [lbl, cls] = LOG_ACOES[x.action] || [x.action, 'act-auth'];
    const dt = new Date(String(x.created_at).replace(' ', 'T'));
    return `<tr>
      <td class="nowrap num">${dt.toLocaleDateString('pt-BR')} <span class="dim">${dt.toLocaleTimeString('pt-BR')}</span></td>
      <td class="nowrap">${x.username ? `<span class="ttl">${esc(x.username)}</span>` : '<span class="dim">—</span>'}</td>
      <td><span class="st ${cls}">${esc(lbl)}</span></td>
      <td class="nowrap">${esc(LOG_AREAS[x.entity] || x.entity)}${x.entity_id ? ` <span class="dim">#${x.entity_id}</span>` : ''}</td>
      <td class="det">${x.details ? esc(detalheLog(x.details)) : '<span class="dim">—</span>'}</td>
      <td class="nowrap num dim">${esc(x.ip) || '—'}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="6" class="empty"><strong>Nenhum registro</strong>Ajuste os filtros.</td></tr>';
  renderPager('log-pager', r.page, r.pages, p => { lg.pagina = p; carregarLogs(); }, r.total, LOG_POR_PAGINA);
  $('log-count').textContent = r.total === 1 ? '1 registro' : `${r.total} registros`;
}
['log-f-acao', 'log-f-area', 'log-f-de', 'log-f-ate'].forEach(id => $(id).addEventListener('change', () => { lg.pagina = 1; carregarLogs(); }));
let logBuscaT = null;
$('log-f-user').addEventListener('input', () => { clearTimeout(logBuscaT); logBuscaT = setTimeout(() => { lg.pagina = 1; carregarLogs(); }, 300); });
$('log-toolbar').addEventListener('submit', e => { e.preventDefault(); lg.pagina = 1; carregarLogs(); });
$('log-limpar').addEventListener('click', () => { ['log-f-acao', 'log-f-area', 'log-f-user', 'log-f-de', 'log-f-ate'].forEach(id => $(id).value = ''); lg.pagina = 1; carregarLogs(); });

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
