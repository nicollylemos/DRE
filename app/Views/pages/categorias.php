<?php /** @var bool $podeEditar */ ?>
<section class="page active" id="page-categorias">
  <header class="page-head">
    <div><h1>Categorias</h1><p>Agrupe os lançamentos para entender de onde vem e para onde vai o dinheiro.</p></div>
    <?php if ($podeEditar): ?><button class="btn btn-gold" type="button" id="btn-nova-categoria"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Nova categoria</button><?php endif; ?>
  </header>
  <form class="toolbar" id="cat-toolbar">
    <div class="pills" id="cat-f-sit"><button type="button" data-v="1" class="on">Ativas</button><button type="button" data-v="0">Desativadas</button><button type="button" data-v="">Todas</button></div>
    <div class="pills" id="cat-f-tipo"><button type="button" data-v="" class="on">Receitas e despesas</button><button type="button" data-v="income">Receitas</button><button type="button" data-v="expense">Despesas</button></div>
    <label class="search"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="cat-f-busca" placeholder="Buscar categoria"/></label>
    <span class="count" id="cat-count"></span>
  </form>
  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="cat-table">
        <thead><tr><th>Categoria</th><th>Tipo</th><th>Descrição</th><th class="r">Lançamentos <span id="cat-ano-label"></span></th><th class="r">Total <span id="cat-ano-label2"></span></th><th>Situação</th><th class="act"></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <nav class="pager" id="cat-pager"></nav>
  </div>
</section>

<div class="modal sheet hidden" id="modal-cat">
  <div class="modal-box">
    <header class="modal-head"><h2 id="cat-modal-title">Nova Categoria</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="cat-form">
      <label class="field"><span>Nome *</span><input type="text" id="cat-nome" maxlength="150" placeholder="Ex: Aluguel, Consultas, Marketing"/></label>
      <div class="field"><span>Tipo *</span>
        <div class="seg" id="cat-tipo-seg"><button type="button" data-v="expense" class="on">Despesa</button><button type="button" data-v="income">Receita</button><button type="button" data-v="ambos">Ambos</button></div>
      </div>
      <div class="field"><span>Cor</span><div class="colors" id="cat-cores"></div></div>
      <label class="field"><span>Descrição</span><input type="text" id="cat-desc" maxlength="255" placeholder="Opcional — o que entra nessa categoria"/></label>
      <div class="field"><span>Situação</span>
        <div class="seg sm" id="cat-sit-seg"><button type="button" data-v="1" class="on">Ativa</button><button type="button" data-v="0">Desativada</button></div>
      </div>
      <div class="form-error" id="cat-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-cat" type="button">Salvar</button></footer>
  </div>
</div>
