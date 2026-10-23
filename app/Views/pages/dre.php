<section class="page active" id="page-dre">
  <header class="page-head">
    <div><h1>DRE</h1><p>Demonstrativo do Resultado do Exercício: receitas, deduções, despesas e resultado, mês a mês.</p></div>
    <div class="head-actions">
      <button class="btn btn-ghost" type="button" id="dre-excel"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>Excel</button>
      <button class="btn btn-ghost" type="button" id="dre-pdf"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>PDF</button>
    </div>
  </header>

  <form class="toolbar" id="dre-toolbar" onsubmit="return false">
    <div class="month-step">
      <button type="button" class="x" id="dre-prev" aria-label="Ano anterior"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg></button>
      <div class="select"><select id="dre-ano" aria-label="Exercício"></select></div>
      <button type="button" class="x" id="dre-next" aria-label="Próximo ano"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></button>
    </div>
    <div class="pills" id="dre-agrupar"><button type="button" data-v="categoria" class="on">Por categoria</button><button type="button" data-v="grupo">Por grupo / conta</button><button type="button" data-v="centro">Por centro de custo</button></div>
    <div class="pills" id="dre-regime" title="Competência: pela data do lançamento. Caixa: só o que já foi pago/recebido, pela data do pagamento.">
      <button type="button" data-v="competencia" class="on">Competência</button><button type="button" data-v="caixa">Caixa</button>
    </div>
    <label class="check inline"><input type="checkbox" id="dre-meses" checked/><span>Mostrar meses</span></label>
  </form>

  <div class="kpis">
    <div class="kpi"><span><i class="kd pos"></i>Receita líquida</span><strong id="dre-k-liq">—</strong><small id="dre-k-liq-sub"></small></div>
    <div class="kpi"><span><i class="kd neg"></i>Despesas</span><strong id="dre-k-desp">—</strong><small id="dre-k-desp-sub"></small></div>
    <div class="kpi saldo"><span>Resultado líquido</span><strong id="dre-k-res">—</strong><small id="dre-k-res-sub"></small></div>
    <div class="kpi"><span><i class="kd warn"></i>Margem líquida</span><strong id="dre-k-margem">—</strong><small id="dre-k-margem-sub"></small></div>
  </div>

  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl dre-tbl" id="dre-table"><thead></thead><tbody></tbody></table>
    </div>
  </div>
  <p class="foot-note">AV% = participação no total da receita líquida. AH% = variação em relação ao ano anterior inteiro (no ano corrente, compare com cuidado: o ano ainda não fechou). Receita sempre líquida das taxas da forma de pagamento (MDR).</p>
</section>
