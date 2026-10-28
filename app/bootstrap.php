<?php
/**
 * bootstrap.php — prepara a aplicação: constantes, .env, autoload das classes
 * (App\Core, App\Controllers, App\Models, App\Services) e helpers globais.
 * Quem chama é o index.php (front controller) e os scripts de linha de comando.
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('VIEWS_DIR', APP_DIR . '/Views');
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('STORAGE_DIR', ROOT_DIR . '/storage');

// Versão do sistema — aparece na tela de login. Atualize a cada entrega.
define('APP_VERSION', '3.1.1');

// ─── .env simples (sem Composer) ─────────────────────────────────────────────
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

// ─── Autoload PSR-4: App\Foo\Bar → app/Foo/Bar.php ───────────────────────────
spl_autoload_register(function (string $class) {
    if (strncmp($class, 'App\\', 4) !== 0) return;
    $file = APP_DIR . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

require APP_DIR . '/helpers.php';

define('IS_PROD', env('APP_ENV', 'production') === 'production');
date_default_timezone_set(env('APP_TZ', 'America/Sao_Paulo'));

// Em produção não vaza stack trace/erro de SQL pro navegador.
ini_set('display_errors', IS_PROD ? '0' : '1');
error_reporting(E_ALL);
