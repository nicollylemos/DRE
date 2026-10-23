<?php use App\Core\Auth; $home = Auth::home(); ?>
<section class="page active" id="page-sem-acesso">
  <div class="vazio-pagina">
    <svg viewBox="0 0 24 24"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
    <?php if (!empty($negado)): ?>
      <h1>Você não tem acesso a esta tela</h1>
      <p>Seu nível de acesso (<b><?= e(Auth::out()['perfil_nome']) ?></b>) não libera essa parte do sistema. Se precisar, peça para um administrador ajustar em <b>Usuários e acessos</b>.</p>
      <?php if ($home !== 'sem-acesso'): ?><a class="btn btn-navy" href="<?= e(url($home)) ?>">Ir para o início</a><?php endif; ?>
    <?php else: ?>
      <h1>Nenhuma tela liberada</h1>
      <p>Seu usuário ainda não tem acesso a nenhuma tela. Peça para um administrador liberar.</p>
    <?php endif; ?>
  </div>
</section>
