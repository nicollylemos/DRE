<?php /** @var bool $podeEditar */ ?>
<section class="page active" id="page-caixas">
  <header class="page-head">
    <div><h1>Caixas / Banco</h1><p>Contas bancárias, agências e caixas físicos com o saldo de hoje.</p></div>
    <?php if ($podeEditar): ?><button class="btn btn-gold" type="button" id="btn-novo-caixa"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Nova conta / caixa</button><?php endif; ?>
  </header>
  <div class="kpis">
    <div class="kpi saldo"><span>Saldo total hoje</span><strong id="cx-total">R$ 0,00</strong></div>
    <div class="kpi"><span>Em bancos</span><strong id="cx-bancos">R$ 0,00</strong></div>
    <div class="kpi"><span>Em caixa físico</span><strong id="cx-fisico">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd warn"></i>Saldo previsto</span><strong id="cx-previsto">R$ 0,00</strong><small>hoje + a receber − a pagar</small></div>
  </div>
  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="cx-table">
        <thead><tr><th>Conta / caixa</th><th>Banco</th><th>Agência</th><th>Conta</th><th class="r">Saldo inicial</th><th class="r">Saldo atual</th><th class="r">A receber</th><th class="r">A pagar</th><th class="act"></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
  <p class="foot-note warn" id="cx-aviso"></p>
  <p class="foot-note">Saldo atual = saldo inicial + receitas quitadas − despesas quitadas ± transferências, até hoje (mesmo critério do Fluxo de Caixa).</p>
</section>

<div class="modal sheet hidden" id="modal-cx">
  <div class="modal-box">
    <header class="modal-head"><h2 id="cx-modal-title">Nova conta / caixa</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="cx-form">
      <div class="field"><span>Tipo</span>
        <div class="seg" id="cx-tipo-seg"><button type="button" data-v="banco" class="on">Conta bancária</button><button type="button" data-v="caixa">Caixa físico</button><button type="button" data-v="investimento">Investimento</button></div>
      </div>
      <label class="field"><span>Nome *</span><input type="text" id="cx-nome" maxlength="100" placeholder="Ex: Itaú PJ, Caixa da recepção"/></label>
      <div class="grid" id="cx-banco-wrap">
        <label class="field span2"><span>Banco</span><input type="text" id="cx-banco" maxlength="100" list="lista-bancos" placeholder="Ex: Itaú, Nubank, Bradesco"/></label>
        <label class="field"><span>Agência</span><input type="text" id="cx-agencia" maxlength="6" inputmode="numeric" placeholder="0000-0"/><small class="hint">4 dígitos + dígito, se tiver</small></label>
        <label class="field"><span>Conta</span><input type="text" id="cx-conta" maxlength="14" inputmode="numeric" placeholder="00000-0"/><small class="hint">até 12 dígitos + dígito</small></label>
      </div>
      <label class="field"><span>Saldo inicial (R$)</span><input type="number" id="cx-saldo" step="0.01" placeholder="0,00"/><small class="hint">Quanto havia na conta antes do primeiro lançamento no sistema.</small></label>
      <label class="field"><span>Observações</span><input type="text" id="cx-obs" maxlength="255"/></label>
      <div class="form-error" id="cx-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-cx" type="button">Salvar</button></footer>
  </div>
</div>
<datalist id="lista-bancos"></datalist>
