<?php /** @var bool $podeEditar */ ?>
<section class="page active" id="page-importacao">
  <header class="page-head">
    <div><h1>Importar / Exportar</h1><p>Traga lançamentos, clientes e fornecedores de planilhas (Excel ou CSV) e baixe os dados do sistema.</p></div>
  </header>

  <div class="imp-grid">
    <div class="card imp-card">
      <header class="card-head"><div><h2>Importar planilha</h2><p class="muted">Você confere tudo antes de gravar — e pode desfazer depois.</p></div></header>
      <?php if (!$podeEditar): ?>
        <div class="vazio-box">Seu nível de acesso permite só <b>exportar</b>. Para importar, peça a um administrador o acesso "editar" em Importar / Exportar.</div>
      <?php else: ?>
      <ol class="steps" id="imp-steps">
        <li class="on" data-step="1"><b>1</b>Arquivo</li>
        <li data-step="2"><b>2</b>Colunas</li>
        <li data-step="3"><b>3</b>Conferir</li>
        <li data-step="4"><b>4</b>Pronto</li>
      </ol>

      <!-- Passo 1 -->
      <div class="imp-step" data-step="1">
        <div class="field"><span>O que você vai importar?</span>
          <div class="seg" id="imp-tipo"><button type="button" data-v="lancamentos" class="on">Lançamentos</button><button type="button" data-v="clientes">Clientes</button><button type="button" data-v="fornecedores">Fornecedores</button></div>
        </div>
        <label class="drop" id="imp-drop" tabindex="0">
          <input type="file" id="imp-arquivo" accept=".xlsx,.csv,.txt" hidden/>
          <svg viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2"/></svg>
          <strong id="imp-drop-t">Arraste a planilha aqui ou clique para escolher</strong>
          <small id="imp-drop-s">Excel (.xlsx) ou CSV · até 10 MB · até 5.000 linhas</small>
        </label>
        <label class="check"><input type="checkbox" id="imp-cabecalho" checked/><span>A primeira linha da planilha tem os nomes das colunas</span></label>
        <p class="muted imp-modelo">Não tem uma planilha pronta? Baixe o modelo:
          <a class="link" id="imp-modelo-xlsx" href="#">Excel</a> · <a class="link" id="imp-modelo-csv" href="#">CSV</a></p>
        <div class="form-error" id="imp-erro-1"></div>
        <div class="imp-acts"><button class="btn btn-navy" type="button" id="imp-ler" disabled><span class="spin"></span><span class="lbl">Ler planilha</span></button></div>
      </div>

      <!-- Passo 2 -->
      <div class="imp-step hidden" data-step="2">
        <p class="imp-info" id="imp-info-2"></p>
        <div class="map" id="imp-map"></div>
        <div class="imp-opcoes" id="imp-opcoes">
          <div class="field so-lanc"><span>Quando a planilha não diz se é receita ou despesa</span>
            <div class="select"><select id="imp-op-tipo"><option value="sinal">Valor negativo = despesa, positivo = receita</option><option value="expense">Considerar tudo despesa</option><option value="income">Considerar tudo receita</option></select></div></div>
          <div class="field so-lanc"><span>Quando não tem a situação</span>
            <div class="select"><select id="imp-op-status"><option value="quitado">Quitado (já pago/recebido)</option><option value="em_aberto">Em aberto (a pagar/receber)</option></select></div></div>
          <label class="check so-lanc"><input type="checkbox" id="imp-op-cat"/><span>Criar as categorias que ainda não existem</span></label>
          <label class="check so-lanc"><input type="checkbox" id="imp-op-dup" checked/><span>Pular lançamentos que já existem (mesma data, tipo, valor e descrição)</span></label>
        </div>
        <div class="form-error" id="imp-erro-2"></div>
        <div class="imp-acts"><button class="btn btn-ghost" type="button" data-voltar="1">Voltar</button><button class="btn btn-navy" type="button" id="imp-validar"><span class="spin"></span><span class="lbl">Conferir linhas</span></button></div>
      </div>

      <!-- Passo 3 -->
      <div class="imp-step hidden" data-step="3">
        <div class="imp-resumo" id="imp-resumo"></div>
        <div class="toolbar sm"><div class="pills" id="imp-f"><button type="button" data-v="" class="on">Todas</button><button type="button" data-v="ok">Prontas</button><button type="button" data-v="aviso">Com aviso</button><button type="button" data-v="duplicado">Duplicadas</button><button type="button" data-v="erro">Com erro</button></div></div>
        <div class="table-card flat">
          <div class="table-scroll imp-scroll"><table class="tbl compact" id="imp-prev"><thead></thead><tbody></tbody></table></div>
          <nav class="pager" id="imp-pager"></nav>
        </div>
        <div class="form-error" id="imp-erro-3"></div>
        <div class="imp-acts"><button class="btn btn-ghost" type="button" data-voltar="2">Voltar</button><button class="btn btn-gold" type="button" id="imp-confirmar"><span class="spin"></span><span class="lbl">Importar</span></button></div>
      </div>

      <!-- Passo 4 -->
      <div class="imp-step hidden" data-step="4">
        <div class="imp-ok" id="imp-ok"></div>
        <div class="imp-acts"><button class="btn btn-ghost" type="button" id="imp-nova">Importar outra planilha</button><a class="btn btn-navy" id="imp-ver" href="#">Ver registros importados</a></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="card exp-card">
      <header class="card-head"><div><h2>Exportar</h2><p class="muted">Lançamentos do exercício escolhido.</p></div><div class="select"><select id="exp-ano" aria-label="Exercício"></select></div></header>
      <div class="exp-list">
        <a class="exp-item" data-exp="excel" href="#"><span class="exp-ico xl">XLSX</span><div><b>Excel completo</b><small>Lançamentos, DRE resumo, por categoria e transferências</small></div></a>
        <a class="exp-item" data-exp="csv" href="#"><span class="exp-ico csv">CSV</span><div><b>CSV</b><small>Lançamentos separados por ponto e vírgula (abre no Excel)</small></div></a>
        <a class="exp-item" data-exp="pdf" href="#"><span class="exp-ico pdf">PDF</span><div><b>DRE em PDF</b><small>Demonstrativo anual com AV%, AH% e mês a mês</small></div></a>
        <a class="exp-item" data-exp="xml" href="#"><span class="exp-ico xml">XML</span><div><b>XML</b><small>Para integrar com outros sistemas</small></div></a>
      </div>
    </div>
  </div>

  <div class="card">
    <header class="card-head"><div><h2>Histórico de importações</h2><p class="muted">Desfazer apaga tudo o que aquela importação criou.</p></div></header>
    <div class="table-scroll">
      <table class="tbl" id="imp-hist"><thead><tr><th>Data</th><th>Tipo</th><th>Arquivo</th><th class="r">Importados</th><th class="r">Ignorados</th><th class="r">Saldo</th><th>Por</th><th>Situação</th><th class="act"></th></tr></thead><tbody></tbody></table>
    </div>
  </div>
</section>
