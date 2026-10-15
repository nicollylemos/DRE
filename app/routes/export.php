<?php
// ── EXPORTAÇÕES (Excel, CSV, XML, PDF) ────────────────────────────────────────
require_once APP_DIR . '/lib/XlsxWriter.php';
require_once APP_DIR . '/lib/PdfWriter.php';

function download(string $content, string $mime, string $filename): void {
    header("Content-Type: $mime");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}
function brl(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

// Excel/CSV/XML/PDF ficam liberados pra quem vê Importar/Exportar, e também pros
// relatórios que têm botão de download próprio (Dashboard, DRE Resumo, DRE Detalhado).
function permitir_export(): void { permitir('import', 'dashboard', 'dre-resumo', 'dre-detalhado'); }

const CAB_LANC = ['ID', 'Data', 'Tipo', 'Categoria', 'Centro de Custo', 'Grupo', 'Conta', 'Forma Pagamento', 'Caixa/Banco', 'Cliente/Fornecedor', 'Descrição', 'Valor Bruto', 'Taxa MDR', 'Valor Líquido', 'Status'];
function linha_lanc(array $t): array {
    return [(int) $t['id'], substr((string) $t['data'], 0, 10), $t['tipo'] === 'income' ? 'Receita' : 'Despesa', (string) $t['categoria_nome'],
        (string) $t['centro_custo_nome'], (string) $t['grupo'], (string) $t['conta_nome'], (string) $t['forma_pagamento_nome'], (string) $t['caixa_nome'],
        (string) $t['cliente_fornecedor'], (string) $t['descricao'], (float) $t['valor'], (float) $t['taxa_mdr'],
        (float) ($t['valor_liquido'] ?? $t['valor']), (string) $t['status']];
}

Router::get('export/excel', function () {
    permitir_export();
    $y = ano_param();
    $txs = q('SELECT * FROM transactions WHERE YEAR(data)=? ORDER BY data, id', [$y]);
    $trs = q('SELECT * FROM transfers WHERE YEAR(data)=? ORDER BY data, id', [$y]);
    $x = new XlsxWriter();
    $x->addSheet('Lançamentos', array_merge([CAB_LANC], array_map('linha_lanc', $txs)));

    // DRE Resumo com receita líquida (mesmo critério das telas)
    $dre = [['Mês', 'Receita Bruta', 'Taxas/Deduções', 'Receita Líquida', 'Despesas', 'Lucro']];
    foreach (MESES_LONGOS as $i => $nome) {
        $rb = $ded = $de = 0.0;
        foreach ($txs as $t) {
            if (mes_de($t['data']) !== $i + 1) continue;
            if ($t['tipo'] === 'income') { $rb += (float) $t['valor']; $ded += (float) $t['taxa_mdr']; } else $de += (float) $t['valor'];
        }
        $dre[] = [$nome, $rb, $ded, $rb - $ded, $de, $rb - $ded - $de];
    }
    $x->addSheet('DRE Resumo', $dre);

    $cats = [['Categoria', 'Tipo', 'Qtd. Lançamentos', 'Total']];
    $agg = [];
    foreach ($txs as $t) {
        $k = ($t['categoria_nome'] ?: 'Sem categoria') . '|' . $t['tipo'];
        $agg[$k] = ($agg[$k] ?? [0, 0.0]); $agg[$k][0]++; $agg[$k][1] += (float) $t['valor'];
    }
    foreach ($agg as $k => [$qtd, $tot]) { [$c, $tp] = explode('|', $k); $cats[] = [$c, $tp === 'income' ? 'Receita' : 'Despesa', $qtd, $tot]; }
    $x->addSheet('Por Categoria', $cats);

    $x->addSheet('Transferências', array_merge([['ID', 'Data', 'Origem', 'Destino', 'Valor', 'Observações']],
        array_map(fn($t) => [(int) $t['id'], substr((string) $t['data'], 0, 10), (string) $t['origem_nome'], (string) $t['destino_nome'], (float) $t['valor'], (string) $t['observacoes']], $trs)));
    download($x->output(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', "DRE_$y.xlsx");
});

Router::get('export/csv', function () {
    permitir_export();
    $y = ano_param();
    $f = fopen('php://temp', 'w+');
    fputcsv($f, CAB_LANC, ';');
    foreach (q('SELECT * FROM transactions WHERE YEAR(data)=? ORDER BY data, id', [$y]) as $t) {
        $l = linha_lanc($t);
        foreach ([11, 12, 13] as $i) $l[$i] = number_format($l[$i], 2, ',', ''); // Excel BR abre certo
        fputcsv($f, $l, ';');
    }
    rewind($f);
    download("\xEF\xBB\xBF" . stream_get_contents($f), 'text/csv; charset=utf-8', "DRE_$y.csv");
});

Router::get('export/xml', function () {
    permitir_export();
    $y = ano_param();
    $e = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<DRE ano=\"$y\">\n";
    foreach (q('SELECT * FROM transactions WHERE YEAR(data)=? ORDER BY data, id', [$y]) as $t) {
        $xml .= sprintf("  <Lancamento><ID>%d</ID><Data>%s</Data><Tipo>%s</Tipo><Categoria>%s</Categoria><CentroCusto>%s</CentroCusto><Valor>%.2F</Valor><TaxaMDR>%.2F</TaxaMDR><ValorLiquido>%.2F</ValorLiquido><Status>%s</Status></Lancamento>\n",
            $t['id'], substr((string) $t['data'], 0, 10), $t['tipo'] === 'income' ? 'Receita' : 'Despesa', $e($t['categoria_nome']), $e($t['centro_custo_nome']),
            $t['valor'], $t['taxa_mdr'], $t['valor_liquido'] ?? $t['valor'], $e($t['status']));
    }
    download($xml . '</DRE>', 'application/xml; charset=utf-8', "DRE_$y.xml");
});

Router::get('export/pdf', function () {
    permitir_export();
    $y = ano_param();
    $txs = q('SELECT tipo, data, valor, taxa_mdr, centro_custo_nome FROM transactions WHERE YEAR(data)=?', [$y]);
    $sum = function (callable $f) use ($txs) { $s = 0.0; foreach ($txs as $t) if ($f($t)) $s += (float) $t['valor']; return $s; };
    $totalRec = $sum(fn($t) => $t['tipo'] === 'income');
    $totalDesp = $sum(fn($t) => $t['tipo'] === 'expense');
    $taxa = array_sum(array_map(fn($t) => $t['tipo'] === 'income' ? (float) $t['taxa_mdr'] : 0, $txs));
    $impostos = $sum(fn($t) => $t['tipo'] === 'expense' && $t['centro_custo_nome'] === 'DESPESAS DE IMPOSTOS');
    $despOp = $sum(fn($t) => $t['tipo'] === 'expense' && $t['centro_custo_nome'] === 'DESPESAS OPERACIONAIS');
    $despAdm = $totalDesp - $impostos - $despOp;
    $recLiq = $totalRec - $taxa;
    $lucro = $recLiq - $totalDesp;

    $p = new PdfWriter(); $p->addPage();
    $p->font(true, 18); $p->textCenter(50, 'DRE — Demonstrativo do Resultado do Exercício');
    $p->font(false, 11); $p->textCenter(76, "OAF - Odirlei Amaro Ferreira  |  Ano: $y", '#666666');
    $p->line(50, 98, 545, 98);
    $yy = 115;
    $linhas = [
        ['RECEITA BRUTA', $totalRec, true, '#1a1a1a'],
        ['(-) Taxa MDR / Intermediação', -$taxa, false, '#dc2626'],
        ['RECEITA LÍQUIDA', $recLiq, true, '#1a1a1a'],
        ['(-) Despesas com Impostos', -$impostos, false, '#dc2626'],
        ['(-) Despesas Operacionais', -$despOp, false, '#dc2626'],
        ['(-) Despesas Administrativas / Outras', -$despAdm, false, '#dc2626'],
        ['LUCRO / PREJUÍZO LÍQUIDO', $lucro, true, $lucro >= 0 ? '#059669' : '#dc2626', true],
    ];
    foreach ($linhas as $l) {
        if (!empty($l[4])) $p->rect(50, $yy - 5, 495, 22, '#f0fdf4');
        $p->font($l[2], $l[2] ? 11 : 10);
        $p->text(55, $yy, $l[0], $l[3]); $p->textRight(540, $yy, brl($l[1]), $l[3]);
        $yy += 22;
    }
    $yy += 10; $p->line(50, $yy, 545, $yy); $yy += 14;
    $p->font(true, 13); $p->text(55, $yy, 'Resumo Mensal'); $yy += 26;
    $cols = [55, 195, 300, 405, 545];
    $p->font(true, 9);
    $p->text($cols[0], $yy, 'Mês', '#666666');
    foreach (['Receita Líq.', 'Despesas', 'Lucro', 'Lucrativ.'] as $i => $h) $p->textRight($cols[$i + 1] - ($i === 3 ? 5 : 15), $yy, $h, '#666666');
    $yy += 16;
    foreach (MESES_CURTOS as $i => $nome) {
        $rec = $des = 0.0;
        foreach ($txs as $t) {
            if (mes_de($t['data']) !== $i + 1) continue;
            if ($t['tipo'] === 'income') $rec += (float) $t['valor'] - (float) $t['taxa_mdr']; else $des += (float) $t['valor'];
        }
        $lc = $rec - $des; $cor = $lc >= 0 ? '#059669' : '#dc2626';
        if ($i % 2 === 0) $p->rect(50, $yy - 4, 495, 17, '#f7f8fa');
        $p->font(false, 9);
        $p->text($cols[0], $yy, $nome);
        $p->textRight($cols[1] - 15, $yy, brl($rec));
        $p->textRight($cols[2] - 15, $yy, brl($des));
        $p->textRight($cols[3] - 15, $yy, brl($lc), $cor);
        $p->textRight($cols[4] - 5, $yy, $rec > 0 ? number_format($lc / $rec * 100, 1, ',', '') . '%' : '0%', $cor);
        $yy += 17;
    }
    $p->font(false, 8); $p->textCenter(PdfWriter::H - 50, 'Gerado em ' . date('d/m/Y H:i'), '#aaaaaa');
    download($p->output(), 'application/pdf', "DRE_$y.pdf");
});
