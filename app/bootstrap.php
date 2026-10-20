<?php
/**
 * bootstrap.php — núcleo do backend PHP (substitui server.js + database.js).
 * Carrega .env, abre a conexão PDO, inicia a sessão e expõe os helpers
 * usados por todas as rotas (resposta JSON, auth, permissões, log, upload).
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('STORAGE_DIR', ROOT_DIR . '/storage');

// ─── .env simples ────────────────────────────────────────────────────────────
(function () {
    $envPath = ROOT_DIR . '/.env';
    if (!is_file($envPath)) return;
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
        if (getenv($k) === false) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
})();

function env(string $k, $default = null) {
    $v = getenv($k);
    return ($v === false || $v === '') ? $default : $v;
}

// Versão do sistema — aparece na tela de login. Atualize a cada entrega.
define('APP_VERSION', '2.0.0');
define('IS_PROD', env('APP_ENV', 'production') === 'production');
date_default_timezone_set(env('APP_TZ', 'America/Sao_Paulo'));

// Em produção não vaza stack trace/erro de SQL pro navegador.
ini_set('display_errors', IS_PROD ? '0' : '1');
error_reporting(E_ALL);

// ─── Banco ───────────────────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = env('DB_HOST', 'localhost');
    $port = (int) env('DB_PORT', 3306);
    $name = env('DB_NAME', 'dre_oaf');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
    } catch (PDOException $e) {
        // 1049 = banco não existe: tenta criar (igual ao Node fazia). Em hospedagem
        // compartilhada o usuário pode não ter permissão — aí o erro sobe normal.
        if ((int) ($e->errorInfo[1] ?? 0) !== 1049) throw $e;
        $tmp = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, $opts);
        $tmp->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
    }
    $pdo->exec("SET time_zone = '-03:00'");
    return $pdo;
}

function q(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st->columnCount() ? $st->fetchAll() : [];
}
function q1(string $sql, array $params = []): ?array {
    $rows = q($sql, $params);
    return $rows[0] ?? null;
}
function exec_sql(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st->rowCount();
}
function insert_id(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return (int) db()->lastInsertId();
}

// ─── Resposta / request ──────────────────────────────────────────────────────
class HttpError extends Exception {
    public int $status;
    public function __construct(int $status, string $msg) { parent::__construct($msg); $this->status = $status; }
}
function json_out($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}
function fail(int $status, string $msg): void { throw new HttpError($status, $msg); }

/** Corpo da requisição: JSON (fetch do front) ou form-data (uploads). */
function body(): array {
    static $b = null;
    if ($b !== null) return $b;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $b = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($b)) $b = [];
    } else {
        $b = $_POST;
    }
    return $b;
}
function b(string $k, $default = null) { $b = body(); return array_key_exists($k, $b) ? $b[$k] : $default; }
/** Primeiro valor não-vazio entre várias chaves (o front manda nomes antigos e novos). */
function bf(array $keys, $default = '') {
    foreach ($keys as $k) { $v = b($k); if ($v !== null && $v !== '') return $v; }
    return $default;
}
function qs(string $k, $default = null) { return isset($_GET[$k]) && $_GET[$k] !== '' ? $_GET[$k] : $default; }
function client_ip(): string {
    return substr((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
}

// ─── Sessão (substitui o JWT em cookie) ──────────────────────────────────────
// Cookie httpOnly + SameSite=Strict + Secure quando em HTTPS. Mesma proteção
// do cookie JWT anterior, mas sem segredo pra vazar e com logout real no servidor.
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $sessDir = STORAGE_DIR . '/sessions';
    if (!is_dir($sessDir)) @mkdir($sessDir, 0770, true);
    if (is_dir($sessDir) && is_writable($sessDir)) session_save_path($sessDir);
    session_name('dre_sess');
    session_set_cookie_params([
        'lifetime' => 7 * 24 * 3600,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.gc_maxlifetime', (string) (7 * 24 * 3600));
    ini_set('session.use_strict_mode', '1');
    session_start();
}

$GLOBALS['CURRENT_USER'] = null;
function current_user(): ?array { return $GLOBALS['CURRENT_USER']; }

/**
 * Busca o usuário no banco a cada requisição (não confia só na sessão) pra que
 * mudanças de função/permissões feitas por um admin valham na hora.
 */
function auth(): array {
    start_session();
    $id = $_SESSION['uid'] ?? null;
    if (!$id) fail(401, 'Não autenticado');
    $u = q1('SELECT id, username, role, nome, foto_path, foto_nome, permissoes FROM users WHERE id=?', [$id]);
    if (!$u) { session_destroy(); fail(401, 'Usuário não encontrado'); }
    $GLOBALS['CURRENT_USER'] = $u;
    return $u;
}
function admin_only(): void {
    $u = current_user() ?? auth();
    if ($u['role'] !== 'admin') fail(403, 'Acesso restrito');
}

// ─── Níveis de acesso ────────────────────────────────────────────────────────
// Dois níveis fixos:
//   admin   → todas as telas (inclui Categorias, Caixas/Banco, Formas de pagamento e Usuários)
//   usuário → só as telas abaixo. Qualquer outra rota responde 403, mesmo chamada direto na API.
const PERMISSOES_USUARIO = [
    'dashboard', 'lancamentos', 'fluxo-caixa',
    'cad-clientes', 'cad-fornecedores', 'cad-parceiros', 'logs',
];
// Telas que só o admin acessa (usado só pra montar a lista "todas" do admin).
const PERMISSOES_ADMIN = [
    'cad-categorias', 'cad-caixas', 'cad-formas', 'usuarios',
    'dre-resumo', 'dre-detalhado', 'formas-pagamento-rel', 'import',
];
function perms_of(array $u): array {
    return $u['role'] === 'admin' ? array_merge(PERMISSOES_USUARIO, PERMISSOES_ADMIN) : PERMISSOES_USUARIO;
}
function tem_permissao(array $u, string ...$paginas): bool {
    if ($u['role'] === 'admin') return true;
    return (bool) array_intersect($paginas, PERMISSOES_USUARIO);
}
/** Segunda camada: a UI já esconde o menu, isso impede acesso direto pela API. */
function permitir(string ...$paginas): void {
    $u = current_user() ?? auth();
    if (!tem_permissao($u, ...$paginas)) fail(403, 'Sem permissão de acesso a esta tela');
}
function user_out(array $u): array {
    return [
        'id' => (int) $u['id'], 'username' => $u['username'], 'role' => $u['role'],
        'nome' => $u['nome'] ?? null,
        'foto_path' => public_path($u['foto_path'] ?? null),
        'foto_nome' => $u['foto_nome'] ?? null,
        'permissoes' => perms_of($u),
    ];
}

// ─── Rate limit (login / criação de usuário) ─────────────────────────────────
// Guardado no banco pra funcionar mesmo com vários workers do PHP-FPM.
function rate_limit(string $key, int $max, int $windowSec): void {
    $id = substr($key . ':' . client_ip(), 0, 190);
    $now = time();
    $row = q1('SELECT start_at, hits FROM rate_limits WHERE k=?', [$id]);
    if (!$row || $now - (int) $row['start_at'] > $windowSec) {
        exec_sql('REPLACE INTO rate_limits (k, start_at, hits) VALUES (?,?,1)', [$id, $now]);
        if (mt_rand(1, 50) === 1) exec_sql('DELETE FROM rate_limits WHERE start_at < ?', [$now - 3600]);
        return;
    }
    $hits = (int) $row['hits'] + 1;
    exec_sql('UPDATE rate_limits SET hits=? WHERE k=?', [$hits, $id]);
    if ($hits > $max) {
        header('Retry-After: ' . max(1, (int) $row['start_at'] + $windowSec - $now));
        fail(429, 'Muitas tentativas. Aguarde um pouco antes de tentar novamente.');
    }
}

// ─── Log de atividades ───────────────────────────────────────────────────────
// Nunca derruba a requisição principal: se falhar, só registra no error_log.
function log_action(string $action, string $entity, $entityId = null, $details = null, ?string $usernameOverride = null): void {
    try {
        $u = current_user();
        exec_sql(
            'INSERT INTO logs (user_id, username, action, entity, entity_id, details, ip) VALUES (?,?,?,?,?,?,?)',
            [$u['id'] ?? null, $usernameOverride ?? ($u['username'] ?? null), $action, $entity,
             $entityId !== null ? (int) $entityId : null,
             $details !== null ? mb_substr((string) $details, 0, 500) : null, client_ip()]
        );
    } catch (Throwable $e) { error_log('[LOG] falha ao registrar: ' . $e->getMessage()); }
}

// ─── Uploads ─────────────────────────────────────────────────────────────────
const EXT_IMAGEM    = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
const EXT_DOCUMENTO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'xml', 'zip'];

/**
 * Salva um arquivo enviado em uploads/<pasta>/ com nome aleatório (não confia no
 * nome vindo do navegador) e só aceita extensões da lista. Retorna
 * ['path' => 'uploads/pasta/arquivo.ext', 'nome' => 'nome original'] ou null.
 */
function save_upload(string $field, string $pasta, array $extPermitidas, int $maxBytes = 20 * 1024 * 1024): ?array {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Arquivo muito grande' : 'Falha no upload do arquivo';
        fail(400, $msg);
    }
    if ($f['size'] > $maxBytes) fail(400, 'Arquivo muito grande (máx. ' . round($maxBytes / 1048576) . ' MB)');
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extPermitidas, true)) fail(400, 'Tipo de arquivo não permitido (.' . $ext . ')');
    $dir = UPLOADS_DIR . '/' . $pasta;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $nome = round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nome")) fail(500, 'Não foi possível salvar o arquivo');
    return ['path' => "uploads/$pasta/$nome", 'nome' => mb_substr(basename((string) $f['name']), 0, 255)];
}
/** Apaga arquivo de upload (aceita caminho antigo do Node com "/" na frente). */
function delete_upload(?string $path): void {
    if (!$path) return;
    $rel = ltrim($path, '/');
    if (strpos($rel, 'uploads/') !== 0 || strpos($rel, '..') !== false) return;
    $full = ROOT_DIR . '/' . $rel;
    if (is_file($full)) @unlink($full);
}
/** Caminho relativo pro front (funciona em subpasta, ex: site.com/dre/). */
function public_path(?string $p): ?string { return $p ? ltrim($p, '/') : null; }

// ─── Diversos ────────────────────────────────────────────────────────────────
function str_or_null($v): ?string { $v = trim((string) ($v ?? '')); return $v === '' ? null : $v; }
function num($v): float { return is_numeric($v) ? (float) $v : 0.0; }
function only_digits(string $v): string { return preg_replace('/\D/', '', $v); }

/** Valida CNPJ pelos dígitos verificadores. */
function cnpj_valido(string $cnpj): bool {
    $c = only_digits($cnpj);
    if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) return false;
    $calc = function (string $base, array $pesos): int {
        $s = 0; foreach ($pesos as $i => $p) $s += (int) $base[$i] * $p;
        $r = $s % 11; return $r < 2 ? 0 : 11 - $r;
    };
    $d1 = $calc(substr($c, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $d2 = $calc(substr($c, 0, 12) . $d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    return $c[12] == $d1 && $c[13] == $d2;
}
function cpf_valido(string $cpf): bool {
    $c = only_digits($cpf);
    if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) return false;
    for ($t = 9; $t < 11; $t++) {
        $s = 0; for ($i = 0; $i < $t; $i++) $s += (int) $c[$i] * (($t + 1) - $i);
        $d = ((10 * $s) % 11) % 10;
        if ((int) $c[$t] !== $d) return false;
    }
    return true;
}
/** Fornecedor pode ser empresa (CNPJ) ou pessoa física (CPF). */
function doc_valido(string $doc): bool { $n = strlen(only_digits($doc)); return $n === 14 ? cnpj_valido($doc) : ($n === 11 && cpf_valido($doc)); }
function fmt_doc(string $doc): string {
    $c = only_digits($doc);
    if (strlen($c) === 11) return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9);
    return fmt_cnpj($doc);
}
function fmt_cnpj(string $cnpj): string {
    $c = only_digits($cnpj);
    return strlen($c) === 14 ? substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 3) . '/' . substr($c, 8, 4) . '-' . substr($c, 12) : $cnpj;
}
