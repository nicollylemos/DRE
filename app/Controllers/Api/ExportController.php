<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Services\DreService;
use App\Services\Spreadsheet\PdfWriter;
use App\Services\Spreadsheet\XlsxWriter;

/** Exportações: Excel, CSV, XML, PDF e o demonstrativo do DRE. */
class ExportController extends Controller
{
    private const CAB_LANC = ['ID', 'Data', 'Tipo', 'Categoria', 'Centro de Custo', 'Grupo', 'Conta', 'Forma Pagamento', 'Caixa/Banco', 'Cliente/Fornecedor', 'Descrição', 'Valor Bruto', 'Taxa MDR', 'Valor Líquido', 'Status', 'Vencimento', 'Data de pagamento'];

    /** Liberado pra quem vê Importar/Exportar e pras telas com botão de download próprio. */
    private function permitir(): void { $this->autorizar('importacao', 'dashboard', 'dre'); }

    private static function linha(array $t): array
    {
        return [(int) $t['id'], substr((string) $t['data'], 0, 10), $t['tipo'] === 'income' ? 'Receita' : 'Despesa', (string) $t['categoria_nome'],
            (string) $t['centro_custo_nome'], (string) $t['grupo'], (string) $t['conta_nome'], (string) $t['forma_pagamento_nome'], (string) $t['caixa_nome'],
            (string) $t['cliente_fornecedor'], (string) $t['descricao'], (float) $t['valor'], (float) $t['taxa_mdr'],
            (float) ($t['valor_liquido'] ?? $t['valor']), $t['status'] === 'em_aberto' ? 'Em aberto' : 'Quitado',
            $t['data_vencimento'] ? substr((string) $t['data_vencimento'], 0, 10) : '', $t['data_pagamento'] ? substr((string) $t['data_pagamento'], 0, 10) : ''];
    }

    public function excel(): void
    {
        $this->permitir();
        $y = ano_param();
        $txs = Database::all('SELECT * FROM transactions WHERE YEAR(data)=? ORDER BY data, id', [$y]);
        $trs = Database::all('SELECT * FROM transfers WHERE YEAR(data)=? ORDER BY data, id', [$y]);
        $x = new XlsxWriter();
        $x->addSheet('Lançamentos', array_merge([self::CAB_LANC], array_map([self::class, 'linha'], $txs)));

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
            $agg[$k] = $agg[$k] ?? [0, 0.0]; $agg[$k][0]++; $agg[$k][1] += (float) $t['valor'];
        }
        foreach ($agg as $k => [$qtd, $tot]) { [$c, $tp] = explode('|', $k); $cats[] = [$c, $tp === 'income' ? 'Receita' : 'Despesa', $qtd, (float) $tot]; }
        $x->addSheet('Por Categoria', $cats);
        $x->addSheet('Transferências', array_merge([['ID', 'Data', 'Origem', 'Destino', 'Valor', 'Observações']],
            array_map(fn($t) => [(int) $t['id'], substr((string) $t['data'], 0, 10), (string) $t['origem_nome'], (string) $t['destino_nome'], (float) $t['valor'], (string) $t['observacoes']], $trs)));
        $this->log('export', 'export', null, "Excel $y");
        Response::download($x->output(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', "DRE_$y.xlsx");
    }

    public function csv(): void
    {
        $this->permitir();
        $y = ano_param();
        $f = fopen('php://temp', 'w+');
        fputcsv($f, self::CAB_LANC, ';', '"', '');
        foreach (Database::all('SELECT * FROM transactions WHERE YEAR(data)=? ORDER BY data, id', [$y]) as $t) {
            $l = self::linha($t);
            foreach ([11, 12, 13] as $i) $l[$i] = number_format($l[$i], 2, ',', ''); // Excel BR abre certo
            fputcsv($f, $l, ';', '"', '');
        }
        rewind($f);
        $this->log('export', 'export', null, "CSV $y");
        Response::download("\xEF\xBB\xBF" . stream_get_contents($f), 'text/csv; charset=utf-8', "DRE_$y.csv");
    }

    public function xml(): void
    {
        $this->permitir();
        $y = ano_param();
        $e = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<DRE ano=\"$y\">\n";
        foreach (Database::all('SELECT * FROM transactions WHERE YEAR(data)=? ORDER BY data, id', [$y]) as $t) {
            $xml .= sprintf("  <Lancamento><ID>%d</ID><Data>%s</Data><Tipo>%s</Tipo><Descricao>%s</Descricao><Categoria>%s</Categoria><CentroCusto>%s</CentroCusto><Valor>%.2F</Valor><TaxaMDR>%.2F</TaxaMDR><ValorLiquido>%.2F</ValorLiquido><Status>%s</Status></Lancamento>\n",
                $t['id'], substr((string) $t['data'], 0, 10), $t['tipo'] === 'income' ? 'Receita' : 'Despesa', $e($t['descricao']), $e($t['categoria_nome']), $e($t['centro_custo_nome']),
                $t['valor'], $t['taxa_mdr'], $t['valor_liquido'] ?? $t['valor'], $e($t['status']));
        }
        $this->log('export', 'export', null, "XML $y");
        Response::download($xml . '</DRE>', 'application/xml; charset=utf-8', "DRE_$y.xml");
    }

    /** PDF do DRE (resumo anual + mês a mês). */
    public function pdf(): void
    {
        $this->permitir();
        $y = ano_param();
        $dem = DreService::demonstrativo($y, (string) $this->query('agrupar', 'categoria'), (string) $this->query('regime', 'competencia'));
        $p = new PdfWriter(); $p->addPage();
        $p->font(true, 17); $p->textCenter(46, 'DRE — Demonstrativo do Resultado do Exercício');
        $p->font(false, 10);
        $p->textCenter(70, "Exercício $y  ·  Regime: " . ($dem['regime'] === 'caixa' ? 'caixa' : 'competência') . '  ·  Agrupado por: ' . mb_strtolower(DreService::AGRUPAMENTOS[$dem['agrupar']]), '#666666');
        $p->line(50, 90, 545, 90);
        $yy = 104;
        $p->font(true, 8.5);
        $p->text(55, $yy, 'Conta', '#666666');
        $p->textRight(390, $yy, "Total $y", '#666666');
        $p->textRight(440, $yy, 'AV %', '#666666');
        if ($dem['tem_anterior']) { $p->textRight(500, $yy, 'Ano anterior', '#666666'); $p->textRight(545, $yy, 'AH %', '#666666'); }
        $yy += 16;
        $pct = fn($v) => $v === null ? '—' : number_format($v * 100, 1, ',', '.') . '%';
        foreach ($dem['linhas'] as $l) {
            if ($yy > PdfWriter::H - 70) { $p->addPage(); $yy = 50; }
            $item = $l['tipo'] === 'item';
            $destaque = in_array($l['tipo'], ['subtotal', 'resultado'], true);
            if ($destaque) $p->rect(50, $yy - 4, 495, 18, $l['tipo'] === 'resultado' ? ($l['total'] < 0 ? '#fdecea' : '#eef7f1') : '#f3f4f8');
            $p->font(!$item, $item ? 9 : 9.5);
            $cor = $l['tipo'] === 'resultado' ? ($l['total'] < 0 ? '#c0392b' : '#1f8a5b') : ($item ? '#333333' : '#1a1a1a');
            $label = mb_strlen($l['label']) > 48 ? mb_substr($l['label'], 0, 46) . '…' : $l['label'];
            $p->text($item ? 68 : 55, $yy, $label, $cor);
            $p->textRight(390, $yy, brl($l['total']), $cor);
            $p->textRight(440, $yy, $pct($l['av']), '#666666');
            if ($dem['tem_anterior']) { $p->textRight(500, $yy, brl((float) $l['anterior']), '#666666'); $p->textRight(545, $yy, $pct($l['ah']), '#666666'); }
            $yy += $item ? 15 : 18;
        }
        $yy += 4;
        $p->font(true, 9.5); $p->text(55, $yy, 'Margem líquida'); $p->textRight(390, $yy, $pct($dem['margem']['total']));

        // Mês a mês (receita líquida, despesas, resultado)
        $yy += 30;
        if ($yy > PdfWriter::H - 300) { $p->addPage(); $yy = 50; }
        $p->font(true, 12); $p->text(55, $yy, 'Mês a mês'); $yy += 22;
        $liq = $desp = $res = null;
        foreach ($dem['linhas'] as $l) {
            if ($l['tipo'] === 'subtotal') $liq = $l['valores'];
            if ($l['tipo'] === 'grupo' && $l['label'] === '(−) Despesas') $desp = $l['valores'];
            if ($l['tipo'] === 'resultado') $res = $l['valores'];
        }
        $cols = [55, 230, 335, 440, 545];
        $p->font(true, 8.5);
        $p->text($cols[0], $yy, 'Mês', '#666666');
        foreach (['Receita líquida', 'Despesas', 'Resultado', 'Margem'] as $i => $h) $p->textRight($cols[$i + 1], $yy, $h, '#666666');
        $yy += 15;
        foreach (MESES_LONGOS as $i => $nome) {
            $cor = $res[$i] >= 0 ? '#1f8a5b' : '#c0392b';
            if ($i % 2 === 0) $p->rect(50, $yy - 4, 495, 16, '#f7f8fa');
            $p->font(false, 9);
            $p->text($cols[0], $yy, $nome);
            $p->textRight($cols[1], $yy, brl($liq[$i]));
            $p->textRight($cols[2], $yy, brl($desp[$i]));
            $p->textRight($cols[3], $yy, brl($res[$i]), $cor);
            $p->textRight($cols[4], $yy, $pct($dem['margem']['valores'][$i]), $cor);
            $yy += 16;
        }
        $p->font(false, 8); $p->textCenter(PdfWriter::H - 40, 'Gerado em ' . date('d/m/Y H:i'), '#aaaaaa');
        $this->log('export', 'export', null, "PDF DRE $y");
        Response::download($p->output(), 'application/pdf', "DRE_$y.pdf");
    }

    /** Excel do demonstrativo da tela DRE (mês a mês + total + AV% + ano anterior). */
    public function dreExcel(): void
    {
        $this->autorizar('dre');
        $y = ano_param();
        $dem = DreService::demonstrativo($y, (string) $this->query('agrupar', 'categoria'), (string) $this->query('regime', 'competencia'));
        $cab = array_merge(['Conta'], MESES_CURTOS, ["Total $y", 'AV %']);
        if ($dem['tem_anterior']) { $cab[] = 'Total ' . ($y - 1); $cab[] = 'AH %'; }
        $rows = [$cab];
        foreach ($dem['linhas'] as $l) {
            $r = array_merge([($l['tipo'] === 'item' ? '    ' : '') . $l['label']], array_map('floatval', $l['valores']), [(float) $l['total'], $l['av'] !== null ? round($l['av'] * 100, 2) . '%' : '']);
            if ($dem['tem_anterior']) { $r[] = (float) $l['anterior']; $r[] = $l['ah'] !== null ? round($l['ah'] * 100, 2) . '%' : ''; }
            $rows[] = $r;
        }
        $rows[] = array_merge(['Margem líquida'], array_map(fn($v) => $v === null ? '' : round($v * 100, 2) . '%', $dem['margem']['valores']),
            [$dem['margem']['total'] === null ? '' : round($dem['margem']['total'] * 100, 2) . '%']);
        $x = new XlsxWriter();
        $x->addSheet("DRE $y", $rows);
        $this->log('export', 'export', null, "Excel DRE $y");
        Response::download($x->output(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', "DRE_demonstrativo_$y.xlsx");
    }
}
