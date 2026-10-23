<section class="page active" id="page-fluxo">
  <header class="page-head">
    <div><h1>Fluxo de Caixa</h1><p>Movimentação dia a dia: o que já entrou e saiu (realizado) e o que ainda vai vencer (previsto).</p></div>
  </header>
  <form class="toolbar" id="fx-toolbar">
    <div class="month-step"><button type="button" class="x" id="fx-prev" aria-label="Mês anterior"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
      <div class="select"><select id="fx-mes" aria-label="Mês"></select></div>
      <div class="select"><select id="fx-ano" aria-label="Ano"></select></div>
      <button type="button" class="x" id="fx-next" aria-label="Próximo mês"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button></div>
    <div class="select"><select id="fx-caixa" aria-label="Caixa"><option value="">Todos os caixas</option></select></div>
    <div class="pills" id="fx-dias"><button type="button" data-v="mov" class="on">Dias com movimento</button><button type="button" data-v="">Todos os dias</button></div>
  </form>
  <div class="kpis five">
    <div class="kpi"><span>Saldo de abertura</span><strong id="fx-abertura">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd pos"></i>Entradas (realizado)</span><strong id="fx-rec">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd neg"></i>Saídas (realizado)</span><strong id="fx-desp">R$ 0,00</strong></div>
    <div class="kpi saldo"><span>Saldo final (realizado)</span><strong id="fx-saldo">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd warn"></i>Saldo projetado</span><strong id="fx-proj">R$ 0,00</strong><small id="fx-proj-sub"></small></div>
  </div>
  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="fx-table">
        <thead><tr><th>Data</th><th class="r">Entradas</th><th class="r">Saídas</th><th class="r">Transferências</th><th class="r">Resultado do dia</th><th class="r">Saldo realizado</th><th class="r">Previsto (líq.)</th><th class="r">Saldo projetado</th></tr></thead>
        <tbody></tbody>
        <tfoot></tfoot>
      </table>
    </div>
  </div>
</section>
