<?php /** @var int $status @var string $mensagem */ ?>
<div class="auth-screen solo">
  <section class="auth-side">
    <div class="auth-card erro-card">
      <div class="erro-cod"><?= (int) $status ?></div>
      <h1><?= $status === 404 ? 'Página não encontrada' : ($status === 403 ? 'Acesso negado' : 'Algo deu errado') ?></h1>
      <p class="auth-sub"><?= e($mensagem) ?></p>
      <a class="btn btn-navy btn-block" href="<?= e(url('')) ?>">Voltar ao início</a>
    </div>
  </section>
</div>
