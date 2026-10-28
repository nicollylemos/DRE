<?php /** Modais usados em todas as telas: menu de ações, detalhes, confirmação, perfil. @var array $user */ ?>
<!-- Menu dos 3 pontinhos (um só, reposicionado ao lado do botão clicado) -->
<div class="row-menu hidden" id="row-menu" role="menu"></div>

<!-- Visualizar (genérico) -->
<div class="modal sheet hidden" id="modal-view">
  <div class="modal-box">
    <header class="modal-head"><div><h2 id="view-title"></h2><p class="modal-sub" id="view-sub"></p></div><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <div class="modal-body"><dl class="details" id="view-body"></dl></div>
    <footer class="modal-foot" id="view-foot"></footer>
  </div>
</div>

<!-- Confirmação -->
<div class="modal hidden" id="modal-confirm">
  <div class="modal-box sm">
    <div class="modal-body confirm"><h2 id="confirm-title">Tem certeza?</h2><p id="confirm-msg"></p><div id="confirm-extra"></div></div>
    <footer class="modal-foot"><button class="btn btn-ghost" id="confirm-nao">Cancelar</button><button class="btn btn-danger" id="confirm-sim">Confirmar</button></footer>
  </div>
</div>

<!-- Meu perfil -->
<div class="modal hidden" id="modal-perfil">
  <div class="modal-box sm">
    <header class="modal-head"><h2>Meu perfil</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <div class="modal-body">
      <div class="tabs sm" id="perfil-tabs" role="tablist">
        <button type="button" class="on" data-tab="dados" role="tab">Meus dados</button>
        <button type="button" data-tab="senha" role="tab">Trocar senha</button>
      </div>
      <form id="perfil-form" class="tab-pane stack" data-pane="dados">
        <div class="perfil-top">
          <div class="avatar-lg" id="perfil-avatar"></div>
          <div><strong id="perfil-username"></strong><div class="muted" id="perfil-role"></div>
            <button type="button" class="link" id="perfil-trocar-foto">Trocar foto</button>
            <input type="file" id="perfil-foto" accept="image/*" hidden/></div>
        </div>
        <label class="field"><span>Nome</span><input type="text" id="perfil-nome" maxlength="150"/></label>
        <label class="field"><span>E-mail</span><input type="email" id="perfil-email" maxlength="150"/></label>
        <div class="form-error" id="perfil-error"></div>
        <p class="muted perfil-lgpd">Seus dados seguem a <a class="link" href="<?= e(url('privacidade')) ?>" target="_blank" rel="noopener">Política de privacidade</a> e o <a class="link" href="<?= e(url('termos')) ?>" target="_blank" rel="noopener">Termo de uso</a><span id="perfil-aceite"></span>.</p>
      </form>
      <form id="senha-form" class="tab-pane stack hidden" data-pane="senha" autocomplete="off">
        <label class="field"><span>Senha atual</span><input type="password" id="senha-atual" autocomplete="current-password"/></label>
        <label class="field"><span>Nova senha</span><input type="password" id="senha-nova" autocomplete="new-password" placeholder="8+ caracteres, letras e números"/></label>
        <div class="forca" id="senha-forca" aria-live="polite"><i></i><i></i><i></i><i></i><span></span></div>
        <label class="field"><span>Confirmar nova senha</span><input type="password" id="senha-conf" autocomplete="new-password"/></label>
        <div class="form-error" id="senha-error"></div>
      </form>
    </div>
    <footer class="modal-foot spread"><button class="btn btn-ghost danger" id="btn-sair" type="button">Sair do sistema</button><div><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-perfil" type="button">Salvar</button></div></footer>
  </div>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>
