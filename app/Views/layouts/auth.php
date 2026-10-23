<?php
/** Layout das telas sem menu (login, erro). @var string $titulo @var string $content */
use App\Core\Csrf;
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($titulo ?? 'Entrar') ?> — OAF Controle Financeiro</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/logo-oaf-mark.png')) ?>"/>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"/>
<script>window.APP = <?= json_encode(['base' => base_path(), 'csrf' => Csrf::token(), 'versao' => APP_VERSION], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
</head>
<body class="auth-body">
<?= $content ?>
<div class="toasts" id="toasts"></div>
<?php if (!empty($scriptsAuth)): ?>
<script src="<?= e(asset('js/core.js')) ?>"></script>
<?php foreach ($scriptsAuth as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
<?php endif; ?>
</body>
</html>
