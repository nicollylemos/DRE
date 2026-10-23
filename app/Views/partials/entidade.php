<?php
/**
 * Tela de Parceiros ou Fornecedores (mesmo layout, campos ajustados pelo JS).
 * @var string $prefixo @var string $titulo @var string $descricao @var string $novo @var string $docLabel @var bool $podeEditar
 */
?>
<section class="page active" id="page-<?= e($prefixo) ?>">
  <header class="page-head">
    <div><h1><?= e($titulo) ?></h1><p><?= e($descricao) ?></p></div>
    <?php if ($podeEditar): ?><button class="btn btn-gold" type="button" id="btn-novo-ent"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg><?= e($novo) ?></button><?php endif; ?>
  </header>
  <form class="toolbar" id="ent-toolbar">
    <div class="pills" id="ent-f-sit"><button type="button" data-v="1" class="on">Ativos</button><button type="button" data-v="0">Desativados</button><button type="button" data-v="">Todos</button></div>
    <label class="search"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="ent-f-busca" placeholder="Buscar por nome, razão social ou documento"/></label>
    <span class="count" id="ent-count"></span>
  </form>
  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="ent-table">
        <thead><tr><th>Nome</th><th>Razão social</th><th><?= e($docLabel) ?></th><th>Responsável</th><th>WhatsApp</th><th>Situação</th><th class="act"></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <nav class="pager" id="ent-pager"></nav>
  </div>
</section>

<!-- Cadastro -->
<div class="modal sheet hidden" id="modal-ent">
  <div class="modal-box lg">
    <header class="modal-head"><h2 id="ent-modal-title"></h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="ent-form">
      <div class="grid">
        <label class="field"><span>Nome *</span><input type="text" id="ent-nome" maxlength="150"/></label>
        <label class="field"><span id="ent-razao-label">Razão Social *</span><input type="text" id="ent-razao" maxlength="255"/></label>
        <div class="field"><span id="ent-doc-label">CNPJ *</span>
          <div class="input-btn"><input type="text" id="ent-doc" inputmode="numeric" maxlength="18" placeholder="00.000.000/0000-00"/><button type="button" class="btn btn-ghost sm" id="btn-buscar-cnpj" title="Preenche os dados pela Receita (BrasilAPI)">Buscar</button></div>
          <small class="hint" id="ent-doc-hint"></small>
        </div>
        <label class="field"><span>E-mail</span><input type="email" id="ent-email" maxlength="150"/></label>
        <label class="field"><span id="ent-resp-label">Responsável *</span><input type="text" id="ent-resp" maxlength="150"/></label>
        <label class="field"><span id="ent-contato-label">Contato *</span><input type="text" id="ent-contato" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000"/></label>
        <label class="field"><span>WhatsApp</span><input type="text" id="ent-whats" inputmode="numeric" maxlength="15" placeholder="(00) 00000-0000"/></label>
        <label class="field" id="ent-comissao-wrap"><span>Comissão (R$)</span><input type="number" id="ent-comissao" step="0.01" min="0" placeholder="0,00"/></label>
        <label class="field span2"><span>Endereço</span><input type="text" id="ent-endereco" maxlength="255"/></label>
        <label class="field span2"><span>Observações</span><input type="text" id="ent-obs" placeholder="Condições, vigência do contrato, etc."/></label>
        <div class="field"><span>Contrato</span>
          <div class="file-pick"><button type="button" class="btn btn-ghost sm" id="ent-contrato-btn">Anexar arquivo</button><span id="ent-contrato-nome" class="file-name">Nenhum arquivo</span></div>
          <input type="file" id="ent-contrato" accept=".pdf,.doc,.docx,.odt,image/*" hidden/>
        </div>
        <div class="field"><span>Situação</span>
          <div class="seg sm" id="ent-sit-seg"><button type="button" data-v="1" class="on">Ativo</button><button type="button" data-v="0">Desativado</button></div>
        </div>
      </div>
      <div class="form-error" id="ent-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-ent" type="button">Salvar</button></footer>
  </div>
</div>

<!-- Contratos -->
<div class="modal sheet hidden" id="modal-contrato">
  <div class="modal-box">
    <header class="modal-head"><div><h2>Contrato</h2><p class="modal-sub" id="contrato-sub"></p></div><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <div class="modal-body">
      <h3 class="sec">Contrato principal</h3>
      <div id="contrato-principal"></div>
      <h3 class="sec">Aditivos e outros documentos</h3>
      <div id="contrato-extras" class="doc-list"></div>
      <?php if ($podeEditar): ?>
      <div class="add-doc">
        <input type="text" id="contrato-desc" placeholder="Descrição (ex: Aditivo 2026)" maxlength="255"/>
        <button type="button" class="btn btn-gold sm" id="contrato-extra-btn">Adicionar arquivo</button>
      </div>
      <?php endif; ?>
      <input type="file" id="contrato-extra-file" accept=".pdf,.doc,.docx,.odt,image/*" hidden/>
      <input type="file" id="contrato-main-file" accept=".pdf,.doc,.docx,.odt,image/*" hidden/>
      <div class="form-error" id="contrato-error"></div>
    </div>
  </div>
</div>
