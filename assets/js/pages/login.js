/* Tela de login: entrar, esqueci a senha, criar nova senha pelo link do e-mail,
   primeiro acesso, troca de senha obrigatória e aceite LGPD. */
(function () {
  const tela = document.querySelector('.auth-screen');
  const next = tela.dataset.next || '';
  const destino = r => url(next || (r && r.home) || '');
  const params = new URLSearchParams(location.search);
  const FORMS = ['login-form', 'esqueci-form', 'redefinir-form', 'setup-form', 'trocar-form', 'termos-form'];

  ligarCaps('login-pass', 'login-caps');
  ligarForca('setup-pass');
  ligarForca('trocar-nova');
  ligarForca('redefinir-nova');

  function mostrar(id, foco) {
    FORMS.forEach(f => $(f) && $(f).classList.toggle('hidden', f !== id));
    if (foco) setTimeout(() => $(foco) && $(foco).focus(), 50);
  }
  function erro(id, msg, campo) {
    $(id).textContent = msg || '';
    if (campo) { campo.classList.add('shake'); campo.focus(); setTimeout(() => campo.classList.remove('shake'), 400); }
  }
  function ok(id, msg) { const el = $(id); el.textContent = msg || ''; el.classList.toggle('hidden', !msg); }
  /** Depois de entrar: ainda falta trocar a senha provisória ou aceitar os termos? */
  function seguir(r) {
    if (r.deve_trocar_senha) {
      $('trocar-form').querySelector('.auth-sub').textContent = `Olá, ${r.nome || r.username}! Você entrou com uma senha provisória. Defina uma senha só sua para continuar.`;
      return mostrar('trocar-form', 'trocar-nova');
    }
    if (r.deve_aceitar_termos) {
      if ($('termos-nome')) $('termos-nome').textContent = ', ' + (r.nome || r.username);
      return mostrar('termos-form', 'termos-aceite');
    }
    location.href = destino(r);
  }

  // Lembra o último usuário (só o nome, nunca a senha) pra agilizar.
  try { const u = localStorage.getItem('dre.ultimoUsuario'); if (u && $('login-user')) $('login-user').value = u; } catch (e) {}
  const est = tela.dataset.estado;
  if (est === 'login' && params.get('esqueci')) { mostrar('esqueci-form', 'esqueci-login'); }
  else {
    const foco = { login: $('login-user').value ? 'login-pass' : 'login-user', setup: 'setup-nome', trocar: 'trocar-nova', termos: 'termos-aceite', redefinir: 'redefinir-nova' }[est];
    setTimeout(() => foco && $(foco) && $(foco).focus(), 50);
  }

  // ─── Entrar ───
  $('login-form').addEventListener('submit', async e => {
    e.preventDefault();
    const username = $('login-user').value.trim(), password = $('login-pass').value;
    if (!username) return erro('login-error', 'Informe o usuário.', $('login-user'));
    if (!password) return erro('login-error', 'Informe a senha.', $('login-pass'));
    erro('login-error', ''); ok('login-ok', '');
    carregando($('btn-login'), true);
    const r = await api('auth/login', 'POST', { username, password, lembrar: $('login-lembrar').checked });
    if (r.error) {
      carregando($('btn-login'), false);
      $('login-pass').value = '';
      return erro('login-error', r.error, r.code === 'BLOQUEADO' || r.code === 'DESATIVADO' ? null : $('login-pass'));
    }
    try { localStorage.setItem('dre.ultimoUsuario', username); } catch (e) {}
    carregando($('btn-login'), false);
    seguir(r);
  });

  // ─── Esqueci minha senha ───
  $('btn-esqueci').addEventListener('click', () => {
    $('esqueci-login').value = $('login-user').value.trim();
    erro('esqueci-error', ''); ok('esqueci-ok', '');
    $('btn-esqueci-enviar').classList.remove('hidden');
    mostrar('esqueci-form', 'esqueci-login');
  });
  $('btn-esqueci-voltar').addEventListener('click', () => { history.replaceState(null, '', location.pathname); mostrar('login-form', 'login-user'); });
  $('esqueci-form').addEventListener('submit', async e => {
    e.preventDefault();
    const login = $('esqueci-login').value.trim();
    if (!login) return erro('esqueci-error', 'Informe seu usuário ou e-mail.', $('esqueci-login'));
    erro('esqueci-error', '');
    carregando($('btn-esqueci-enviar'), true);
    const r = await api('auth/esqueci-senha', 'POST', { login });
    carregando($('btn-esqueci-enviar'), false);
    if (r.error) return erro('esqueci-error', r.error);
    ok('esqueci-ok', r.mensagem);
    $('btn-esqueci-enviar').classList.add('hidden');
  });

  // ─── Criar nova senha (link do e-mail) ───
  if ($('btn-redefinir')) {
    $('redefinir-form').addEventListener('submit', async e => {
      e.preventDefault();
      const nova = $('redefinir-nova').value, conf = $('redefinir-conf').value;
      const [n, t] = forcaSenha(nova);
      if (n < 2) return erro('redefinir-error', t || 'Senha fraca.', $('redefinir-nova'));
      if (nova !== conf) return erro('redefinir-error', 'As senhas não conferem.', $('redefinir-conf'));
      erro('redefinir-error', '');
      carregando($('btn-redefinir'), true);
      const r = await api('auth/redefinir-senha', 'POST', { token: $('redefinir-token').value, nova, confirmacao: conf });
      carregando($('btn-redefinir'), false);
      if (r.error) return erro('redefinir-error', r.error);
      history.replaceState(null, '', location.pathname);
      $('login-user').value = r.username || ''; $('login-pass').value = '';
      ok('login-ok', 'Senha nova criada! Agora é só entrar com ela.');
      mostrar('login-form', 'login-pass');
    });
  }

  // ─── Primeiro acesso ───
  $('setup-form').addEventListener('submit', async e => {
    e.preventDefault();
    const body = { nome: $('setup-nome').value.trim(), username: $('setup-user').value.trim(), email: $('setup-email').value.trim(),
      password: $('setup-pass').value, confirmacao: $('setup-conf').value, aceite_termos: $('setup-aceite').checked };
    if (!body.username) return erro('setup-error', 'Escolha um usuário.', $('setup-user'));
    const [n, t] = forcaSenha(body.password);
    if (n < 2) return erro('setup-error', t || 'Senha fraca.', $('setup-pass'));
    if (body.password !== body.confirmacao) return erro('setup-error', 'As senhas não conferem.', $('setup-conf'));
    if (!body.aceite_termos) return erro('setup-error', 'Para continuar, aceite o Termo de uso e a Política de privacidade.', $('setup-aceite'));
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
    carregando($('btn-trocar'), false);
    if (r.error) return erro('trocar-error', r.error);
    seguir(r);
  });

  // ─── Aceite LGPD ───
  $('termos-form').addEventListener('submit', async e => {
    e.preventDefault();
    if (!$('termos-aceite').checked) return erro('termos-error', 'Marque que você leu e concorda para continuar.', $('termos-aceite'));
    erro('termos-error', '');
    carregando($('btn-termos'), true);
    const r = await api('auth/aceitar-termos', 'POST', { aceite: true });
    carregando($('btn-termos'), false);
    if (r.error) return erro('termos-error', r.error);
    location.href = destino(r);
  });

  document.querySelectorAll('[data-sair]').forEach(b => b.addEventListener('click', async () => {
    await api('auth/logout', 'POST');
    location.href = url('login');
  }));
})();
