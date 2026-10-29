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
  <div class="modal-box perfil-box">
    <header class="perfil-capa">
      <button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
      <div class="perfil-foto">
        <div class="avatar-lg" id="perfil-avatar"></div>
        <button type="button" class="perfil-cam" id="perfil-trocar-foto" title="Trocar foto" aria-label="Trocar foto"><svg viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg></button>
        <input type="file" id="perfil-foto" accept="image/*" hidden/>
      </div>
      <div class="perfil-quem">
        <h2 id="perfil-titulo">Meu perfil</h2>
        <p id="perfil-username"></p>
        <div class="perfil-chips"><span class="chip gold" id="perfil-role"></span><span class="chip" id="perfil-cargo-chip"></span></div>
      </div>
    </header>
    <div class="modal-body">
      <div class="tabs" id="perfil-tabs" role="tablist">
        <button type="button" class="on" data-tab="dados" role="tab"><svg viewBox="0 0 24 24" class="ico-tab"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-4 3.8-6.2 7.5-6.2s6.7 2.2 7.5 6.2"/></svg>Dados profissionais</button>
        <button type="button" data-tab="senha" role="tab"><svg viewBox="0 0 24 24" class="ico-tab"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>Segurança</button>
      </div>
      <form id="perfil-form" class="tab-pane stack" data-pane="dados" autocomplete="off">
        <div class="perfil-sec">
          <h3>Identificação</h3>
          <div class="grid">
            <label class="field span2"><span>Nome completo</span><input type="text" id="perfil-nome" maxlength="150" placeholder="Como você aparece no sistema"/></label>
            <label class="field"><span>Cargo</span><input type="text" id="perfil-cargo" maxlength="100" placeholder="Ex: Analista financeiro"/></label>
            <label class="field"><span>Setor</span><input type="text" id="perfil-setor" maxlength="100" placeholder="Ex: Financeiro"/></label>
          </div>
        </div>
        <div class="perfil-sec">
          <h3>Contato de trabalho</h3>
          <div class="grid">
            <label class="field span2"><span>E-mail corporativo</span><input type="email" id="perfil-email" maxlength="150" placeholder="voce@empresa.com.br"/></label>
            <div class="grid inner-ramal">
              <label class="field"><span>Telefone comercial</span><input type="text" id="perfil-telefone" maxlength="15" inputmode="tel" placeholder="(15) 3333-0000"/></label>
              <label class="field"><span>Ramal</span><input type="text" id="perfil-ramal" maxlength="6" inputmode="numeric" placeholder="—"/></label>
            </div>
            <label class="field"><span>WhatsApp comercial</span><input type="text" id="perfil-whatsapp" maxlength="15" inputmode="tel" placeholder="(15) 99999-0000"/></label>
          </div>
        </div>
        <p class="perfil-aviso"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg><span>Coloque só dados de trabalho — nada de CPF, endereço ou telefone pessoal. Seus dados seguem a <a class="link" href="<?= e(url('privacidade')) ?>" target="_blank" rel="noopener">Política de privacidade</a> e o <a class="link" href="<?= e(url('termos')) ?>" target="_blank" rel="noopener">Termo de uso</a><span id="perfil-aceite"></span>.</span></p>
        <div class="form-error" id="perfil-error"></div>
      </form>
      <form id="senha-form" class="tab-pane stack hidden" data-pane="senha" autocomplete="off">
        <p class="muted" id="senha-info"></p>
        <label class="field"><span>Senha atual</span><input type="password" id="senha-atual" autocomplete="current-password"/></label>
        <div class="grid">
          <label class="field"><span>Nova senha</span><input type="password" id="senha-nova" autocomplete="new-password" placeholder="8+ caracteres, letras e números"/></label>
          <label class="field"><span>Confirmar nova senha</span><input type="password" id="senha-conf" autocomplete="new-password"/></label>
        </div>
        <div class="forca" id="senha-forca" aria-live="polite"><i></i><i></i><i></i><i></i><span></span></div>
        <div class="form-error" id="senha-error"></div>
      </form>
    </div>
    <footer class="modal-foot spread"><button class="btn btn-ghost danger" id="btn-sair" type="button"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg>Sair do sistema</button><div><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-perfil" type="button"><span class="spin"></span><span class="lbl">Salvar</span></button></div></footer>
  </div>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>
