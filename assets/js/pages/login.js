/* Tela de login: entrar, primeiro acesso e troca de senha obrigatória. */
(function () {
  const tela = document.querySelector('.auth-screen');
  const next = tela.dataset.next || '';
  const destino = r => url(next || (r && r.home) || '');

  ligarCaps('login-pass', 'login-caps');
  ligarForca('setup-pass');
  ligarForca('trocar-nova');

  // Lembra o último usuário (só o nome, nunca a senha) pra agilizar.
  try { const u = localStorage.getItem('dre.ultimoUsuario'); if (u && $('login-user')) $('login-user').value = u; } catch (e) {}
  const foco = tela.dataset.estado === 'login' ? ($('login-user').value ? $('login-pass') : $('login-user'))
    : tela.dataset.estado === 'setup' ? $('setup-nome') : $('trocar-nova');
  setTimeout(() => foco && foco.focus(), 50);

  function erro(id, msg, campo) {
    $(id).textContent = msg || '';
    if (campo) { campo.classList.add('shake'); campo.focus(); setTimeout(() => campo.classList.remove('shake'), 400); }
  }

  // ─── Entrar ───
  $('login-form').addEventListener('submit', async e => {
    e.preventDefault();
    const username = $('login-user').value.trim(), password = $('login-pass').value;
    if (!username) return erro('login-error', 'Informe o usuário.', $('login-user'));
    if (!password) return erro('login-error', 'Informe a senha.', $('login-pass'));
    erro('login-error', '');
    carregando($('btn-login'), true);
    const r = await api('auth/login', 'POST', { username, password, lembrar: $('login-lembrar').checked });
    if (r.error) {
      carregando($('btn-login'), false);
      $('login-pass').value = '';
      return erro('login-error', r.error, r.code === 'BLOQUEADO' || r.code === 'DESATIVADO' ? null : $('login-pass'));
    }
    try { localStorage.setItem('dre.ultimoUsuario', username); } catch (e) {}
    if (r.deve_trocar_senha) {
      carregando($('btn-login'), false);
      $('login-form').classList.add('hidden');
      $('trocar-form').classList.remove('hidden');
      $('trocar-form').querySelector('.auth-sub').textContent = `Olá, ${r.nome || r.username}! Você entrou com uma senha provisória. Defina uma senha só sua para continuar.`;
      setTimeout(() => $('trocar-nova').focus(), 50);
      return;
    }
    location.href = destino(r);
  });

  // ─── Primeiro acesso ───
  $('setup-form').addEventListener('submit', async e => {
    e.preventDefault();
    const body = { nome: $('setup-nome').value.trim(), username: $('setup-user').value.trim(), password: $('setup-pass').value, confirmacao: $('setup-conf').value };
    if (!body.username) return erro('setup-error', 'Escolha um usuário.', $('setup-user'));
    const [n, t] = forcaSenha(body.password);
    if (n < 2) return erro('setup-error', t || 'Senha fraca.', $('setup-pass'));
    if (body.password !== body.confirmacao) return erro('setup-error', 'As senhas não conferem.', $('setup-conf'));
    erro('setup-error', '');
    carregando($('btn-setup'), true);
    const r = await api('auth/register', 'POST', body);
    if (r.error) { carregando($('btn-setup'), false); return erro('setup-error', r.error); }
    location.href = destino(r);
  });

  // ─── Troca obrigatória ───
  $('trocar-form').addEventListener('submit', async e => {
    e.preventDefault();
    const nova = $('trocar-nova').value, conf = $('trocar-conf').value;
    const [n, t] = forcaSenha(nova);
    if (n < 2) return erro('trocar-error', t || 'Senha fraca.', $('trocar-nova'));
    if (nova !== conf) return erro('trocar-error', 'As senhas não conferem.', $('trocar-conf'));
    erro('trocar-error', '');
    carregando($('btn-trocar'), true);
    const r = await api('auth/trocar-senha', 'POST', { nova, confirmacao: conf });
    if (r.error) { carregando($('btn-trocar'), false); return erro('trocar-error', r.error); }
    location.href = destino(r);
  });
  $('btn-trocar-sair').addEventListener('click', async () => {
    await api('auth/logout', 'POST');
    location.href = url('login');
  });
})();
