<?php /** @var bool $podeEditar */ ?>
<section class="page active" id="page-formas">
  <header class="page-head">
    <div><h1>Formas de pagamento</h1><p>Cartões, PIX, boleto, transferência e dinheiro, com prazo de recebimento, taxas e tarifas usadas no cálculo do valor líquido. Formas inativas somem dos novos lançamentos, mas continuam nos antigos.</p></div>
    <?php if ($podeEditar): ?><button class="btn btn-gold" type="button" id="btn-nova-forma"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Nova forma</button><?php endif; ?>
  </header>
  <form class="toolbar" id="fp-toolbar">
    <div class="pills" id="fp-f-tipo"><button type="button" data-v="" class="on">Todas</button><button type="button" data-v="credito">Crédito</button><button type="button" data-v="debito">Débito</button><button type="button" data-v="pix">PIX</button><button type="button" data-v="boleto">Boleto</button><button type="button" data-v="transferencia">Transferência</button><button type="button" data-v="dinheiro">Dinheiro</button></div>
    <div class="select"><select id="fp-f-sit" aria-label="Situação"><option value="">Ativas e inativas</option><option value="1" selected>Só ativas</option><option value="0">Só inativas</option></select></div>
    <span class="count" id="fp-count"></span>
  </form>
  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="fp-table">
        <thead><tr><th>Nome</th><th>Tipo</th><th>Banco / Agência</th><th class="r">Parcelas</th><th class="r">Recebe em</th><th class="r">Taxa intermed.</th><th class="r">Taxa parcel.</th><th class="r">Tarifa fixa</th><th class="act"></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</section>

<div class="modal sheet hidden" id="modal-fp">
  <div class="modal-box lg">
    <header class="modal-head"><h2 id="fp-modal-title">Nova forma de pagamento</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="fp-form">
      <div class="field"><span>Tipo *</span>
        <div class="seg wrap" id="fp-tipo-seg"><button type="button" data-v="credito" class="on">Cartão de crédito</button><button type="button" data-v="debito">Cartão de débito</button><button type="button" data-v="pix">PIX</button><button type="button" data-v="boleto">Boleto</button><button type="button" data-v="transferencia">Transferência</button><button type="button" data-v="dinheiro">Dinheiro</button></div>
      </div>
      <div class="grid">
        <label class="field span2"><span>Nome *</span><input type="text" id="fp-nome" maxlength="100" placeholder="Ex: Visa Crédito 3x, PIX Itaú, TED Itaú"/></label>
        <label class="field fp-banco"><span>Banco</span><input type="text" id="fp-banco" maxlength="100" list="lista-bancos" placeholder="Ex: Itaú"/></label>
        <label class="field fp-banco"><span>Agência</span><input type="text" id="fp-agencia" maxlength="6" inputmode="numeric" placeholder="0000-0"/></label>
        <label class="field fp-credito"><span>Parcelas</span><input type="text" id="fp-parcelas" maxlength="2" inputmode="numeric" value="1"/><small class="hint">de 1 a 48</small></label>
        <label class="field"><span>Dias para recebimento</span><input type="text" id="fp-dias" maxlength="3" inputmode="numeric" value="0"/><small class="hint">de 0 a 365 (0 = na hora)</small></label>
        <label class="field"><span>Taxa de intermediação (%)</span><input type="text" id="fp-taxa" maxlength="6" inputmode="decimal" placeholder="0,00"/><small class="hint">de 0 a 100, com até 2 casas</small></label>
        <label class="field fp-credito"><span>Taxa de parcelamento (%)</span><input type="text" id="fp-taxa-parc" maxlength="6" inputmode="decimal" placeholder="0,00"/><small class="hint">de 0 a 100, com até 2 casas</small></label>
        <label class="field"><span>Tarifa fixa por transação (R$)</span><input type="text" id="fp-tarifa" maxlength="7" inputmode="decimal" placeholder="0,00"/><small class="hint">até R$ 9.999,99</small></label>
      </div>
      <div class="taxa" id="fp-simula"></div>
      <div class="form-error" id="fp-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-fp" type="button">Salvar</button></footer>
  </div>
</div>
<datalist id="lista-bancos"></datalist>
