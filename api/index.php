<?php
/**
 * api/index.php — ponto de entrada de todas as rotas /api/*.
 * O .htaccess (Apache) ou o router.php (servidor embutido do PHP) mandam pra cá
 * com ?__route=<caminho depois de /api/>.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once APP_DIR . '/schema.php';
require_once APP_DIR . '/router.php';

// Cabeçalhos de segurança (mesmos do Express)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header('Cache-Control: no-store');
if (IS_PROD && !empty($_SERVER['HTTPS'])) header('Strict-Transport-Security: max-age=15552000; includeSubDomains');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$route  = trim((string) ($_GET['__route'] ?? ''), '/');
unset($_GET['__route']);

try {
    ensure_schema();

    foreach (glob(APP_DIR . '/routes/*.php') as $f) require_once $f;

    [$handler, $params] = Router::match($method, $route);
    if (!$handler) fail(404, 'Rota não encontrada');
    $result = $handler(...$params);
    // Rotas de download (export) já enviaram a resposta e deram exit.
    json_out($result ?? ['ok' => true]);
} catch (HttpError $e) {
    json_out(['error' => $e->getMessage()], $e->status);
} catch (PDOException $e) {
    error_log('[DB] ' . $e->getMessage());
    $msg = IS_PROD ? 'Erro no banco de dados' : $e->getMessage();
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) { json_out(['error' => 'Registro duplicado'], 400); }
    json_out(['error' => $msg], 500);
} catch (Throwable $e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_out(['error' => IS_PROD ? 'Erro interno' : $e->getMessage()], 500);
}
