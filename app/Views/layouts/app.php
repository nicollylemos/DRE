<?php
/**
 * Layout das telas internas: menu lateral + conteúdo + modais globais.
 * @var string $titulo  @var string $pagina  @var string $content  @var array $scripts
 */
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$user = Auth::out();
$appData = [
    'base' => base_path(), 'csrf' => Csrf::token(), 'versao' => APP_VERSION,
    'pagina' => $pagina, 'tela' => $tela ?? null, 'user' => $user,
];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($titulo) ?> — OAF Controle Financeiro</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/logo-oaf-mark.png')) ?>"/>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"/>
<script>
  // Evita "piscar" o menu: aplica o estado salvo antes de desenhar a página.
  try { if (localStorage.getItem('dre.menu') === 'fechado') document.documentElement.classList.add('menu-fechado'); } catch (e) {}
  window.APP = <?= json_encode($appData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
</head>
<body class="pg-<?= e($pagina) ?>">
<div class="app">
  <?= View::partial('partials/sidebar', ['pagina' => $pagina, 'user' => $user]) ?>
  <main class="main" id="main">
    <?= $content ?>
  </main>
</div>
<?= View::partial('partials/modais-globais', ['user' => $user]) ?>
<script src="<?= e(asset('js/core.js')) ?>"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= e(asset('js/' . $s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
