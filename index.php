<?php
/**
 * index.php — front controller: TODA requisição (páginas e /api/*) passa por aqui.
 * O .htaccess (Apache) ou o router.php (servidor embutido do PHP) mandam pra cá
 * tudo que não for arquivo estático (css, js, imagens, uploads).
 */
require __DIR__ . '/app/bootstrap.php';

App\Core\App::run();
