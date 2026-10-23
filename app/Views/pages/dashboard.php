<?php use App\Core\Auth; ?>
<section class="page active" id="page-dashboard">
  <header class="page-head">
    <div><h1>Dashboard</h1><p>Visão geral do exercício: resultado, evolução mensal, contas a vencer e saldos.</p></div>
    <div class="head-actions">
      <div class="select"><select id="dash-ano" aria-label="Exercício"></select></div>
      <button class="btn btn-ghost" type="button" id="btn-dash-excel" title="Baixar o exercício em Excel"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>Excel</button>
    </div>
  </header>

  <div class="kpis">
    <div class="kpi"><span><i class="kd pos"></i>Receita bruta</span><strong id="dash-rec">—</strong><small id="dash-rec-sub"></small></div>
    <div class="kpi"><span><i class="kd neg"></i>Despesas</span><strong id="dash-desp">—</strong><small id="dash-desp-sub"></small></div>
    <div class="kpi saldo"><span>Lucro / prejuízo líquido</span><strong id="dash-lucro">—</strong><small id="dash-lucro-sub"></small></div>
    <div class="kpi"><span><i class="kd warn"></i>Margem líquida</span><strong id="dash-margem">—</strong><small id="dash-margem-sub">lucro ÷ receita líquida</small></div>
  </div>
  <div class="kpis mini">
    <div class="kpi"><span>A receber (em aberto)</span><strong class="pos" id="dash-areceber">—</strong></div>
    <div class="kpi"><span>A pagar (em aberto)</span><strong class="neg" id="dash-apagar">—</strong></div>
    <div class="kpi"><span>Vencidos</span><strong id="dash-vencidos">—</strong><small id="dash-vencidos-sub"></small></div>
    <div class="kpi"><span>Saldo em caixas / bancos</span><strong id="dash-saldo-cx">—</strong></div>
  </div>

  <div class="dash-grid">
    <div class="card">
      <header class="card-head"><h2>Resultado do exercício</h2><?php if (Auth::pode('dre')): ?><a class="link" href="<?= e(url('dre')) ?>">Ver DRE completo →</a><?php endif; ?></header>
      <dl class="dre-lines" id="dash-dre"></dl>
      <div class="compara" id="dash-compara"></div>
    </div>
    <div class="card">
      <header class="card-head"><h2>Receitas x despesas por mês</h2>
        <div class="legend"><span><i style="background:var(--c-rec)"></i>Receita líquida</span><span><i style="background:var(--c-desp)"></i>Despesas</span></div>
      </header>
      <div class="chart" id="dash-chart"></div>
    </div>
  </div>

  <div class="dash-grid b">
    <div class="card">
      <header class="card-head"><div><h2>Próximos vencimentos</h2><p class="muted">Em aberto nos próximos 7 dias, incluindo os atrasados.</p></div>
        <?php if (Auth::pode('calendario')): ?><a class="link" href="<?= e(url('calendario')) ?>">Abrir calendário →</a><?php endif; ?></header>
      <div class="prox" id="dash-proximos"></div>
    </div>
    <div class="card">
      <header class="card-head"><h2>Saldo por conta</h2><?php if (Auth::pode('cad-caixas')): ?><a class="link" href="<?= e(url('caixas')) ?>">Caixas / Banco →</a><?php endif; ?></header>
      <div class="saldos" id="dash-caixas"></div>
    </div>
  </div>

  <div class="card">
    <header class="card-head"><h2>Despesas por categoria</h2><span class="muted" id="dash-cat-ano"></span></header>
    <div class="cat-bars" id="dash-cats"></div>
  </div>
</section>
