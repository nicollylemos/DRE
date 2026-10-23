<?php /** @var bool $podeEditar */ ?>
<section class="page active" id="page-clientes">
  <header class="page-head">
    <div><h1>Clientes</h1><p>Quem compra da empresa: documento, endereço e contatos para usar nos lançamentos.</p></div>
    <?php if ($podeEditar): ?><button class="btn btn-gold" type="button" id="btn-novo-cliente"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Novo cliente</button><?php endif; ?>
  </header>
  <form class="toolbar" id="cli-toolbar">
    <label class="search"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="cli-f-busca" placeholder="Buscar por nome, razão social, CPF/CNPJ ou cidade"/></label>
    <span class="count" id="cli-count"></span>
  </form>
  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="cli-table">
        <thead><tr><th>Nome</th><th>CPF / CNPJ</th><th>Cidade / UF</th><th>Telefone</th><th>E-mail</th><th class="r">Recebido</th><th class="act"></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <nav class="pager" id="cli-pager"></nav>
  </div>
</section>

<div class="modal sheet hidden" id="modal-cli">
  <div class="modal-box lg">
    <header class="modal-head"><h2 id="cli-modal-title">Novo Cliente</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="cli-form">
      <div class="grid">
        <label class="field"><span>Nome *</span><input type="text" id="cli-nome" maxlength="255" placeholder="Como o cliente é conhecido"/></label>
        <label class="field"><span>Razão social</span><input type="text" id="cli-razao" maxlength="255"/></label>
        <div class="field"><span>CPF ou CNPJ</span>
          <div class="input-btn"><input type="text" id="cli-doc" inputmode="numeric" maxlength="18" placeholder="CPF ou CNPJ"/><button type="button" class="btn btn-ghost sm" id="btn-cli-cnpj" title="Preenche os dados pela Receita (BrasilAPI)">Buscar</button></div>
          <small class="hint" id="cli-doc-hint"></small>
        </div>
        <label class="field"><span>E-mail</span><input type="email" id="cli-email" maxlength="150"/></label>
        <label class="field"><span>Telefone</span><input type="text" id="cli-tel" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000"/></label>
        <div class="grid inner">
          <label class="field"><span>Estado</span><select id="cli-uf"></select></label>
          <label class="field"><span>Cidade</span><input type="text" id="cli-cidade" maxlength="100"/></label>
        </div>
        <label class="field span2"><span>Endereço</span><input type="text" id="cli-endereco" maxlength="255" placeholder="Rua, número, bairro, CEP"/></label>
        <label class="field span2"><span>Observações</span><textarea id="cli-obs" rows="3"></textarea></label>
      </div>
      <div class="form-error" id="cli-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-cli" type="button">Salvar</button></footer>
  </div>
</div>
