<?php
// ── USUÁRIOS (só admin) ───────────────────────────────────────────────────────
// Dois níveis de acesso (ver PERMISSOES_USUARIO no bootstrap): o admin vê tudo e o
// usuário comum só Dashboard, Lançamentos, Fluxo de Caixa, Clientes, Fornecedores,
// Parceiros e Logs. A coluna `permissoes` não é mais usada (fica null).

function role_label(string $r): string { return $r === 'admin' ? 'Administrador' : 'Usuário'; }

function admins_restantes(int $exceto): int {
    return (int) q1("SELECT COUNT(*) n FROM users WHERE role='admin' AND id<>?", [$exceto])['n'];
}

Router::get('users', function () {
    admin_only();
    $rows = q('SELECT id, username, role, nome, foto_path, permissoes, created_at FROM users ORDER BY id');
    return array_map(fn($u) => ['id' => (int) $u['id'], 'permissoes' => perms_of($u), 'foto_path' => public_path($u['foto_path'])] + $u, $rows);
});

Router::post('users', function () {
    admin_only();
    rate_limit('create-user', 20, 600);
    $username = trim((string) b('username', '')); $password = (string) b('password', '');
    if ($err = valid_credentials($username, $password)) fail(400, $err);
    if (q1('SELECT id FROM users WHERE username=?', [$username])) fail(400, 'Usuário já existe');
    $role = b('role') === 'admin' ? 'admin' : 'user';
    $id = insert_id('INSERT INTO users (username, nome, password, role, permissoes) VALUES (?,?,?,?,NULL)',
        [$username, str_or_null(b('nome')), password_hash($password, PASSWORD_BCRYPT), $role]);
    log_action('create', 'users', $id, "$username — nível: " . role_label($role));
    return ['id' => $id, 'username' => $username, 'role' => $role];
});

Router::put('users/:id/role', function ($id) {
    admin_only();
    $id = (int) $id;
    $role = b('role') === 'admin' ? 'admin' : 'user';
    if ($id === (int) current_user()['id']) fail(400, 'Você não pode alterar a própria função');
    $atual = q1('SELECT role FROM users WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Usuário não encontrado');
    if ($atual['role'] === 'admin' && $role !== 'admin' && !admins_restantes($id)) fail(400, 'O sistema precisa de pelo menos um administrador');
    exec_sql('UPDATE users SET role=? WHERE id=?', [$role, $id]);
    log_action('update_role', 'users', $id, 'novo nível: ' . role_label($role));
    return ['ok' => true];
});

Router::put('users/:id', function ($id) {
    admin_only();
    $id = (int) $id;
    $atual = q1('SELECT * FROM users WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Usuário não encontrado');
    $username = trim((string) b('username', ''));
    if (!preg_match(USERNAME_RE, $username)) fail(400, 'Usuário deve ter 3-32 caracteres (letras, números, _ . -)');
    if (q1('SELECT id FROM users WHERE username=? AND id<>?', [$username, $id])) fail(400, 'Usuário já existe');
    $role = b('role') === 'admin' ? 'admin' : 'user';
    // Não deixa o sistema ficar sem nenhum admin (nem o admin se rebaixar).
    if ($atual['role'] === 'admin' && $role !== 'admin') {
        if ($id === (int) current_user()['id']) fail(400, 'Você não pode remover o próprio acesso de administrador');
        if (!admins_restantes($id)) fail(400, 'O sistema precisa de pelo menos um administrador');
    }
    $hash = $atual['password'];
    $senha = trim((string) b('password', ''));
    if ($senha !== '') {
        if (strlen($senha) < 6) fail(400, 'A senha deve ter ao menos 6 caracteres');
        $hash = password_hash($senha, PASSWORD_BCRYPT);
    }
    $nome = array_key_exists('nome', body()) ? str_or_null(b('nome')) : $atual['nome'];
    exec_sql('UPDATE users SET username=?, nome=?, role=?, password=?, permissoes=NULL WHERE id=?', [$username, $nome, $role, $hash, $id]);
    $mud = [];
    if ($atual['role'] !== $role) $mud[] = 'nível: ' . role_label($atual['role']) . ' → ' . role_label($role);
    if ($atual['username'] !== $username) $mud[] = "usuário: {$atual['username']} → $username";
    if ($senha !== '') $mud[] = 'senha redefinida';
    log_action('update', 'users', $id, $username . ($mud ? ' — ' . implode('; ', $mud) : ''));
    return ['id' => $id, 'username' => $username, 'role' => $role];
});

Router::delete('users/:id', function ($id) {
    admin_only();
    $id = (int) $id;
    if ($id === (int) current_user()['id']) fail(400, 'Não pode deletar a si mesmo');
    $row = q1('SELECT username, role, foto_path FROM users WHERE id=?', [$id]);
    if (!$row) fail(404, 'Usuário não encontrado');
    if ($row['role'] === 'admin' && !admins_restantes($id)) fail(400, 'O sistema precisa de pelo menos um administrador');
    exec_sql('DELETE FROM users WHERE id=?', [$id]);
    delete_upload($row['foto_path']);
    log_action('delete', 'users', $id, "{$row['username']} ({$row['role']})");
    return ['ok' => true];
});

// ── LOGS (admin e usuário) ────────────────────────────────────────────────────
Router::get('logs', function () {
    permitir('logs');
    $lim = min(max((int) qs('limit', 50), 1), 200);
    $pg = max((int) qs('page', 1), 1);
    $where = ' WHERE 1=1'; $p = [];
    if ($v = qs('action'))   { $where .= ' AND action=?';          $p[] = $v; }
    if ($v = qs('entity'))   { $where .= ' AND entity=?';          $p[] = $v; }
    if ($v = qs('username')) { $where .= ' AND username LIKE ?';   $p[] = "%$v%"; }
    if ($v = qs('from'))     { $where .= ' AND created_at>=?';     $p[] = $v; }
    if ($v = qs('to'))       { $where .= ' AND created_at<=?';     $p[] = "$v 23:59:59"; }
    $n = (int) q1("SELECT COUNT(*) n FROM logs$where", $p)['n'];
    $rows = array_map(fn($r) => ['id' => (int) $r['id'], 'entity_id' => $r['entity_id'] !== null ? (int) $r['entity_id'] : null] + $r,
        q("SELECT * FROM logs$where ORDER BY id DESC LIMIT $lim OFFSET " . (($pg - 1) * $lim), $p));
    return ['rows' => $rows, 'total' => $n, 'page' => $pg, 'limit' => $lim, 'pages' => max((int) ceil($n / $lim), 1)];
});
