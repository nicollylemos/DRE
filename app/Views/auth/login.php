<?php
/**
 * Tela de login. Estados: entrar, esqueci a senha, criar nova senha (link do e-mail),
 * primeiro acesso (cria o administrador), troca de senha obrigatória (senha provisória)
 * e aceite do Termo de uso / Política de privacidade (LGPD).
 * @var bool $precisaSetup @var bool $trocarSenha @var bool $aceitarTermos @var ?array $redefinir @var ?array $usuario @var string $next
 */
use App\Core\Termos;

$estado = $redefinir ? 'redefinir' : ($trocarSenha ? 'trocar' : ($aceitarTermos ? 'termos' : ($precisaSetup ? 'setup' : 'login')));
$olho = '<svg viewBox="0 0 24 24" class="eye-on"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg viewBox="0 0 24 24" class="eye-off"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17.4 17.4 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
$links = '<a href="' . e(url('termos')) . '" target="_blank" rel="noopener">Termo de uso</a> e a <a href="' . e(url('privacidade')) . '" target="_blank" rel="noopener">Política de privacidade</a>';
$nomeUsuario = $usuario ? e($usuario['nome'] ?: $usuario['username']) : '';
?>
<div class="auth-screen" data-estado="<?= e($estado) ?>" data-next="<?= e($next) ?>">
  <section class="auth-brand" aria-hidden="true">
    <div class="auth-brand-in">
      <img class="auth-brand-logo" src="<?= e(url('assets/img/logo-oaf-clara.png')) ?>" onerror="this.onerror=null;this.src='<?= e(url('assets/img/logo-oaf.png')) ?>'" alt=""/>
      <h2>O financeiro da empresa,<br/>claro e em dia.</h2>
      <ul class="auth-bullets">
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>DRE mês a mês, com comparação ao ano anterior</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Calendário de contas a pagar e a receber</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Notas fiscais ligadas aos lançamentos</li>
      </ul>
    </div>
  </section>

  <section class="auth-side">
    <div class="auth-card">
      <img class="auth-logo" src="<?= e(url('assets/img/logo-oaf.png')) ?>" onerror="this.onerror=null;this.src='<?= e(url('assets/img/logo-oaf.svg')) ?>'" alt="OAF Benefícios e Consultoria"/>

      <!-- ENTRAR -->
      <form id="login-form" class="auth-form<?= $estado !== 'login' ? ' hidden' : '' ?>" autocomplete="on" novalidate>
        <header><h1>Bem-vindo de volta</h1><p class="auth-sub">Entre com seu usuário para continuar.</p></header>
        <div class="auth-ok hidden" id="login-ok" role="status"></div>
        <label class="field"><span>Usuário</span>
          <div class="input-ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-4 3.8-6.2 7.5-6.2s6.7 2.2 7.5 6.2"/></svg>
          <input type="text" id="login-user" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required/></div>
        </label>
        <label class="field"><span>Senha <button type="button" class="link auth-esqueci" id="btn-esqueci">Esqueci minha senha</button></span>
          <div class="input-ico pass"><svg viewBox="0 0 24 24"><rect x="4" y="10.5" width="16" height="10.5" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/></svg>
          <input type="password" id="login-pass" name="password" autocomplete="current-password" required/>
          <button type="button" class="eye" data-eye="login-pass" aria-label="Mostrar senha"><?= $olho ?></button></div>
          <small class="caps hidden" id="login-caps">Caps Lock está ligado</small>
        </label>
        <label class="check"><input type="checkbox" id="login-lembrar"/><span>Manter conectado neste computador</span></label>
        <div class="form-error" id="login-error" role="alert"></div>
        <button class="btn btn-navy btn-block btn-lg" type="submit" id="btn-login"><span class="spin"></span><span class="lbl">Entrar</span></button>
      </form>

      <!-- ESQUECI MINHA SENHA -->
      <form id="esqueci-form" class="auth-form hidden" autocomplete="on" novalidate>
        <header><h1>Esqueci minha senha</h1><p class="auth-sub">Informe seu usuário ou o e-mail do seu cadastro. Vamos mandar um link para você criar uma senha nova.</p></header>
        <label class="field"><span>Usuário ou e-mail</span>
          <div class="input-ico"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>
          <input type="text" id="esqueci-login" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="150" required/></div>
        </label>
        <div class="form-error" id="esqueci-error" role="alert"></div>
        <div class="auth-ok hidden" id="esqueci-ok" role="status"></div>
        <button class="btn btn-navy btn-block btn-lg" type="submit" id="btn-esqueci-enviar"><span class="spin"></span><span class="lbl">Enviar link</span></button>
        <p class="auth-help">Não tem e-mail no cadastro? Peça para um administrador redefinir sua senha em <b>Usuários e acessos</b>.</p>
        <button class="link auth-out" type="button" id="btn-esqueci-voltar">← Voltar para entrar</button>
      </form>

      <!-- CRIAR NOVA SENHA (link do e-mail) -->
      <form id="redefinir-form" class="auth-form<?= $estado !== 'redefinir' ? ' hidden' : '' ?>" autocomplete="off" novalidate>
        <?php if ($redefinir && empty($redefinir['invalido'])): ?>
        <header><h1>Criar nova senha</h1><p class="auth-sub">Olá, <?= e($redefinir['nome'] ?: $redefinir['username']) ?>! Defina a nova senha do usuário <b><?= e($redefinir['username']) ?></b>.</p></header>
        <input type="hidden" id="redefinir-token" value="<?= e($redefinir['token']) ?>"/>
        <input type="text" class="hidden" autocomplete="username" value="<?= e($redefinir['username']) ?>" aria-hidden="true" tabindex="-1"/>
        <label class="field"><span>Nova senha</span>
          <div class="input-ico pass no-ico"><input type="password" id="redefinir-nova" autocomplete="new-password" placeholder="8+ caracteres, letras e números" required/>
          <button type="button" class="eye" data-eye="redefinir-nova" aria-label="Mostrar senha"><?= $olho ?></button></div>
        </label>
        <div class="forca" data-forca="redefinir-nova"><i></i><i></i><i></i><i></i><span></span></div>
        <label class="field"><span>Confirmar nova senha</span><input type="password" id="redefinir-conf" autocomplete="new-password" required/></label>
        <div class="form-error" id="redefinir-error" role="alert"></div>
        <button class="btn btn-navy btn-block btn-lg" type="submit" id="btn-redefinir"><span class="spin"></span><span class="lbl">Salvar nova senha</span></button>
        <?php else: ?>
        <header><h1>Link expirado</h1><p class="auth-sub">Esse link para criar senha não vale mais — ele dura <?= (int) \App\Models\PasswordReset::VALIDADE_MIN ?> minutos e só pode ser usado uma vez.</p></header>
        <a class="btn btn-navy btn-block btn-lg" href="<?= e(url('login?esqueci=1')) ?>">Pedir um novo link</a>
        <a class="link auth-out" href="<?= e(url('login')) ?>">← Voltar para entrar</a>
        <?php endif; ?>
      </form>

      <!-- PRIMEIRO ACESSO -->
      <form id="setup-form" class="auth-form<?= $estado !== 'setup' ? ' hidden' : '' ?>" autocomplete="off" novalidate>
        <header><h1>Primeiro acesso</h1><p class="auth-sub">Nenhum usuário cadastrado ainda. Crie o acesso do administrador.</p></header>
        <label class="field"><span>Seu nome</span><input type="text" id="setup-nome" maxlength="150" autocomplete="name"/></label>
        <label class="field"><span>Usuário</span><input type="text" id="setup-user" autocomplete="username" autocapitalize="none" placeholder="ex: nicolly" required/></label>
        <label class="field"><span>E-mail <small class="opc">para recuperar a senha</small></span><input type="email" id="setup-email" maxlength="150" autocomplete="email"/></label>
        <label class="field"><span>Senha</span>
          <div class="input-ico pass no-ico"><input type="password" id="setup-pass" autocomplete="new-password" placeholder="8+ caracteres, letras e números" required/>
          <button type="button" class="eye" data-eye="setup-pass" aria-label="Mostrar senha"><?= $olho ?></button></div>
        </label>
        <div class="forca" data-forca="setup-pass"><i></i><i></i><i></i><i></i><span></span></div>
        <label class="field"><span>Confirmar senha</span><input type="password" id="setup-conf" autocomplete="new-password" required/></label>
        <label class="check top"><input type="checkbox" id="setup-aceite"/><span>Li e concordo com o <?= $links ?>.</span></label>
        <div class="form-error" id="setup-error" role="alert"></div>
        <button class="btn btn-gold btn-block btn-lg" type="submit" id="btn-setup"><span class="spin"></span><span class="lbl">Criar acesso de administrador</span></button>
      </form>

      <!-- TROCA OBRIGATÓRIA -->
      <form id="trocar-form" class="auth-form<?= $estado !== 'trocar' ? ' hidden' : '' ?>" autocomplete="off" novalidate>
        <header><h1>Crie sua senha</h1><p class="auth-sub">Olá<?= $nomeUsuario ? ', ' . $nomeUsuario : '' ?>! Você entrou com uma senha provisória. Defina uma senha só sua para continuar.</p></header>
        <label class="field"><span>Nova senha</span>
          <div class="input-ico pass no-ico"><input type="password" id="trocar-nova" autocomplete="new-password" placeholder="8+ caracteres, letras e números" required/>
          <button type="button" class="eye" data-eye="trocar-nova" aria-label="Mostrar senha"><?= $olho ?></button></div>
        </label>
        <div class="forca" data-forca="trocar-nova"><i></i><i></i><i></i><i></i><span></span></div>
        <label class="field"><span>Confirmar nova senha</span><input type="password" id="trocar-conf" autocomplete="new-password" required/></label>
        <div class="form-error" id="trocar-error" role="alert"></div>
        <button class="btn btn-navy btn-block btn-lg" type="submit" id="btn-trocar"><span class="spin"></span><span class="lbl">Salvar e entrar</span></button>
        <button class="link auth-out" type="button" data-sair>Sair e entrar com outro usuário</button>
      </form>

      <!-- ACEITE LGPD -->
      <form id="termos-form" class="auth-form<?= $estado !== 'termos' ? ' hidden' : '' ?>" novalidate>
        <header><h1>Privacidade e uso dos dados</h1><p class="auth-sub">Olá<span id="termos-nome"><?= $nomeUsuario ? ', ' . $nomeUsuario : '' ?></span>! Antes de continuar, leia como o sistema trata os dados pessoais (LGPD).</p></header>
        <div class="termos-resumo">
          <p><b><?= e(Termos::controlador()) ?></b> é a controladora dos dados deste sistema. Em resumo:</p>
          <ul>
            <li>Guardamos seu nome, usuário, e-mail, foto (se enviar), IP e o registro do que você faz no sistema — para dar acesso, proteger a conta e manter a auditoria financeira.</li>
            <li>Os cadastros de clientes, fornecedores, parceiros e as notas fiscais são usados só para o controle financeiro da empresa.</li>
            <li>Nada é vendido nem usado para propaganda. Você pode pedir acesso, correção ou exclusão dos seus dados<?= Termos::email() ? ' pelo e-mail <b>' . e(Termos::email()) . '</b>' : ' ao administrador do sistema' ?>.</li>
          </ul>
        </div>
        <label class="check top"><input type="checkbox" id="termos-aceite"/><span>Li e concordo com o <?= $links ?> (versão <?= e(Termos::VERSAO) ?>).</span></label>
        <div class="form-error" id="termos-error" role="alert"></div>
        <button class="btn btn-navy btn-block btn-lg" type="submit" id="btn-termos"><span class="spin"></span><span class="lbl">Aceitar e continuar</span></button>
        <button class="link auth-out" type="button" data-sair>Não concordo — sair</button>
      </form>

      <p class="auth-version"><a href="<?= e(url('termos')) ?>" target="_blank" rel="noopener">Termo de uso</a> · <a href="<?= e(url('privacidade')) ?>" target="_blank" rel="noopener">Privacidade</a> · Versão <?= e(APP_VERSION) ?></p>
    </div>
  </section>
</div>
