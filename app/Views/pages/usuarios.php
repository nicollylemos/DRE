<section class="page active" id="page-usuarios">
  <header class="page-head">
    <div><h1>Usuários e acessos</h1><p>Quem entra no sistema e o que cada nível de acesso pode ver ou alterar.</p></div>
    <div class="head-actions">
      <button class="btn btn-gold" type="button" id="btn-novo-usuario"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Novo usuário</button>
      <button class="btn btn-gold hidden" type="button" id="btn-novo-perfil"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Novo nível</button>
    </div>
  </header>

  <div class="tabs" id="usr-tabs" role="tablist">
    <button type="button" class="on" data-tab="usuarios" role="tab">Usuários <span class="tab-n" id="usr-n"></span></button>
    <button type="button" data-tab="perfis" role="tab">Níveis de acesso <span class="tab-n" id="perf-n"></span></button>
  </div>

  <!-- USUÁRIOS -->
  <div class="tab-pane" data-pane="usuarios">
    <form class="toolbar" id="usr-toolbar">
      <div class="pills" id="usr-f-sit"><button type="button" data-v="1" class="on">Ativos</button><button type="button" data-v="0">Desativados</button><button type="button" data-v="">Todos</button></div>
      <div class="select"><select id="usr-f-perfil" aria-label="Nível de acesso"><option value="">Todos os níveis</option></select></div>
      <label class="search"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="usr-f-busca" placeholder="Buscar por nome, usuário ou e-mail"/></label>
      <span class="count" id="usr-count"></span>
    </form>
    <div class="table-card">
      <div class="table-scroll">
        <table class="tbl" id="usr-table">
          <thead><tr><th>Usuário</th><th>Nível de acesso</th><th>Situação</th><th>Último acesso</th><th>Criado em</th><th class="act"></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <nav class="pager" id="usr-pager"></nav>
    </div>
  </div>

  <!-- NÍVEIS -->
  <div class="tab-pane hidden" data-pane="perfis">
    <div class="niv-topo">
      <p>O que cada nível pode fazer em cada tela. O bloqueio vale no menu e no servidor.</p>
      <div class="niv-leg"><span><i class="nv-i e"></i>Editar</span><span><i class="nv-i v"></i>Só ver</span><span><i class="nv-i n"></i>Sem acesso</span></div>
    </div>
    <div class="table-card"><div class="table-scroll" id="perfis-grid"></div></div>
  </div>
</section>

<!-- Usuário -->
<div class="modal sheet hidden" id="modal-usr">
  <div class="modal-box lg">
    <header class="modal-head"><h2 id="usr-modal-title">Novo usuário</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="usr-form" autocomplete="off">
      <div class="grid">
        <label class="field"><span>Nome</span><input type="text" id="usr-nome" maxlength="150" placeholder="Nome da pessoa"/></label>
        <label class="field"><span>Usuário (login) *</span><input type="text" id="usr-username" placeholder="sem espaços, ex: maria.silva" autocomplete="off" autocapitalize="none"/></label>
        <label class="field"><span>E-mail</span><input type="email" id="usr-email" maxlength="150"/></label>
        <label class="field"><span>Nível de acesso *</span><select id="usr-perfil"></select></label>
      </div>
      <div class="nivel" id="usr-nivel"></div>
      <div id="usr-senha-bloco">
        <h3 class="sec">Senha de acesso</h3>
        <div class="seg sm" id="usr-senha-modo"><button type="button" data-v="gerar" class="on">Gerar senha provisória</button><button type="button" data-v="definir">Definir agora</button></div>
        <div class="grid" id="usr-senha-def" style="margin-top:12px">
          <label class="field"><span>Senha</span>
            <div class="input-ico pass no-ico"><input type="password" id="usr-senha" autocomplete="new-password" placeholder="8+ caracteres, letras e números"/>
            <button type="button" class="eye" data-eye="usr-senha" aria-label="Mostrar senha"><svg viewBox="0 0 24 24" class="eye-on"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg viewBox="0 0 24 24" class="eye-off"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17.4 17.4 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div>
          </label>
          <div class="field"><span>&nbsp;</span><div class="forca" data-forca="usr-senha"><i></i><i></i><i></i><i></i><span></span></div></div>
        </div>
        <label class="check" id="usr-trocar-wrap"><input type="checkbox" id="usr-trocar" checked/><span>Pedir para a pessoa criar a própria senha no primeiro acesso</span></label>
        <p class="hint" id="usr-senha-hint">Uma senha provisória será gerada e mostrada uma única vez. No primeiro acesso a pessoa cria a senha dela.</p>
      </div>
      <div class="form-error" id="usr-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-usr" type="button">Salvar</button></footer>
  </div>
</div>

<!-- Redefinir senha -->
<div class="modal hidden" id="modal-reset">
  <div class="modal-box sm">
    <header class="modal-head"><div><h2>Redefinir senha</h2><p class="modal-sub" id="reset-sub"></p></div><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="reset-form" autocomplete="off">
      <div class="seg sm" id="reset-modo"><button type="button" data-v="gerar" class="on">Gerar provisória</button><button type="button" data-v="definir">Definir agora</button></div>
      <label class="field hidden" id="reset-senha-wrap"><span>Nova senha</span><input type="password" id="reset-senha" autocomplete="new-password" placeholder="8+ caracteres, letras e números"/></label>
      <label class="check hidden" id="reset-trocar-wrap"><input type="checkbox" id="reset-trocar" checked/><span>Pedir para trocar no próximo acesso</span></label>
      <p class="hint">A pessoa é desconectada na hora e precisa entrar com a nova senha.</p>
      <div class="form-error" id="reset-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-reset" type="button">Redefinir</button></footer>
  </div>
</div>

<!-- Senha provisória gerada -->
<div class="modal hidden" id="modal-senha-gerada">
  <div class="modal-box sm">
    <div class="modal-body confirm">
      <h2>Senha provisória</h2>
      <p id="senha-gerada-msg"></p>
      <div class="senha-box"><code id="senha-gerada"></code><button class="btn btn-ghost sm" type="button" id="btn-copiar-senha">Copiar</button></div>
      <p class="hint">Anote ou envie para a pessoa agora — ela não aparece de novo. No primeiro acesso o sistema pede uma senha nova.</p>
    </div>
    <footer class="modal-foot"><button class="btn btn-navy" data-close type="button">Pronto</button></footer>
  </div>
</div>

<!-- Nível de acesso -->
<div class="modal sheet hidden" id="modal-perfil-nivel">
  <div class="modal-box lg">
    <header class="modal-head"><h2 id="perf-modal-title">Novo nível de acesso</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="perf-form">
      <div class="grid">
        <label class="field"><span>Nome *</span><input type="text" id="perf-nome" maxlength="80" placeholder="Ex: Financeiro, Recepção, Contador"/></label>
        <label class="field"><span>Descrição</span><input type="text" id="perf-desc" maxlength="255" placeholder="Pra que serve esse nível"/></label>
      </div>
      <div class="perm-head">
        <h3 class="sec">O que este nível pode fazer</h3>
        <div class="perm-atalhos" id="perf-atalhos"><button type="button" class="link" data-tudo="1">Tudo ver</button><button type="button" class="link" data-tudo="2">Tudo editar</button><button type="button" class="link" data-tudo="0">Limpar</button></div>
      </div>
      <p class="nivel-admin hidden" id="perf-admin-aviso">O nível Administrador sempre tem acesso total e gerencia usuários — só o nome e a descrição podem mudar.</p>
      <div class="perm-matriz" id="perf-matriz"></div>
      <div class="form-error" id="perf-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-perf" type="button">Salvar</button></footer>
  </div>
</div>
