<?php
/**
 * Roteador pro servidor embutido do PHP (desenvolvimento local):
 *   php -S localhost:8000 router.php
 * Em produção quem faz esse papel é o .htaccess (Apache) ou o bloco do nginx do README.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/api/(.*)$#', $path, $m)) {
    $_GET['__route'] = $m[1];
    require __DIR__ . '/api/index.php';
    return true;
}
// Mesmos bloqueios do .htaccess
if (preg_match('#^/(app|storage)(/|$)|/\.|\.(sql|md|lock)$#', $path)) { http_response_code(403); echo 'Forbidden'; return true; }
if (preg_match('#^/uploads/.*\.(php\d?|phtml|phar|html?|svg|js)$#i', $path)) { http_response_code(403); echo 'Forbidden'; return true; }
if ($path === '/' ) { header('Cache-Control: no-cache'); readfile(__DIR__ . '/index.html'); return true; }
if (preg_match('#\.(html|js|css)$#', $path)) header('Cache-Control: no-cache, no-store, must-revalidate');
return false; // arquivo estático: o servidor embutido entrega
