<?php
/**
 * Roteador pro servidor embutido do PHP (desenvolvimento local):
 *   php -S localhost:8000 router.php
 * Faz o mesmo papel do .htaccess: arquivo estático é servido direto, o resto vai pro index.php.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

// Mesmos bloqueios do .htaccess
if (preg_match('#^/(app|storage)(/|$)|/\.|\.(sql|md|lock|log)$|^/router\.php$#', $path)) { http_response_code(403); echo 'Forbidden'; return true; }
if (preg_match('#^/uploads/.*\.(php\d?|phtml|phar|html?|svg|js)$#i', $path)) { http_response_code(403); echo 'Forbidden'; return true; }

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file) && basename($file) !== 'index.php') return false; // estático: o servidor embutido entrega

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
