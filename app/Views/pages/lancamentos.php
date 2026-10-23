<?php use App\Core\View; /** @var bool $podeEditar */ ?>
<section class="page active" id="page-lancamentos">
  <header class="page-head">
    <div><h1>Lançamentos</h1><p>Receitas e despesas da empresa, organizadas por categoria.</p></div>
    <?php if ($podeEditar): ?>
    <button class="btn btn-gold" type="button" id="btn-novo-tx"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Novo lançamento</button>
    <?php endif; ?>
  </header>

  <div class="kpis">
    <div class="kpi"><span><i class="kd pos"></i>Receitas</span><strong id="kpi-rec">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd neg"></i>Despesas</span><strong id="kpi-desp">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd warn"></i>Em aberto</span><strong id="kpi-aberto">R$ 0,00</strong></div>
    <div class="kpi saldo"><span>Saldo do período</span><strong id="kpi-saldo">R$ 0,00</strong></div>
  </div>

  <form class="toolbar" id="tx-toolbar">
    <div class="pills" id="tx-f-tipo"><button type="button" data-v="" class="on">Tudo</button><button type="button" data-v="income">Receitas</button><button type="button" data-v="expense">Despesas</button></div>
    <div class="select"><select id="tx-f-status" aria-label="Situação"><option value="">Qualquer situação</option><option value="quitado">Quitados</option><option value="em_aberto">Em aberto</option></select></div>
    <div class="select"><select id="tx-f-categoria" aria-label="Categoria"><option value="">Todas as categorias</option></select></div>
    <div class="select"><select id="tx-f-mes" aria-label="Mês"></select></div>
    <div class="select"><select id="tx-f-ano" aria-label="Ano"></select></div>
    <label class="search"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="tx-f-busca" placeholder="Buscar descrição ou fornecedor"/></label>
  </form>
  <div class="filtro-ativo hidden" id="tx-filtro-imp"></div>

  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="tx-table">
        <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Fornecedor / cliente</th><th class="r">Valor</th><th>Vencimento</th><th>Situação</th><th class="act"></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <nav class="pager" id="tx-pager"></nav>
  </div>
</section>
<?= View::partial('partials/modal-lancamento') ?>
