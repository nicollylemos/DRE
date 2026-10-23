<?php use App\Core\Auth; use App\Core\View; ?>
<section class="page active" id="page-calendario">
  <header class="page-head">
    <div><h1>Calendário</h1><p>Contas a pagar e a receber pela data de vencimento. Clique num dia para ver os títulos e dar baixa.</p></div>
    <?php if (Auth::podeEditar('lancamentos')): ?>
    <button class="btn btn-gold" type="button" id="cal-novo"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Nova conta</button>
    <?php endif; ?>
  </header>

  <form class="toolbar" id="cal-toolbar" onsubmit="return false">
    <div class="month-step">
      <button type="button" class="x" id="cal-prev" aria-label="Mês anterior"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
      <strong class="cal-titulo" id="cal-titulo"></strong>
      <button type="button" class="x" id="cal-next" aria-label="Próximo mês"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
      <button type="button" class="btn btn-ghost sm" id="cal-hoje">Hoje</button>
    </div>
    <div class="pills" id="cal-f-tipo"><button type="button" data-v="" class="on">Tudo</button><button type="button" data-v="income">A receber</button><button type="button" data-v="expense">A pagar</button></div>
    <div class="pills" id="cal-f-sit"><button type="button" data-v="" class="on">Qualquer situação</button><button type="button" data-v="em_aberto">Em aberto</button><button type="button" data-v="vencido">Vencidos</button><button type="button" data-v="quitado">Quitados</button></div>
  </form>

  <div class="kpis">
    <div class="kpi"><span><i class="kd pos"></i>A receber no mês</span><strong class="pos" id="cal-k-rec">—</strong><small id="cal-k-rec-sub"></small></div>
    <div class="kpi"><span><i class="kd neg"></i>A pagar no mês</span><strong class="neg" id="cal-k-pag">—</strong><small id="cal-k-pag-sub"></small></div>
    <div class="kpi"><span><i class="kd warn"></i>Vencidos</span><strong id="cal-k-venc">—</strong><small id="cal-k-venc-sub"></small></div>
    <div class="kpi saldo"><span>Saldo previsto do mês</span><strong id="cal-k-saldo">—</strong><small>a receber − a pagar (em aberto)</small></div>
  </div>

  <div class="card agenda-card">
    <div class="agenda">
      <div>
        <div class="cal big" id="cal"></div>
        <div class="cal-legend"><span><i class="pos-bg"></i>A receber</span><span><i class="neg-bg"></i>A pagar</span><span><i class="late-ring"></i>Tem vencido</span><span><i class="done-bg"></i>Quitado</span></div>
      </div>
      <div class="agenda-list">
        <div class="agenda-head"><strong id="agenda-dia-titulo">Mês inteiro</strong><span class="muted" id="agenda-dia-tot"></span></div>
        <div id="agenda-itens"></div>
        <div class="agenda-foot hidden" id="agenda-foot"><button class="link" type="button" id="agenda-ver-mes">← Ver o mês inteiro</button>
          <?php if (Auth::podeEditar('lancamentos')): ?><button class="btn btn-ghost sm" type="button" id="agenda-novo-dia">+ Conta neste dia</button><?php endif; ?></div>
      </div>
    </div>
  </div>
</section>
<?= View::partial('partials/modal-lancamento') ?>
