/* Usuários e níveis de acesso (só administrador). */
(function () {
  const st = { usuarios: [], perfis: [], telas: [], pagina: 1, editUsr: null, editPerf: null, resetUsr: null, matriz: {} };
  const NIVEIS = ['Sem acesso', 'Ver', 'Editar'];

  ligarAbas('usr-tabs', aba => {
    $('btn-novo-usuario').classList.toggle('hidden', aba !== 'usuarios');
    $('btn-novo-perfil').classList.toggle('hidden', aba !== 'perfis');
    try { sessionStorage.setItem('dre.usrAba', aba); } catch (e) {}
  });
  try { if (sessionStorage.getItem('dre.usrAba') === 'perfis') document.querySelector('#usr-tabs [data-tab=perfis]').click(); } catch (e) {}

  async function carregar() {
    const [u, p] = await Promise.all([api('users'), api('perfis')]);
    if (u.error) toast(u.error, 'err');
    if (p.error) toast(p.error, 'err');
    st.usuarios = Array.isArray(u) ? u : [];
    st.perfis = p.perfis || []; st.telas = p.telas || [];
    const sel = $('usr-f-perfil'), atual = sel.value;
    sel.innerHTML = '<option value="">Todos os níveis</option>' + st.perfis.map(x => `<option value="${x.id}">${esc(x.nome)}</option>`).join('');
    sel.value = atual;
    $('usr-n').textContent = st.usuarios.filter(x => x.ativo).length;
    $('perf-n').textContent = st.perfis.length;
    renderUsuarios(); renderPerfis();
  }

  // ═══════════ USUÁRIOS ═══════════
  function situacao(u) {
    if (!u.ativo) return '<span class="st off">Desativado</span>';
    if (u.bloqueado) return '<span class="st late" title="Errou a senha várias vezes">Bloqueado</span>';
    if (u.deve_trocar_senha) return '<span class="st open" title="Ainda vai criar a própria senha">Aguardando 1º acesso</span>';
    return '<span class="st on">Ativo</span>';
  }
  function renderUsuarios() {
    const sit = fv('usr-f-sit'), perf = $('usr-f-perfil').value, t = norm($('usr-f-busca').value.trim());
    const lista = st.usuarios.filter(u => (sit === '' || String(u.ativo) === sit) && (!perf || String(u.perfil_id) === perf)
      && (!t || [u.username, u.nome, u.email].some(v => norm(v).includes(t))));
    const { itens, pagina, total, n } = paginar(lista, st.pagina); st.pagina = pagina;
    const eu = user().id;
    document.querySelector('#usr-table tbody').innerHTML = itens.length ? itens.map(u => {
      const sou = u.id === eu;
      const menu = acoes('usr' + u.id, [
        { label: 'Editar', fn: () => abrirUsuario(u) },
        { label: 'Redefinir senha', fn: () => abrirReset(u) },
        u.bloqueado && { label: 'Desbloquear', fn: () => desbloquear(u) },
        !sou && { label: u.ativo ? 'Desativar' : 'Ativar', fn: () => alternar(u) },
        !sou && { label: 'Apagar', danger: true, fn: () => apagar(u) },
      ]);
      return `<tr class="${u.ativo ? '' : 'inativo'}">
        <td><div class="who">${avatar(u.nome || u.username, u.foto_path)}<div><span class="ttl">${esc(u.nome || u.username)}${sou ? ' <span class="you">você</span>' : ''}</span><small>@${esc(u.username)}${u.cargo ? ' · ' + esc(u.cargo) + (u.setor ? ' (' + esc(u.setor) + ')' : '') : ''}${u.email ? ' · ' + esc(u.email) : ''}</small></div></div></td>
        <td><span class="tp ${u.is_admin ? 'admin' : 'ambos'}">${esc(u.perfil_nome || 'Sem nível')}</span></td>
        <td>${situacao(u)}</td>
        <td class="nowrap">${u.ultimo_login ? `${fmtDataHora(u.ultimo_login)}${u.ultimo_ip ? `<small>${esc(u.ultimo_ip)}</small>` : ''}` : '<span class="dim">Nunca entrou</span>'}</td>
        <td class="nowrap num">${fmtData(u.created_at)}</td>
        <td class="r">${menu}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="empty"><strong>Nenhum usuário encontrado</strong>Tente outro termo ou filtro.</td></tr>';
    renderPager('usr-pager', pagina, total, p => { st.pagina = p; renderUsuarios(); }, n);
    $('usr-count').textContent = n === 1 ? '1 usuário' : `${n} usuários`;
  }
  $('usr-toolbar').addEventListener('submit', e => { e.preventDefault(); carregar(); });
  ['usr-f-sit', 'usr-f-perfil'].forEach(id => $(id).addEventListener('change', () => { st.pagina = 1; renderUsuarios(); }));
  $('usr-f-busca').addEventListener('input', () => { st.pagina = 1; renderUsuarios(); });

  // Resumo do nível escolhido no formulário do usuário
  function resumoNivel(perfil) {
    if (!perfil) return '';
    if (perfil.is_admin) return `<p><b>${esc(perfil.nome)}</b> — acesso total, inclusive Usuários e acessos.</p>`;
    const ed = st.telas.filter(t => perfil.permissoes[t.chave] === 2).map(t => `<span class="chip">${esc(t.label)}</span>`);
    const ve = st.telas.filter(t => perfil.permissoes[t.chave] === 1).map(t => `<span class="chip gray">${esc(t.label)}</span>`);
    return `<p><b>${esc(perfil.nome)}</b>${perfil.descricao ? ' — ' + esc(perfil.descricao) : ''}</p>`
      + (ed.length ? `<div class="nivel-l"><small>Pode editar</small><div class="chips">${ed.join('')}</div></div>` : '')
      + (ve.length ? `<div class="nivel-l"><small>Só consulta</small><div class="chips">${ve.join('')}</div></div>` : '');
  }
  function atualizarResumo() { $('usr-nivel').innerHTML = resumoNivel(st.perfis.find(p => String(p.id) === $('usr-perfil').value)); }
  $('usr-perfil').addEventListener('change', atualizarResumo);

  function atualizarSenhaModo() {
    const definir = segValor('usr-senha-modo') === 'definir';
    $('usr-senha-def').classList.toggle('hidden', !definir);
    $('usr-trocar-wrap').classList.toggle('hidden', !definir);
    $('usr-senha-hint').textContent = definir ? 'A senha precisa ter pelo menos 8 caracteres, com letras e números.' : 'Uma senha provisória será gerada e mostrada uma única vez. No primeiro acesso a pessoa cria a senha dela.';
  }
  $('usr-senha-modo').addEventListener('change', atualizarSenhaModo);
  ligarForca('usr-senha');

  function abrirUsuario(u = null) {
    st.editUsr = u ? u.id : null;
    $('usr-modal-title').textContent = u ? `Editar ${u.nome || u.username}` : 'Novo usuário';
    $('usr-username').value = u ? u.username : ''; $('usr-nome').value = u ? (u.nome || '') : ''; $('usr-email').value = u ? (u.email || '') : '';
    const padrao = (st.perfis.find(p => p.nome === 'Usuário') || st.perfis.find(p => !p.is_admin) || st.perfis[0] || {}).id;
    $('usr-perfil').innerHTML = st.perfis.map(p => `<option value="${p.id}">${esc(p.nome)}${p.is_admin ? ' (acesso total)' : ''}</option>`).join('');
    $('usr-perfil').value = String(u ? u.perfil_id : padrao);
    $('usr-perfil').disabled = !!(u && u.id === user().id && u.is_admin);
    $('usr-senha-bloco').classList.toggle('hidden', !!u);
    segSet('usr-senha-modo', 'gerar'); $('usr-senha').value = ''; $('usr-trocar').checked = true;
    $('usr-senha').dispatchEvent(new Event('input'));
    atualizarSenhaModo(); atualizarResumo();
    $('usr-error').textContent = '';
    abrirModal('modal-usr');
    setTimeout(() => $(u ? 'usr-nome' : 'usr-nome').focus(), 60);
  }
  $('btn-novo-usuario').addEventListener('click', () => abrirUsuario());
  // Sugere o login a partir do nome (ex: "Maria Silva" → maria.silva)
  $('usr-nome').addEventListener('input', () => {
    if (st.editUsr || $('usr-username').dataset.manual) return;
    const p = norm($('usr-nome').value).replace(/[^a-z\s]/g, '').trim().split(/\s+/).filter(Boolean);
    $('usr-username').value = p.length ? (p.length > 1 ? `${p[0]}.${p[p.length - 1]}` : p[0]).slice(0, 32) : '';
  });
  $('usr-username').addEventListener('input', () => { $('usr-username').dataset.manual = $('usr-username').value ? '1' : ''; });
  $('usr-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-usr').click(); });
  $('btn-salvar-usr').addEventListener('click', async () => {
    const body = { username: $('usr-username').value.trim(), nome: $('usr-nome').value.trim(), email: $('usr-email').value.trim(), perfil_id: Number($('usr-perfil').value) };
    if (!body.username) { $('usr-error').textContent = 'Informe o usuário (login).'; return; }
    if (!st.editUsr) {
      body.senha_modo = segValor('usr-senha-modo');
      if (body.senha_modo === 'definir') {
        body.password = $('usr-senha').value; body.trocar_senha = $('usr-trocar').checked;
        const [nv, txt] = forcaSenha(body.password);
        if (nv < 2) { $('usr-error').textContent = txt || 'Senha fraca.'; return; }
      }
    }
    const btn = $('btn-salvar-usr'); carregando(btn, true);
    const r = st.editUsr ? await api('users/' + st.editUsr, 'PUT', body) : await api('users', 'POST', body);
    carregando(btn, false);
    if (r.error) { $('usr-error').textContent = r.error; return; }
    $('usr-username').dataset.manual = '';
    fecharModal('modal-usr'); toast(st.editUsr ? 'Usuário atualizado' : 'Usuário criado');
    if (r.senha_provisoria) mostrarSenha(body.nome || body.username, r.senha_provisoria);
    carregar();
  });

  // Redefinir senha
  function atualizarResetModo() {
    const def = segValor('reset-modo') === 'definir';
    $('reset-senha-wrap').classList.toggle('hidden', !def);
    $('reset-trocar-wrap').classList.toggle('hidden', !def);
  }
  $('reset-modo').addEventListener('change', atualizarResetModo);
  function abrirReset(u) {
    st.resetUsr = u;
    $('reset-sub').textContent = `${u.nome || u.username} (@${u.username})`;
    segSet('reset-modo', 'gerar'); $('reset-senha').value = ''; $('reset-trocar').checked = true; $('reset-error').textContent = '';
    atualizarResetModo();
    abrirModal('modal-reset');
  }
  $('reset-form').addEventListener('submit', e => { e.preventDefault(); $('btn-reset').click(); });
  $('btn-reset').addEventListener('click', async () => {
    const u = st.resetUsr, body = { senha_modo: segValor('reset-modo') };
    if (body.senha_modo === 'definir') {
      body.password = $('reset-senha').value; body.trocar_senha = $('reset-trocar').checked;
      const [nv, txt] = forcaSenha(body.password);
      if (nv < 2) { $('reset-error').textContent = txt || 'Senha fraca.'; return; }
    }
    const btn = $('btn-reset'); carregando(btn, true);
    const r = await api(`users/${u.id}/senha`, 'POST', body);
    carregando(btn, false);
    if (r.error) { $('reset-error').textContent = r.error; return; }
    fecharModal('modal-reset');
    if (r.senha_provisoria) mostrarSenha(u.nome || u.username, r.senha_provisoria); else toast('Senha redefinida');
    carregar();
  });
  function mostrarSenha(quem, senha) {
    $('senha-gerada-msg').textContent = `Senha provisória de ${quem}:`;
    $('senha-gerada').textContent = senha;
    $('btn-copiar-senha').textContent = 'Copiar';
    abrirModal('modal-senha-gerada');
  }
  $('btn-copiar-senha').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText($('senha-gerada').textContent); $('btn-copiar-senha').textContent = 'Copiado ✓'; }
    catch (e) { const r = document.createRange(); r.selectNode($('senha-gerada')); getSelection().removeAllRanges(); getSelection().addRange(r); }
  });

  async function alternar(u) {
    if (u.ativo && !await confirmar(`Desativar o acesso de "${u.nome || u.username}"? A pessoa sai do sistema na hora e não consegue mais entrar (o histórico dela continua nos logs).`, { titulo: 'Desativar acesso', ok: 'Desativar' })) return;
    const r = await api(`users/${u.id}/situacao`, 'PATCH', { ativo: u.ativo ? 0 : 1 });
    if (r.error) return toast(r.error, 'err');
    toast(u.ativo ? 'Acesso desativado' : 'Acesso reativado'); carregar();
  }
  async function desbloquear(u) {
    const r = await api(`users/${u.id}/desbloquear`, 'POST');
    if (r.error) return toast(r.error, 'err');
    toast('Usuário desbloqueado'); carregar();
  }
  async function apagar(u) {
    if (!await confirmar(`Apagar o usuário "${u.username}"? Ele perde o acesso na hora. Se a ideia é só bloquear, use "Desativar".`, { ok: 'Apagar' })) return;
    const r = await api('users/' + u.id, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Usuário apagado'); carregar();
  }

  // ═══════════ NÍVEIS DE ACESSO ═══════════
  function renderPerfis() {
    const grupos = [...new Set(st.telas.map(t => t.grupo))];
    const ICO = {
      2: '<svg viewBox="0 0 24 24"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/></svg>',
      1: '<svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
    };
    const celula = n => n === 2 ? `<span class="nv e">${ICO[2]}Editar</span>` : n === 1 ? `<span class="nv v">${ICO[1]}Ver</span>` : '<span class="nv n">—</span>';
    const nivelDe = (p, t) => p.is_admin ? (t.editavel ? 2 : 1) : (p.permissoes[t.chave] || 0);
    const head = st.perfis.map(p => `<th class="nv-col${p.is_admin ? ' adm' : ''}">
        <div class="nv-h"><button type="button" class="nv-nome" data-pf="${p.id}" title="Editar nível">${esc(p.nome)}</button>${acoes('pf' + p.id, [
          { label: 'Editar', fn: () => abrirPerfil(p) },
          !p.is_admin && { label: 'Duplicar', fn: () => abrirPerfil(p, true) },
          !p.is_admin && !p.sistema && { label: 'Apagar', danger: true, fn: () => apagarPerfil(p) },
        ])}</div>
        <small class="nv-desc" title="${esc(p.descricao || '')}">${esc(p.descricao || (p.is_admin ? 'Acesso total' : ''))}</small>
        <small class="nv-qtd">${p.qtd_usuarios} ${p.qtd_usuarios === 1 ? 'usuário' : 'usuários'}${p.sistema ? ' · padrão' : ''}</small></th>`).join('');
    let corpo = '';
    grupos.forEach(g => {
      corpo += `<tr class="nv-g"><td colspan="${st.perfis.length + 1}">${esc(g)}</td></tr>`;
      st.telas.filter(t => t.grupo === g).forEach(t => {
        corpo += `<tr><td class="nv-tela" ${t.editar_desc ? `title="Editar = ${esc(t.editar_desc)}"` : ''}>${esc(t.label)}</td>${st.perfis.map(p => `<td>${celula(nivelDe(p, t))}</td>`).join('')}</tr>`;
      });
      if (g === 'Sistema') corpo += `<tr><td class="nv-tela" title="Só administradores gerenciam usuários e níveis">Usuários e acessos</td>${st.perfis.map(p => `<td>${celula(p.is_admin ? 2 : 0)}</td>`).join('')}</tr>`;
    });
    $('perfis-grid').innerHTML = `<table class="nv-tbl"><thead><tr><th class="nv-tela-h">Tela</th>${head}</tr></thead><tbody>${corpo}</tbody></table>`;
    $('perfis-grid').querySelectorAll('.nv-nome').forEach(b => b.onclick = () => abrirPerfil(st.perfis.find(p => String(p.id) === b.dataset.pf)));
  }

  function renderMatriz(admin) {
    const grupos = [...new Set(st.telas.map(t => t.grupo))];
    $('perf-matriz').innerHTML = grupos.map(g => `<div class="pm-grupo"><h4>${esc(g)}</h4>${st.telas.filter(t => t.grupo === g).map(t => {
      const v = admin ? (t.editavel ? 2 : 1) : (st.matriz[t.chave] || 0);
      const btns = [0, 1, 2].map(n => `<button type="button" data-n="${n}" class="${v === n ? 'on' : ''} n${n}" ${admin || (n === 2 && !t.editavel) ? 'disabled' : ''}>${NIVEIS[n]}</button>`).join('');
      return `<div class="pm-linha"><div class="pm-t"><b>${esc(t.label)}</b>${t.editavel && t.editar_desc ? `<small>Editar = ${esc(t.editar_desc)}</small>` : (!t.editavel ? '<small>Tela de consulta</small>' : '')}</div>
        <div class="seg sm pm-seg" data-tela="${esc(t.chave)}">${btns}</div></div>`;
    }).join('')}</div>`).join('');
    $('perf-matriz').querySelectorAll('.pm-seg button').forEach(b => b.onclick = () => {
      const ch = b.closest('.pm-seg').dataset.tela;
      st.matriz[ch] = Number(b.dataset.n);
      b.parentElement.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
    });
  }
  function abrirPerfil(p = null, duplicar = false) {
    st.editPerf = p && !duplicar ? p.id : null;
    const admin = !!(p && p.is_admin && !duplicar);
    $('perf-modal-title').textContent = duplicar ? 'Novo nível (cópia)' : p ? `Editar "${p.nome}"` : 'Novo nível de acesso';
    $('perf-nome').value = p ? (duplicar ? `${p.nome} (cópia)` : p.nome) : '';
    $('perf-desc').value = p ? (p.descricao || '') : '';
    st.matriz = p ? Object.assign({}, p.permissoes) : {};
    $('perf-admin-aviso').classList.toggle('hidden', !admin);
    $('perf-atalhos').classList.toggle('hidden', admin);
    renderMatriz(admin);
    $('perf-error').textContent = '';
    abrirModal('modal-perfil-nivel');
  }
  $('btn-novo-perfil').addEventListener('click', () => abrirPerfil());
  $('perf-atalhos').addEventListener('click', e => {
    const b = e.target.closest('[data-tudo]'); if (!b) return;
    const n = Number(b.dataset.tudo);
    st.telas.forEach(t => { st.matriz[t.chave] = n === 2 && !t.editavel ? 1 : n; });
    renderMatriz(false);
  });
  $('perf-form').addEventListener('submit', e => { e.preventDefault(); $('btn-salvar-perf').click(); });
  $('btn-salvar-perf').addEventListener('click', async () => {
    const body = { nome: $('perf-nome').value.trim(), descricao: $('perf-desc').value.trim(), permissoes: st.matriz };
    if (!body.nome) { $('perf-error').textContent = 'Dê um nome para o nível.'; return; }
    const btn = $('btn-salvar-perf'); carregando(btn, true);
    const r = st.editPerf ? await api('perfis/' + st.editPerf, 'PUT', body) : await api('perfis', 'POST', body);
    carregando(btn, false);
    if (r.error) { $('perf-error').textContent = r.error; return; }
    fecharModal('modal-perfil-nivel'); toast(st.editPerf ? 'Nível atualizado' : 'Nível criado');
    carregar();
  });
  async function apagarPerfil(p) {
    let extra = '';
    const outros = st.perfis.filter(x => x.id !== p.id);
    if (p.qtd_usuarios) extra = `<label class="field" style="text-align:left;margin-top:12px"><span>Mover os ${p.qtd_usuarios} usuário(s) para</span><select id="perf-mover">${outros.map(x => `<option value="${x.id}">${esc(x.nome)}</option>`).join('')}</select></label>`;
    if (!await confirmar(`Apagar o nível "${p.nome}"?`, { ok: 'Apagar', extra })) return;
    const mover = $('perf-mover') ? $('perf-mover').value : '';
    const r = await api(`perfis/${p.id}${mover ? '?mover_para=' + mover : ''}`, 'DELETE');
    if (r.error) return toast(r.error, 'err');
    toast('Nível apagado'); carregar();
  }

  carregar();
})();
