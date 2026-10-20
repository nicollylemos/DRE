<?php
// ── AUTH ──────────────────────────────────────────────────────────────────────
const USERNAME_RE = '/^[a-zA-Z0-9_.-]{3,32}$/';

function valid_credentials($username, $password): ?string {
    if (!is_string($username) || !is_string($password)) return 'Campos obrigatórios';
    if (!preg_match(USERNAME_RE, $username)) return 'Usuário deve ter 3-32 caracteres (letras, números, _ . -)';
    if (strlen($password) < 6) return 'Senha deve ter pelo menos 6 caracteres';
    return null;
}

function login_user(array $u): void {
    start_session();
    session_regenerate_id(true); // evita fixação de sessão
    $_SESSION['uid'] = (int) $u['id'];
    $GLOBALS['CURRENT_USER'] = $u;
}

// Cadastro público só existe pro "bootstrap" (primeiro admin, banco vazio).
// Depois disso, novos usuários só são criados por um admin (POST /api/users).
Router::post('auth/register', function () {
    rate_limit('register', 5, 600);
    if ((int) q1('SELECT COUNT(*) n FROM users')['n'] > 0)
        fail(403, 'Cadastro público desativado. Peça a um administrador para criar seu acesso.');
    $username = b('username'); $password = b('password');
    if ($err = valid_credentials($username, $password)) fail(400, $err);
    $id = insert_id('INSERT INTO users (username, password, role) VALUES (?,?,?)', [$username, password_hash($password, PASSWORD_BCRYPT), 'admin']);
    $u = q1('SELECT * FROM users WHERE id=?', [$id]);
    login_user($u);
    log_action('setup_admin', 'auth', $id, "Primeiro admin criado: $username");
    return user_out($u);
});

Router::get('auth/setup-status', function () {
    return ['needsSetup' => (int) q1('SELECT COUNT(*) n FROM users')['n'] === 0, 'version' => APP_VERSION];
});

Router::post('auth/login', function () {
    rate_limit('login', 8, 600);
    $username = b('username'); $password = b('password');
    if (!is_string($username) || !is_string($password) || $username === '' || $password === '') fail(400, 'Credenciais inválidas');
    $u = q1('SELECT * FROM users WHERE username=?', [$username]);
    // Hashes do bcryptjs ($2a$/$2b$) são aceitos pelo password_verify — quem
    // migrar o banco da versão Node continua entrando com a mesma senha.
    if (!$u || !password_verify($password, $u['password'])) {
        log_action('login_failed', 'auth', null, "Tentativa falhou para usuário: $username", $username);
        fail(401, 'Credenciais inválidas');
    }
    if (password_needs_rehash($u['password'], PASSWORD_BCRYPT))
        exec_sql('UPDATE users SET password=? WHERE id=?', [password_hash($password, PASSWORD_BCRYPT), $u['id']]);
    login_user($u);
    log_action('login', 'auth', $u['id']);
    return user_out($u);
});

Router::post('auth/logout', function () {
    $u = auth();
    log_action('logout', 'auth', $u['id']);
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
    session_destroy();
    return ['ok' => true];
});

Router::get('auth/me', fn() => user_out(auth()));

// Edita o PRÓPRIO perfil (nome, foto, senha). Aceita POST porque o PHP não lê
// multipart/form-data em PUT — o front manda POST com FormData.
$perfil = function () {
    $u = auth();
    $atual = q1('SELECT * FROM users WHERE id=?', [$u['id']]);
    $nome = str_or_null(b('nome'));
    $hash = $atual['password'];
    $senha = trim((string) b('password', ''));
    if ($senha !== '') {
        if (strlen($senha) < 6) fail(400, 'A nova senha deve ter ao menos 6 caracteres');
        $hash = password_hash($senha, PASSWORD_BCRYPT);
    }
    $fotoPath = $atual['foto_path']; $fotoNome = $atual['foto_nome'];
    if ($foto = save_upload('foto', 'perfil', EXT_IMAGEM, 8 * 1024 * 1024)) {
        delete_upload($atual['foto_path']);
        $fotoPath = $foto['path']; $fotoNome = $foto['nome'];
    }
    exec_sql('UPDATE users SET nome=?, password=?, foto_path=?, foto_nome=? WHERE id=?', [$nome, $hash, $fotoPath, $fotoNome, $u['id']]);
    log_action('update', 'users', $u['id'], 'Atualizou o próprio perfil');
    return user_out(q1('SELECT id, username, role, nome, foto_path, foto_nome, permissoes FROM users WHERE id=?', [$u['id']]));
};
Router::post('auth/profile', $perfil);
Router::put('auth/profile', $perfil);
