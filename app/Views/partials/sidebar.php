<?php
/**
 * Menu lateral: monta os itens a partir do catálogo de telas (Permissions) e
 * mostra só o que o usuário pode ver. @var string $pagina @var array $user
 */
use App\Core\Auth;
use App\Core\Permissions;

$icones = [
    'dashboard'    => '<rect x="3" y="3" width="7.5" height="9" rx="2"/><rect x="13.5" y="3" width="7.5" height="5.5" rx="2"/><rect x="13.5" y="11.5" width="7.5" height="9.5" rx="2"/><rect x="3" y="15" width="7.5" height="6" rx="2"/>',
    'dre'          => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 17v-3M12 17v-6M15 17v-4"/>',
    'calendario'   => '<rect x="3" y="4.5" width="18" height="16.5" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/><path d="M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 17h2M11 17h2"/>',
    'lancamentos'  => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/>',
    'fluxo-caixa'  => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
    'notas-fiscais' => '<path d="M6 2.5h8.5L19 7v14.5H6z"/><path d="M14.5 2.5V7H19"/><path d="M9 12h6.5M9 15.5h6.5M9 9h2.5"/>',
    'importacao'   => '<path d="M12 3v12m0 0-4-4m4 4 4-4"/><path d="M4 15v3a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-3"/>',
    'clientes'     => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-4 3.8-6.2 7.5-6.2s6.7 2.2 7.5 6.2"/>',
    'fornecedores' => '<path d="M3 9.5 4.6 4h14.8L21 9.5"/><path d="M4 9.5v10.5h16V9.5"/><path d="M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M10 20v-5h4v5"/>',
    'parceiros'    => '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
    'categorias'   => '<path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.42l8.7 8.7a2.43 2.43 0 0 0 3.42 0l6.58-6.58a2.43 2.43 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
    'caixas'       => '<path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v8M9.7 10v8M14.3 10v8M19 10v8"/><path d="M3 20.5h18"/>',
    'formas'       => '<path d="M19 7V5.5A1.5 1.5 0 0 0 17.5 4h-12A2.5 2.5 0 0 0 3 6.5v11A2.5 2.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V16"/><path d="M3 6.5A2.5 2.5 0 0 0 5.5 9h13A1.5 1.5 0 0 1 20 10.5V16h-4a2 2 0 0 1 0-4.5h4"/>',
    'usuarios'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 14.8c1.7.8 2.8 2.6 3 5.2"/>',
    'logs'         => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7.5V12l3 2"/>',
];

// Agrupa as telas liberadas
$grupos = [];
foreach (Permissions::TELAS as $chave => [$label, $grupo, $rota]) {
    if (!Auth::pode($chave)) continue;
    $grupos[$grupo][] = [$rota, $label];
}
if (Auth::isAdmin()) $grupos['Sistema'] = array_merge([['usuarios', 'Usuários e acessos']], $grupos['Sistema'] ?? []);
$inicial = mb_strtoupper(mb_substr(trim($user['nome'] ?: $user['username']), 0, 1));
?>
<aside class="sidebar" id="sidebar" aria-label="Menu principal">
  <div class="side-top">
    <a class="side-brand" href="<?= e(url($user['home'])) ?>" title="Página inicial">
      <img src="<?= e(url('assets/img/logo-oaf-mark-clara.png')) ?>" onerror="this.onerror=null;this.src='<?= e(url('assets/img/logo-oaf-mark.png')) ?>'" alt="OAF"/>
      <span class="side-brand-txt"><strong>OAF</strong><small>Controle Financeiro</small></span>
    </a>
    <button class="side-toggle" id="side-toggle" type="button" aria-label="Recolher menu" title="Recolher / expandir menu">
      <svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg>
    </button>
  </div>
  <nav class="side-nav" id="side-nav">
    <?php foreach ($grupos as $grupo => $itens): ?>
      <div class="side-group">
        <span class="side-group-t"><?= e($grupo) ?></span>
        <?php foreach ($itens as [$rota, $label]): ?>
          <a href="<?= e(url($rota)) ?>" class="side-btn<?= $rota === $pagina ? ' active' : '' ?>" data-label="<?= e($label) ?>"<?= $rota === $pagina ? ' aria-current="page"' : '' ?>>
            <svg viewBox="0 0 24 24"><?= $icones[$rota] ?? '' ?></svg><span class="side-lbl"><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </nav>
  <button class="side-user" id="btn-perfil" type="button" title="Meu perfil">
    <span class="side-avatar" id="user-avatar"><?php if ($user['foto_path']): ?><img src="<?= e(url($user['foto_path'])) ?>" alt=""/><?php else: ?><?= e($inicial) ?><?php endif; ?></span>
    <span class="side-user-txt"><strong><?= e($user['nome'] ?: $user['username']) ?></strong><small><?= e($user['perfil_nome']) ?></small></span>
  </button>
</aside>
