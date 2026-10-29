<!-- Lançamento (usado em Lançamentos e no Calendário) -->
<div class="modal sheet hidden" id="modal-tx">
  <div class="modal-box lg">
    <header class="modal-head"><h2 id="tx-modal-title">Novo Lançamento</h2><button class="x" type="button" data-close aria-label="Fechar"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
    <form class="modal-body" id="tx-form">
      <div class="seg" id="tx-tipo-seg">
        <button type="button" data-tipo="income">Receita</button>
        <button type="button" data-tipo="expense" class="on">Despesa</button>
      </div>
      <div class="grid">
        <label class="field"><span>Data *</span><input type="date" id="tx-data" required/></label>
        <label class="field"><span>Valor (R$) *</span><input type="number" id="tx-valor" step="0.01" min="0" placeholder="0,00" required/></label>
        <label class="field"><span>Categoria</span><select id="tx-categoria"></select></label>
        <label class="field"><span>Descrição</span><input type="text" id="tx-descricao" maxlength="255" placeholder="Ex: Aluguel de outubro"/></label>
        <label class="field"><span id="tx-pessoa-label">Fornecedor</span><select id="tx-pessoa"></select></label>
        <label class="field"><span>Forma de pagamento</span><select id="tx-forma"></select></label>
        <label class="field"><span>Caixa / Banco</span><select id="tx-caixa"></select></label>
        <label class="field"><span>Centro de custo</span><select id="tx-centro"></select></label>
        <label class="field"><span>Situação</span><select id="tx-status"><option value="quitado">Quitado</option><option value="em_aberto">Em aberto</option></select></label>
        <label class="field"><span>Vencimento <b class="obr hidden" id="tx-venc-obr">*</b></span><input type="date" id="tx-venc"/></label>
        <label class="field"><span>Data de pagamento <b class="obr" id="tx-pag-obr">*</b></span><input type="date" id="tx-pag"/></label>
        <label class="field"><span>Grupo / Conta</span><select id="tx-conta"></select></label>
        <label class="field span2"><span>Observações</span><input type="text" id="tx-obs" placeholder="Notas adicionais"/></label>
        <div class="field span2"><span>Comprovante <small class="opc">PDF ou foto do pagamento · até 10 MB</small></span>
          <div class="tx-comp">
            <label class="btn btn-ghost" for="tx-comp-file"><svg viewBox="0 0 24 24" class="ico-btn"><path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/></svg><span id="tx-comp-btn">Anexar comprovante</span></label>
            <input type="file" id="tx-comp-file" accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/*" hidden/>
            <span class="tx-comp-nome" id="tx-comp-nome"></span>
            <button type="button" class="btn btn-ghost xs danger hidden" id="tx-comp-remover">Remover</button>
          </div>
        </div>
      </div>
      <div class="taxa hidden" id="tx-taxa">Taxa da forma de pagamento: <strong id="tx-taxa-v"></strong> · Valor líquido: <strong id="tx-liq-v"></strong></div>
      <div class="form-error" id="tx-error"></div>
    </form>
    <footer class="modal-foot spread"><div><button class="btn btn-ghost danger hidden" id="btn-apagar-tx" type="button">Apagar lançamento</button></div><div><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-tx" type="button">Salvar</button></div></footer>
  </div>
</div>
<input type="file" id="tx-comp-picker" accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/*" hidden/>
