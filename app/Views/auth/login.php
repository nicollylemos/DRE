<?php
/**
 * Tela de login. Três estados: entrar, primeiro acesso (cria o administrador)
 * e troca de senha obrigatória (senha provisória criada pelo administrador).
 * @var bool $precisaSetup @var bool $trocarSenha @var ?array $usuario @var string $next
 */
$estado = $trocarSenha ? 'trocar' : ($precisaSetup ? 'setup' : 'login');
$olho = '<svg viewBox="0 0 24 24" class="eye-on"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg viewBox="0 0 24 24" class="eye-off"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17.4 17.4 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
?>
<div class="auth-screen" data-estado="<?= e($estado) ?>" data-next="<?= e($next) ?>">
  <section class="auth-brand" aria-hidden="true">
    <div class="auth-brand-in">
      <img class="auth-brand-logo" src="<?= e(url('assets/img/logo-oaf.png')) ?>" onerror="this.onerror=null;this.src='<?= e(url('assets/img/logo-oaf.svg')) ?>'" alt=""/>
      <h2>O financeiro da empresa,<br/>claro e em dia.</h2>
      <ul class="auth-bullets">
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>DRE mês a mês, com comparação ao ano anterior</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Calendário de contas a pagar e a receber</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Importação de planilhas em poucos cliques</li>
      </ul>
    </div>
  </section>

  <section class="auth-side">
    <div class="auth-card">
      <img class="auth-logo" src="<?= e(url('assets/img/logo-oaf.png')) ?>" onerror="this.onerror=null;this.src='<?= e(url('assets/img/logo-oaf.svg')) ?>'" alt="OAF Benefícios e Consultoria"/>

      <!-- ENTRAR -->
      <form id="login-form" class="auth-form<?= $estado !== 'login' ? ' hidden' : '' ?>" autocomplete="on" novalidate>
        <header><h1>Bem-vindo de volta</h1><p class="auth-sub">Entre com seu usuário para continuar.</p></header>
        <label class="field"><span>Usuário</span>
          <div class="input-ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-4 3.8-6.2 7.5-6.2s6.7 2.2 7.5 6.2"/></svg>
          <input type="text" id="login-user" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required/></div>
        </label>
        <label class="field"><span>Senha</span>
          <div class="input-ico pass"><svg viewBox="0 0 24 24"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
          <input type="password" id="login-pass" name="password" autocomplete="current-password" required/>
          <button type="button" class="eye" data-eye="login-pass" aria-label="Mostrar senha"><?= $olho ?></button></div>
          <small class="caps hidden" id="login-caps">Caps Lock está ligado</small>
        </label>
        <label class="check"><input type="checkbox" id="login-lembrar"/><span>Manter conectado neste computador</span></label>
        <div class="form-error" id="login-error" role="alert"></div>
        <button class="btn btn-navy btn-block btn-lg" type="submit" id="btn-login"><span class="spin"></span><span class="lbl">Entrar</span></button>
        <p class="auth-help">Esqueceu a senha? Peça para um administrador redefinir em <b>Usuários e acessos</b>.</p>
      </form>

      <!-- PRIMEIRO ACESSO -->
      <form id="setup-form" class="auth-form<?= $estado !== 'setup' ? ' hidden' : '' ?>" autocomplete="off" novalidate>
        <header><h1>Primeiro acesso</h1><p class="auth-sub">Nenhum usuário cadastrado ainda. Crie o acesso do administrador.</p></header>
        <label class="field"><span>Seu nome</span><input type="text" id="setup-nome" maxlength="150" autocomplete="name"/></label>
        <label class="field"><span>Usuário</span><input type="text" id="setup-user" autocomplete="username" autocapitalize="none" placeholder="ex: nicolly" required/></label>
        <label class="field"><span>Senha</span>
          <div class="input-ico pass no-ico"><input type="password" id="setup-pass" autocomplete="new-password" placeholder="8+ caracteres, letras e números" required/>
          <button type="button" class="eye" data-eye="setup-pass" aria-label="Mostrar senha"><?= $olho ?></button></div>
        </label>
        <div class="forca" data-forca="setup-pass"><i></i><i></i><i></i><i></i><span></span></div>
        <label class="field"><span>Confirmar senha</span><input type="password" id="setup-conf" autocomplete="new-password" required/></label>
        <div class="form-error" id="setup-error" role="alert"></div>
        <button class="btn btn-gold btn-block btn-lg" type="submit" id="btn-setup"><span class="spin"></span><span class="lbl">Criar acesso de administrador</span></button>
      </form>

      <!-- TROCA OBRIGATÓRIA -->
      <form id="trocar-form" class="auth-form<?= $estado !== 'trocar' ? ' hidden' : '' ?>" autocomplete="off" novalidate>
        <header><h1>Crie sua senha</h1><p class="auth-sub">Olá<?= $usuario ? ', ' . e($usuario['nome'] ?: $usuario['username']) : '' ?>! Você entrou com uma senha provisória. Defina uma senha só sua para continuar.</p></header>
        <label class="field"><span>Nova senha</span>
          <div class="input-ico pass no-ico"><input type="password" id="trocar-nova" autocomplete="new-password" placeholder="8+ caracteres, letras e números" required/>
          <button type="button" class="eye" data-eye="trocar-nova" aria-label="Mostrar senha"><?= $olho ?></button></div>
        </label>
        <div class="forca" data-forca="trocar-nova"><i></i><i></i><i></i><i></i><span></span></div>
        <label class="field"><span>Confirmar nova senha</span><input type="password" id="trocar-conf" autocomplete="new-password" required/></label>
        <div class="form-error" id="trocar-error" role="alert"></div>
        <button class="btn btn-navy btn-block btn-lg" type="submit" id="btn-trocar"><span class="spin"></span><span class="lbl">Salvar e entrar</span></button>
        <button class="link auth-out" type="button" id="btn-trocar-sair">Sair e entrar com outro usuário</button>
      </form>

      <p class="auth-version">Versão <?= e(APP_VERSION) ?></p>
    </div>
  </section>
</div>
