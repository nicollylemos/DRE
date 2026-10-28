<?php use App\Core\Auth; use App\Core\View; /** @var bool $podeEditar */
$x = '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>';
$icoUp = '<svg viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2"/></svg>';
?>
<section class="page active" id="page-notas-fiscais">
  <header class="page-head">
    <div><h1>Notas fiscais</h1><p>Guarde o PDF e o XML das notas, vincule a lançamentos e contas a pagar/receber e preencha lançamentos direto do XML.</p></div>
    <?php if ($podeEditar): ?>
    <div class="head-actions">
      <button class="btn btn-ghost" type="button" id="btn-nf-lote"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2"/></svg>Importar XML e PDF</button>
      <button class="btn btn-gold" type="button" id="btn-nf-nova"><svg viewBox="0 0 24 24" class="ico-btn"><path d="M12 5v14M5 12h14"/></svg>Nova nota</button>
    </div>
    <?php endif; ?>
  </header>

  <div class="kpis">
    <div class="kpi"><span><i class="kd neg"></i>Entradas (compras)</span><strong id="nf-kpi-ent">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd pos"></i>Saídas (vendas)</span><strong id="nf-kpi-sai">R$ 0,00</strong></div>
    <div class="kpi"><span><i class="kd warn"></i>Sem lançamento</span><strong id="nf-kpi-sem">0</strong></div>
    <div class="kpi saldo"><span>Notas no período</span><strong id="nf-kpi-qtd">0</strong></div>
  </div>

  <form class="toolbar" id="nf-toolbar">
    <div class="pills" id="nf-f-tipo"><button type="button" data-v="" class="on">Todas</button><button type="button" data-v="entrada">Entrada</button><button type="button" data-v="saida">Saída</button></div>
    <div class="select"><select id="nf-f-vinculo" aria-label="Vínculo"><option value="">Com e sem lançamento</option><option value="sem">Sem lançamento</option><option value="com">Com lançamento</option></select></div>
    <div class="select"><select id="nf-f-mes" aria-label="Mês"></select></div>
    <div class="select"><select id="nf-f-ano" aria-label="Ano"></select></div>
    <label class="search"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="nf-f-busca" placeholder="Número, emitente, CNPJ ou chave"/></label>
  </form>
  <div class="filtro-ativo hidden" id="nf-filtro-tx"></div>

  <div class="table-card">
    <div class="table-scroll">
      <table class="tbl" id="nf-table">
        <thead><tr><th>Emissão</th><th>Nota</th><th>Emitente / destinatário</th><th class="r">Valor</th><th>Arquivos</th><th>Lançamento</th><th class="act"></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <nav class="pager" id="nf-pager"></nav>
  </div>
</section>

<!-- Nova / editar nota -->
<div class="modal sheet hidden" id="modal-nf">
  <div class="modal-box lg">
    <header class="modal-head"><div><h2 id="nf-modal-title">Nova nota fiscal</h2><p class="modal-sub">Anexe o XML pra preencher tudo sozinho. O PDF (DANFE) é opcional.</p></div><button class="x" type="button" data-close aria-label="Fechar"><?= $x ?></button></header>
    <form class="modal-body" id="nf-form" autocomplete="off">
      <div class="nf-arqs">
        <div class="nf-arq" id="nf-box-xml">
          <label class="drop sm" id="nf-drop-xml" tabindex="0">
            <input type="file" id="nf-xml" accept=".xml,text/xml,application/xml" hidden/>
            <?= $icoUp ?><strong>XML da nota</strong><small>preenche os campos automaticamente</small>
          </label>
          <div class="nf-atual hidden" id="nf-atual-xml"></div>
        </div>
        <div class="nf-arq" id="nf-box-pdf">
          <label class="drop sm" id="nf-drop-pdf" tabindex="0">
            <input type="file" id="nf-pdf" accept=".pdf,application/pdf" hidden/>
            <?= $icoUp ?><strong>PDF / DANFE</strong><small>até 15 MB</small>
          </label>
          <div class="nf-atual hidden" id="nf-atual-pdf"></div>
        </div>
      </div>
      <div class="taxa hidden" id="nf-lido"></div>

      <div class="field"><span>Tipo da nota</span>
        <div class="seg" id="nf-tipo-seg"><button type="button" data-v="entrada" class="on">Entrada · compra / despesa</button><button type="button" data-v="saida">Saída · venda / receita</button></div>
      </div>
      <div class="grid">
        <label class="field"><span>Modelo</span><select id="nf-modelo"><option value="">—</option><option>NF-e</option><option>NFC-e</option><option>NFS-e</option><option>Outro</option></select></label>
        <div class="grid inner2">
          <label class="field"><span>Número</span><input type="text" id="nf-numero" maxlength="20" inputmode="numeric"/></label>
          <label class="field"><span>Série</span><input type="text" id="nf-serie" maxlength="10"/></label>
        </div>
        <label class="field"><span>Data de emissão</span><input type="date" id="nf-emissao"/></label>
        <label class="field"><span>Valor total (R$)</span><input type="number" id="nf-valor" step="0.01" min="0" placeholder="0,00"/></label>
        <label class="field span2"><span>Chave de acesso / código de verificação</span><input type="text" id="nf-chave" maxlength="60" placeholder="44 dígitos da NF-e" inputmode="numeric"/></label>
        <label class="field"><span>Emitente</span><input type="text" id="nf-emit-nome" maxlength="255" placeholder="Quem emitiu a nota"/></label>
        <label class="field"><span>CNPJ / CPF do emitente</span><input type="text" id="nf-emit-doc" maxlength="20" inputmode="numeric"/></label>
        <label class="field"><span>Destinatário</span><input type="text" id="nf-dest-nome" maxlength="255" placeholder="Para quem a nota foi emitida"/></label>
        <label class="field"><span>CNPJ / CPF do destinatário</span><input type="text" id="nf-dest-doc" maxlength="20" inputmode="numeric"/></label>
        <label class="field span2"><span>Descrição</span><input type="text" id="nf-descricao" maxlength="255" placeholder="Produtos ou serviço"/></label>
        <label class="field span2"><span>Observações</span><textarea id="nf-obs" rows="2"></textarea></label>
      </div>
      <div class="nf-vinc-box" id="nf-vinc-box"></div>
      <?php if (Auth::podeEditar('lancamentos')): ?>
      <label class="check" id="nf-gerar-wrap"><input type="checkbox" id="nf-gerar" checked/><span>Depois de salvar, abrir o lançamento já preenchido com os dados da nota</span></label>
      <?php endif; ?>
      <div class="form-error" id="nf-error"></div>
    </form>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="btn-salvar-nf" type="button"><span class="spin"></span><span class="lbl">Salvar</span></button></footer>
  </div>
</div>

<!-- Visualizar -->
<div class="modal sheet hidden" id="modal-nf-ver">
  <div class="modal-box xl">
    <header class="modal-head"><div><h2 id="nfv-title"></h2><p class="modal-sub" id="nfv-sub"></p></div><button class="x" type="button" data-close aria-label="Fechar"><?= $x ?></button></header>
    <div class="modal-body nfv-body">
      <div class="tabs" id="nfv-tabs" role="tablist">
        <button type="button" data-tab="dados" class="on">Dados</button>
        <button type="button" data-tab="pdf" id="nfv-tab-pdf">PDF</button>
        <button type="button" data-tab="xml" id="nfv-tab-xml">XML</button>
      </div>
      <div class="tab-pane" data-pane="dados"><dl class="details" id="nfv-dados"></dl></div>
      <div class="tab-pane hidden" data-pane="pdf"><div class="nfv-pdf" id="nfv-pdf"></div></div>
      <div class="tab-pane hidden" data-pane="xml"><div id="nfv-xml-resumo"></div><pre class="nfv-xml" id="nfv-xml"></pre></div>
    </div>
    <footer class="modal-foot spread"><div id="nfv-foot-l"></div><div id="nfv-foot-r"></div></footer>
  </div>
</div>

<!-- Vincular a lançamento -->
<div class="modal sheet hidden" id="modal-nf-vinc">
  <div class="modal-box lg">
    <header class="modal-head"><div><h2>Vincular a um lançamento</h2><p class="modal-sub" id="nfl-sub"></p></div><button class="x" type="button" data-close aria-label="Fechar"><?= $x ?></button></header>
    <div class="modal-body">
      <div class="toolbar sm nfl-tools">
        <label class="search"><svg class="ico-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" id="nfl-busca" placeholder="Descrição ou fornecedor/cliente"/></label>
        <label class="check"><input type="checkbox" id="nfl-aberto"/><span>Só contas em aberto (a pagar / a receber)</span></label>
      </div>
      <p class="muted">Ordenado pelo valor mais parecido com o da nota e pela data mais próxima.</p>
      <div class="nfl-lista" id="nfl-lista" role="radiogroup"></div>
      <div class="form-error" id="nfl-error"></div>
    </div>
    <footer class="modal-foot spread"><div><button class="btn btn-ghost danger hidden" id="nfl-desvincular" type="button">Tirar vínculo</button></div><div><button class="btn btn-ghost" data-close type="button">Cancelar</button><button class="btn btn-navy" id="nfl-ok" type="button">Vincular</button></div></footer>
  </div>
</div>

<!-- Importar em lote -->
<div class="modal sheet hidden" id="modal-nf-lote">
  <div class="modal-box lg">
    <header class="modal-head"><div><h2>Importar XML e PDF</h2><p class="modal-sub">Selecione várias notas de uma vez. O PDF é juntado ao XML pela chave ou pelo número da nota no nome do arquivo.</p></div><button class="x" type="button" data-close aria-label="Fechar"><?= $x ?></button></header>
    <div class="modal-body">
      <div id="lote-passo1" class="stack">
        <label class="drop" id="lote-drop" tabindex="0">
          <input type="file" id="lote-arquivos" accept=".xml,.pdf" multiple hidden/>
          <?= $icoUp ?><strong id="lote-drop-t">Arraste os arquivos aqui ou clique para escolher</strong>
          <small id="lote-drop-s">XML e PDF · até 20 arquivos por vez (10 notas com XML + PDF)</small>
        </label>
        <div class="lote-arqs hidden" id="lote-arqs"></div>
        <?php if (Auth::podeEditar('lancamentos')): ?>
        <label class="check"><input type="checkbox" id="lote-gerar"/><span>Criar um lançamento para cada nota (despesa nas de entrada, receita nas de saída)</span></label>
        <label class="field hidden" id="lote-sit-wrap"><span>Situação dos lançamentos</span>
          <div class="select"><select id="lote-sit"><option value="auto">Automático: em aberto se a nota tem duplicata a vencer</option><option value="em_aberto">Tudo em aberto (contas a pagar/receber)</option><option value="quitado">Tudo quitado</option></select></div></label>
        <?php endif; ?>
      </div>
      <div id="lote-passo2" class="hidden">
        <div class="imp-resumo" id="lote-resumo"></div>
        <div class="table-scroll" style="max-height:46vh"><table class="tbl compact" id="lote-res"><thead><tr><th>Arquivo(s)</th><th>Resultado</th></tr></thead><tbody></tbody></table></div>
      </div>
      <div class="form-error" id="lote-error"></div>
    </div>
    <footer class="modal-foot"><button class="btn btn-ghost" data-close type="button" id="lote-fechar">Cancelar</button><button class="btn btn-navy" id="lote-ok" type="button" disabled><span class="spin"></span><span class="lbl">Importar</span></button></footer>
  </div>
</div>
<?= View::partial('partials/modal-lancamento') ?>
