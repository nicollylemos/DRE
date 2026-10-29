/* ═══════════════════════════════════════════════════════════════════════════
   OAF — Controle Financeiro · núcleo do front-end (carregado em todas as telas)
   Utilitários, chamada à API (com token CSRF), modais, menu de ações,
   paginação, máscaras, menu lateral e "Meu perfil".
   Cada tela tem seu próprio arquivo em assets/js/pages/.
   ═══════════════════════════════════════════════════════════════════════════ */
'use strict';

const APP = window.APP || {};
const $ = id => document.getElementById(id);
const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const MESES_CURTOS = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
const POR_PAGINA = 10;

// ─── FORMATAÇÃO ───────────────────────────────────────────────────────────────
// Escapa texto vindo do banco antes de ir pro innerHTML (evita XSS).
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtBRL = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtBRLs = v => (Number(v) < 0 ? '− ' : '') + fmtBRL(Math.abs(Number(v) || 0)); // com sinal
const fmtBRLk = v => { const a = Math.abs(v); return a >= 1e6 ? (v / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : a >= 1e3 ? (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : String(Math.round(v)); };
const fmtPct = (v, casas = 1) => v === null || v === undefined || !isFinite(v) ? '—' : (v * 100).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas }) + '%';
const fmtData = d => d ? String(d).slice(0, 10).split('-').reverse().join('/') : '—';
const fmtDataHora = d => { if (!d) return '—'; const dt = new Date(String(d).replace(' ', 'T')); return isNaN(dt) ? '—' : dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); };
const hojeISO = () => { const d = new Date(); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };
const soDigitos = v => String(v || '').replace(/\D/g, '');
const norm = v => String(v || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const url = p => (APP.base || '/') + String(p || '').replace(/^\//, '');
const anosLista = () => { const a = new Date().getFullYear(); return [a + 1, a, a - 1, a - 2, a - 3, a - 4]; };

// ─── API ──────────────────────────────────────────────────────────────────────
// Toda chamada leva o token CSRF. 401 → volta pro login; troca de senha pendente → tela de troca.
async function api(path, method = 'GET', body = null) {
  const opts = { method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
  if (method !== 'GET') opts.headers['X-CSRF-Token'] = APP.csrf || '';
  if (body instanceof FormData) opts.body = body;
  else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  try {
    const res = await fetch(url('api/' + path), opts);
    const data = await res.json().catch(() => ({}));
    if (res.status === 401 && !/^auth\/(login|register)/.test(path)) {
      if (document.body.classList.contains('auth-body')) return { error: data.error || 'Sessão expirada' };
      location.href = url('login?next=' + encodeURIComponent(APP.pagina || ''));
      return { error: 'Sessão expirada' };
    }
    if (res.status === 403 && (data.code === 'TROCAR_SENHA' || data.code === 'ACEITAR_TERMOS') && !document.body.classList.contains('auth-body')) { location.href = url('login?next=' + encodeURIComponent(APP.pagina || '')); return { error: data.error }; }
    if (!res.ok && !data.error) data.error = `Erro ${res.status}`;
    if (data && data.csrf) APP.csrf = data.csrf;
    return data;
  } catch (e) {
    console.error('API', path, e);
    return { error: 'Não foi possível falar com o servidor' };
  }
}
/** Download de arquivo da API (export, modelos). */
function baixar(path) { location.href = url('api/' + path); }

function toast(msg, tipo = 'ok') {
  const el = document.createElement('div');
  el.className = `toast ${tipo}`;
  el.textContent = msg;
  $('toasts').appendChild(el);
  setTimeout(() => el.remove(), tipo === 'err' ? 5000 : 2800);
}

/** Botão com spinner enquanto espera. */
function carregando(btn, on) {
  if (!btn) return;
  btn.disabled = !!on;
  btn.classList.toggle('loading', !!on);
}

// ─── PERMISSÕES (o servidor também confere) ──────────────────────────────────
const user = () => APP.user || {};
const ehAdmin = () => !!user().is_admin;
const nivel = tela => ehAdmin() ? 2 : Number((user().permissoes || {})[tela] || 0);
const pode = tela => nivel(tela) >= 1;
const podeEditar = tela => nivel(tela) >= 2;

// ─── MODAIS ───────────────────────────────────────────────────────────────────
function abrirModal(id) { $(id).classList.remove('hidden'); const f = $(id).querySelector('input:not([type=hidden]):not([hidden]):not([type=checkbox]),select'); if (f) setTimeout(() => f.focus({ preventScroll: true }), 60); }
function fecharModal(id) { $(id).classList.add('hidden'); }
function ligarModais() {
  document.querySelectorAll('.modal').forEach(m => {
    if (m.dataset.ligado) return; m.dataset.ligado = '1';
    m.addEventListener('mousedown', e => { if (e.target === m && m.id !== 'modal-confirm') fecharModal(m.id); });
    m.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); fecharModal(m.id); }));
  });
}
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  if ($('row-menu') && !$('row-menu').classList.contains('hidden')) return fecharMenu();
  const abertos = [...document.querySelectorAll('.modal:not(.hidden)')];
  if (abertos.length) fecharModal(abertos[abertos.length - 1].id);
});

// Confirmação bonita no lugar do confirm() do navegador. "extra" = HTML adicional (ex: um select).
function confirmar(msg, { titulo = 'Tem certeza?', ok = 'Confirmar', perigo = true, extra = '' } = {}) {
  return new Promise(resolve => {
    $('confirm-title').textContent = titulo;
    $('confirm-msg').textContent = msg;
    $('confirm-extra').innerHTML = extra;
    $('confirm-sim').textContent = ok;
    $('confirm-sim').className = 'btn ' + (perigo ? 'btn-danger' : 'btn-navy');
    const fim = v => { fecharModal('modal-confirm'); $('confirm-sim').onclick = $('confirm-nao').onclick = null; resolve(v); };
    $('confirm-sim').onclick = () => fim(true);
    $('confirm-nao').onclick = () => fim(false);
    abrirModal('modal-confirm');
    setTimeout(() => $('confirm-sim').focus(), 60);
  });
}

function mostrarDetalhes(titulo, sub, linhas, botoes = []) {
  $('view-title').textContent = titulo;
  $('view-sub').textContent = sub || '';
  $('view-body').innerHTML = linhas.filter(Boolean).map(([k, v, html]) => `<dt>${esc(k)}</dt><dd>${html ? v : (esc(v) || '—')}</dd>`).join('');
  // Com muitos botões o "Fechar" sai (o X do topo continua) pra caber tudo numa linha.
  $('view-foot').innerHTML = botoes.length > 3 ? '' : '<button class="btn btn-ghost" type="button" data-close>Fechar</button>';
  botoes.forEach(b => { const el = document.createElement('button'); el.type = 'button'; el.className = 'btn ' + (b.cls || 'btn-navy'); el.textContent = b.label; if (b.title) el.title = b.title; el.onclick = b.fn; $('view-foot').appendChild(el); });
  const fx = $('view-foot').querySelector('[data-close]'); if (fx) fx.onclick = () => fecharModal('modal-view');
  abrirModal('modal-view');
}

// ─── CONTROLES: pílulas, segmentados, abas ───────────────────────────────────
document.addEventListener('click', e => {
  const b = e.target.closest('.pills button');
  if (!b || b.classList.contains('on')) return;
  const g = b.closest('.pills');
  g.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
  g.dispatchEvent(new Event('change'));
});
document.addEventListener('click', e => {
  const b = e.target.closest('.seg button');
  if (!b || b.disabled) return;
  const seg = b.closest('.seg');
  seg.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
  seg.dispatchEvent(new Event('change'));
});
// Valor de um filtro, seja <select>/<input> ou grupo de pílulas.
function fv(id) { const el = $(id); if (el.tagName !== 'DIV') return el.value; const b = el.querySelector('button.on'); return b ? b.dataset.v : ''; }
function fset(id, v) { const el = $(id); if (el.tagName !== 'DIV') el.value = v; else el.querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.v === String(v))); }
function segValor(id) { const b = $(id).querySelector('button.on'); return b ? (b.dataset.v ?? b.dataset.tipo) : null; }
function segSet(id, v) { $(id).querySelectorAll('button').forEach(b => b.classList.toggle('on', (b.dataset.v ?? b.dataset.tipo) === String(v))); }
/** Abas: <div class="tabs" id=X><button data-tab=a> + .tab-pane[data-pane=a] no mesmo container. */
function ligarAbas(tabsId, onChange) {
  const tabs = $(tabsId); if (!tabs) return;
  const escopo = tabs.parentElement;
  tabs.addEventListener('click', e => {
    const b = e.target.closest('button[data-tab]'); if (!b) return;
    tabs.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
    escopo.querySelectorAll(':scope > .tab-pane, :scope > form.tab-pane').forEach(p => p.classList.toggle('hidden', p.dataset.pane !== b.dataset.tab));
    if (onChange) onChange(b.dataset.tab);
  });
}

// ─── AVATAR / PÍLULAS ────────────────────────────────────────────────────────
const TONS = [['#E8EDF5', '#1B3155'], ['#F6EEDC', '#8A6A2E'], ['#E3F1EA', '#25704A'], ['#F5E6EC', '#8B3A5A'], ['#E4EEF5', '#2F5E7E'], ['#EFE9F6', '#5B4690']];
function iniciais(nome) {
  const p = String(nome || '?').trim().split(/\s+/).filter(w => w.length > 2 || /^[A-ZÀ-Ú]/.test(w));
  return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
}
function avatar(nome, foto) {
  if (foto) return `<span class="av"><img src="${esc(url(foto))}" alt=""/></span>`;
  let h = 0; for (const c of String(nome)) h = (h * 31 + c.charCodeAt(0)) >>> 0;
  const [bg, fg] = TONS[h % TONS.length];
  return `<span class="av" style="background:${bg};color:${fg}">${esc(iniciais(nome))}</span>`;
}
const pill = (ok, sim = 'Ativo', nao = 'Desativado') => `<span class="st ${ok ? 'on' : 'off'}">${ok ? sim : nao}</span>`;
function linkWhats(v) {
  const d = soDigitos(v); if (!d) return '<span class="dim">—</span>';
  return `<a class="wa" href="https://wa.me/55${d}" target="_blank" rel="noopener" title="Abrir conversa no WhatsApp">${esc(maskTel(v))}</a>`;
}

// ─── MENU DOS 3 PONTINHOS ─────────────────────────────────────────────────────
const ICO = {
  Contrato: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
  Editar: '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
  'Ver comprovante': '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  'Anexar comprovante': '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
  'Trocar comprovante': '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
  'Remover comprovante': '<path d="M18 6 6 18M6 6l12 12"/>',
  Apagar: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
  Visualizar: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  'Dar baixa': '<path d="M20 6 9 17l-5-5"/>', Reabrir: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
  Ativar: '<path d="M20 6 9 17l-5-5"/>', Desativar: '<circle cx="12" cy="12" r="9"/><path d="M5.7 5.7l12.6 12.6"/>',
  'Redefinir senha': '<rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
  Desbloquear: '<rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 7.5-2"/>',
  Duplicar: '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
  Desfazer: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
  'Ver registros': '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
  'Nota fiscal': '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5M9 13h7M9 17h7M9 9h2"/>',
  'Anexar nota fiscal': '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5M13 11v7M9.5 14.5h7"/>',
  Vincular: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
  'Gerar lançamento': '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M12 13v4M10 15h4"/>',
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
function fecharMenu() { if (!$('row-menu')) return; $('row-menu').classList.add('hidden'); if (menuBtnAtual) menuBtnAtual.classList.remove('open'); menuBtnAtual = null; }
/** Kebab só aparece se houver ação; sem permissão de editar, a linha fica sem menu ou só com "Visualizar". */
function acoes(chave, itens) { menuAcoes[chave] = itens.filter(Boolean); return menuAcoes[chave].length ? kebab(chave) : ''; }
document.addEventListener('click', e => {
  const k = e.target.closest('.kebab');
  if (k) { e.stopPropagation(); abrirMenu(k); return; }
  if (!e.target.closest('#row-menu')) fecharMenu();
});
window.addEventListener('scroll', fecharMenu, true);
window.addEventListener('resize', fecharMenu);

// ─── PAGINAÇÃO ────────────────────────────────────────────────────────────────
function paginar(lista, pagina, porPagina = POR_PAGINA) {
  const total = Math.max(1, Math.ceil(lista.length / porPagina));
  const p = Math.min(Math.max(1, pagina), total);
  return { itens: lista.slice((p - 1) * porPagina, p * porPagina), pagina: p, total, n: lista.length };
}
function renderPager(elId, pagina, total, onChange, n = 0, porPagina = POR_PAGINA) {
  const el = $(elId);
  const ini = n ? (pagina - 1) * porPagina + 1 : 0, fim = Math.min(pagina * porPagina, n);
  const info = n ? `<span class="pg-info">Mostrando ${ini}–${fim} de ${n}</span>` : '';
  if (total <= 1) { el.innerHTML = info; el.classList.toggle('hidden', !n); return; }
  el.classList.remove('hidden');
  const nums = new Set([1, total, pagina - 1, pagina, pagina + 1].filter(k => k >= 1 && k <= total));
  const ordem = [...nums].sort((a, b) => a - b);
  let html = `<button type="button" data-p="${pagina - 1}" class="nav" ${pagina === 1 ? 'disabled' : ''} aria-label="Anterior">‹</button>`, ant = 0;
  ordem.forEach(k => { if (k - ant > 1) html += '<span class="gap">…</span>'; html += `<button type="button" data-p="${k}" class="${k === pagina ? 'on' : ''}" aria-label="Página ${k}">${k}</button>`; ant = k; });
  html += `<button type="button" data-p="${pagina + 1}" class="nav" ${pagina === total ? 'disabled' : ''} aria-label="Próxima">›</button>`;
  el.innerHTML = info + `<div class="pg">${html}</div>`;
  el.querySelectorAll('button[data-p]').forEach(b => b.onclick = () => onChange(Number(b.dataset.p)));
}

// ─── MÁSCARAS E VALIDAÇÃO DE DOCUMENTOS ──────────────────────────────────────
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
/** Agência: até 4 dígitos + dígito verificador opcional (0000-0; o DV pode ser X, como no BB). */
function maskAgencia(v) {
  const s = String(v || '').toUpperCase().replace(/[^0-9X-]/g, '');
  const [corpo, dv] = s.split('-');
  const c = (corpo || '').replace(/X/g, '').slice(0, 4);
  return s.includes('-') && c ? `${c}-${(dv || '').replace(/-/g, '').slice(0, 1)}` : c;
}
/** Conta: até 12 dígitos + dígito verificador (00000000-0; o DV pode ser X). */
function maskConta(v) {
  const s = String(v || '').toUpperCase().replace(/[^0-9X-]/g, '');
  const [corpo, dv] = s.split('-');
  const c = (corpo || '').replace(/X/g, '').slice(0, 12);
  return s.includes('-') && c ? `${c}-${(dv || '').replace(/-/g, '').slice(0, 1)}` : c;
}
/** Número com vírgula, limitado: ex. taxa 0–100 com 2 casas, tarifa até 9.999,99. */
function maskDecimal(v, inteiros, casas, max) {
  let s = String(v || '').replace(/\./g, ',').replace(/[^0-9,]/g, '');
  const i = s.indexOf(',');
  if (i >= 0) s = s.slice(0, i + 1) + s.slice(i + 1).replace(/,/g, '');
  let [a, b] = s.split(',');
  a = (a || '').replace(/^0+(?=\d)/, '').slice(0, inteiros);
  s = b !== undefined ? `${a || '0'},${b.slice(0, casas)}` : a;
  if (max !== undefined && numBR(s) > max) s = String(max).replace('.', ',');
  return s;
}
/** "1.234,56" / "3,19" / "3.19" → número. */
function numBR(v) { const s = String(v ?? '').trim(); if (!s) return 0; const n = parseFloat(s.includes(',') ? s.replace(/\./g, '').replace(',', '.') : s); return isFinite(n) ? n : 0; }
const fmtNumBR = (n, casas = 2) => n ? Number(n).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas, useGrouping: false }) : '';
/** Liga a máscara num campo (aplica ao digitar e ao colar). */
function ligarMascara(id, fn) { const el = $(id); if (!el) return; el.addEventListener('input', () => { const v = fn(el.value); if (v !== el.value) el.value = v; }); }

const maskDoc = v => soDigitos(v).length <= 11 ? maskCPF(v) : maskCNPJ(v);
const docOk = v => { const n = soDigitos(v).length; return n === 14 ? cnpjValido(v) : n === 11 && cpfValido(v); };
/** Dados públicos do CNPJ (BrasilAPI). */
async function consultarCNPJ(dig) {
  const r = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${dig}`);
  if (!r.ok) throw new Error(r.status === 404 ? 'CNPJ não encontrado na Receita' : 'Consulta indisponível agora');
  return r.json();
}

// ─── SENHA: mostrar/ocultar, força, caps lock ────────────────────────────────
document.addEventListener('click', e => {
  const b = e.target.closest('.eye'); if (!b) return;
  const inp = $(b.dataset.eye); if (!inp) return;
  const ver = inp.type === 'password';
  inp.type = ver ? 'text' : 'password';
  b.classList.toggle('on', ver);
  b.setAttribute('aria-label', ver ? 'Ocultar senha' : 'Mostrar senha');
  inp.focus();
});
/** 0..4 + texto. Mesma regra do servidor: 8+ caracteres, letras e números. */
function forcaSenha(s) {
  if (!s) return [0, ''];
  let p = 0;
  if (s.length >= 8) p++;
  if (/[a-zA-Z]/.test(s) && /\d/.test(s)) p++;
  if (/[a-z]/.test(s) && /[A-Z]/.test(s)) p++;
  if (/[^a-zA-Z0-9]/.test(s) || s.length >= 12) p++;
  if (s.length < 8 || !/[a-zA-Z]/.test(s) || !/\d/.test(s)) return [Math.min(p, 1), s.length < 8 ? `Faltam ${8 - s.length} caractere(s)` : 'Use letras e números'];
  return [p, ['', 'Fraca', 'Ok', 'Boa', 'Forte'][p]];
}
function ligarForca(inputId, barra) {
  const inp = $(inputId), el = barra || document.querySelector(`[data-forca="${inputId}"]`);
  if (!inp || !el) return;
  const upd = () => { const [n, t] = forcaSenha(inp.value); el.dataset.n = n; el.querySelector('span').textContent = t; };
  inp.addEventListener('input', upd); upd();
}
function ligarCaps(inputId, avisoId) {
  const inp = $(inputId), av = $(avisoId); if (!inp || !av) return;
  const f = e => { if (e.getModifierState) av.classList.toggle('hidden', !e.getModifierState('CapsLock')); };
  inp.addEventListener('keydown', f); inp.addEventListener('keyup', f); inp.addEventListener('blur', () => av.classList.add('hidden'));
}

// ─── CADASTROS (selects dos lançamentos etc.) ────────────────────────────────
const state = { cad: {} };
async function carregarCadastros() {
  const c = await api('cadastros');
  state.cad = c && !c.error ? c : {};
  return state.cad;
}
function opts(lista, atual, vazio = '—') {
  const arr = [...lista];
  if (atual && !arr.some(o => (o.v ?? o) === atual)) arr.push({ v: atual, l: atual });
  return `<option value="">${vazio}</option>` + arr.map(o => { const v = o.v ?? o, l = o.l ?? o; return `<option value="${esc(v)}" ${v === atual ? 'selected' : ''}>${esc(l)}</option>`; }).join('');
}
const BANCOS = ['Itaú', 'Bradesco', 'Banco do Brasil', 'Caixa Econômica', 'Santander', 'Nubank', 'Inter', 'C6 Bank', 'Sicoob', 'Sicredi', 'Mercado Pago', 'PagBank', 'Stone', 'Cielo', 'Rede', 'Getnet'];

// ─── MENU LATERAL ─────────────────────────────────────────────────────────────
(function menuLateral() {
  const t = $('side-toggle'); if (!t) return;
  t.addEventListener('click', () => {
    const fechado = document.documentElement.classList.toggle('menu-fechado');
    try { localStorage.setItem('dre.menu', fechado ? 'fechado' : 'aberto'); } catch (e) {}
  });
  // Dica com o nome do item quando o menu está só com ícones. Fica fora do menu (position:fixed)
  // para o menu poder rolar em telas baixas sem cortar a dica.
  const dica = document.createElement('div'); dica.className = 'side-dica'; document.body.appendChild(dica);
  const soIcones = () => getComputedStyle(document.querySelector('.side-lbl') || document.body).display === 'none' && innerWidth > 720;
  const mostra = b => {
    if (!soIcones()) return;
    const r = b.getBoundingClientRect();
    dica.textContent = b.dataset.label || b.title || '';
    dica.style.top = (r.top + r.height / 2) + 'px'; dica.style.left = (r.right + 12) + 'px';
    dica.classList.add('on');
  };
  document.querySelectorAll('.side-btn').forEach(b => {
    b.addEventListener('mouseenter', () => mostra(b)); b.addEventListener('focus', () => mostra(b));
    b.addEventListener('mouseleave', () => dica.classList.remove('on')); b.addEventListener('blur', () => dica.classList.remove('on'));
  });
  $('side-nav') && $('side-nav').addEventListener('scroll', () => dica.classList.remove('on'), { passive: true });
  // Mantém o item ativo visível no menu com rolagem
  const ativo = document.querySelector('.side-btn.active');
  if (ativo && ativo.scrollIntoView) ativo.scrollIntoView({ block: 'nearest' });
})();

// ─── MEU PERFIL ───────────────────────────────────────────────────────────────
function renderAvatar(el, u) {
  if (!el) return;
  if (u && u.foto_path) el.innerHTML = `<img src="${esc(url(u.foto_path))}" alt=""/>`;
  else el.textContent = ((u && (u.nome || u.username)) || '?').trim()[0].toUpperCase();
}
(function perfil() {
  if (!$('btn-perfil')) return;
  let aba = 'dados';
  const lblSalvar = () => $('btn-salvar-perfil').querySelector('.lbl');
  ligarAbas('perfil-tabs', a => { aba = a; lblSalvar().textContent = a === 'senha' ? 'Trocar senha' : 'Salvar'; });
  ligarForca('senha-nova', $('senha-forca'));
  ['perfil-telefone', 'perfil-whatsapp'].forEach(id => ligarMascara(id, maskTel));
  ligarMascara('perfil-ramal', v => soDigitos(v).slice(0, 6));
  $('perfil-trocar-foto').onclick = () => $('perfil-foto').click();
  function cabecalho(u) {
    $('perfil-titulo').textContent = u.nome || u.username;
    $('perfil-username').textContent = '@' + u.username + (u.email ? ' · ' + u.email : '');
    $('perfil-role').textContent = u.perfil_nome || '';
    const cargo = [u.cargo, u.setor].filter(Boolean).join(' · ');
    $('perfil-cargo-chip').textContent = cargo; $('perfil-cargo-chip').classList.toggle('hidden', !cargo);
  }
  $('btn-perfil').addEventListener('click', () => {
    const u = user();
    cabecalho(u);
    $('perfil-nome').value = u.nome || ''; $('perfil-email').value = u.email || '';
    $('perfil-cargo').value = u.cargo || ''; $('perfil-setor').value = u.setor || '';
    $('perfil-telefone').value = u.telefone ? maskTel(u.telefone) : ''; $('perfil-ramal').value = u.ramal || '';
    $('perfil-whatsapp').value = u.whatsapp ? maskTel(u.whatsapp) : ''; $('perfil-foto').value = '';
    ['senha-atual', 'senha-nova', 'senha-conf'].forEach(id => $(id).value = '');
    $('perfil-error').textContent = ''; $('senha-error').textContent = '';
    $('senha-info').textContent = u.senha_alterada_em ? `Sua senha foi alterada pela última vez em ${fmtDataHora(u.senha_alterada_em)}.` : 'Escolha uma senha que você não usa em outros sites.';
    $('senha-nova').dispatchEvent(new Event('input'));
    renderAvatar($('perfil-avatar'), u);
    if ($('perfil-aceite')) $('perfil-aceite').textContent = u.termos_aceitos_em ? ` (aceitos em ${fmtDataHora(u.termos_aceitos_em)}, versão ${u.termos_versao})` : '';
    document.querySelector('#perfil-tabs button[data-tab="dados"]').click();
    abrirModal('modal-perfil');
  });
  $('perfil-foto').addEventListener('change', () => { const f = $('perfil-foto').files[0]; if (f) $('perfil-avatar').innerHTML = `<img src="${URL.createObjectURL(f)}" alt=""/>`; });
  $('perfil-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-perfil').click(); });
  $('senha-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-perfil').click(); });
  $('btn-salvar-perfil').addEventListener('click', async () => {
    const btn = $('btn-salvar-perfil');
    if (aba === 'senha') {
      const body = { senha_atual: $('senha-atual').value, nova: $('senha-nova').value, confirmacao: $('senha-conf').value };
      if (!body.senha_atual) { $('senha-error').textContent = 'Informe a senha atual.'; return; }
      if (body.nova !== body.confirmacao) { $('senha-error').textContent = 'A confirmação não confere.'; return; }
      carregando(btn, true);
      const r = await api('auth/trocar-senha', 'POST', body);
      carregando(btn, false);
      if (r.error) { $('senha-error').textContent = r.error; return; }
      APP.user = Object.assign(APP.user || {}, r);
      fecharModal('modal-perfil'); toast('Senha alterada');
      return;
    }
    const tel = soDigitos($('perfil-telefone').value), wpp = soDigitos($('perfil-whatsapp').value);
    if ((tel && tel.length < 10) || (wpp && wpp.length < 10)) { $('perfil-error').textContent = 'Telefone e WhatsApp: DDD + número.'; return; }
    const fd = new FormData();
    [['nome', 'perfil-nome'], ['email', 'perfil-email'], ['cargo', 'perfil-cargo'], ['setor', 'perfil-setor'], ['ramal', 'perfil-ramal']].forEach(([k, id]) => fd.append(k, $(id).value.trim()));
    fd.append('telefone', tel); fd.append('whatsapp', wpp);
    const f = $('perfil-foto').files[0]; if (f) fd.append('foto', f);
    carregando(btn, true);
    const r = await api('auth/profile', 'POST', fd);
    carregando(btn, false);
    if (r.error) { $('perfil-error').textContent = r.error; return; }
    APP.user = Object.assign(APP.user || {}, r);
    renderAvatar($('user-avatar'), r);
    const nm = document.querySelector('.side-user-txt strong'); if (nm) nm.textContent = r.nome || r.username;
    fecharModal('modal-perfil'); toast('Perfil atualizado');
  });
  $('btn-sair').addEventListener('click', async () => {
    await api('auth/logout', 'POST');
    location.href = url('login');
  });
})();

ligarModais();
