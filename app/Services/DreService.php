<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Caixa;
use App\Models\Transacao;

/**
 * Cálculos do DRE, Dashboard e Fluxo de Caixa.
 * Regras da auditoria-dre.md: receita sempre LÍQUIDA (bruta − taxa MDR) nos totais,
 * fluxo de caixa realizado x previsto, transferências só entram filtrando por caixa.
 */
final class DreService
{
    // ─── Dashboard ──────────────────────────────────────────────────────────
    public static function dashboard(int $ano): array
    {
        $txs = Database::all('SELECT tipo, data, valor, taxa_mdr, centro_custo_nome, grupo, categoria_nome FROM transactions WHERE YEAR(data)=?', [$ano]);
        $mes = array_fill(1, 12, ['r' => 0.0, 'd' => 0.0, 'x' => 0.0]);
        $porCategoria = []; $porGrupo = []; $recCategoria = [];
        foreach ($txs as $t) {
            $m = mes_de($t['data']); $v = (float) $t['valor'];
            if ($t['tipo'] === 'income') {
                $mes[$m]['r'] += $v; $mes[$m]['d'] += (float) $t['taxa_mdr'];
                $c = $t['categoria_nome'] ?: 'Sem categoria';
                $recCategoria[$c] = ($recCategoria[$c] ?? 0) + $v;
            } else {
                $mes[$m]['x'] += $v;
                $cat = $t['categoria_nome'] ?: 'Sem categoria';
                $porCategoria[$cat] = ($porCategoria[$cat] ?? 0) + $v;
                $g = $t['grupo'] ?: ($t['centro_custo_nome'] ?: 'Outros');
                $porGrupo[$g] = ($porGrupo[$g] ?? 0) + $v;
            }
        }
        $summary = [];
        foreach (MESES_LONGOS as $i => $nome) {
            $d = $mes[$i + 1];
            $summary[] = ['month' => $nome, 'receitas' => $d['r'], 'deducoes' => $d['d'], 'despesas' => $d['x'], 'saldo' => $d['r'] - $d['d'] - $d['x']];
        }
        $bruta = array_sum(array_column($summary, 'receitas'));
        $ded = array_sum(array_column($summary, 'deducoes'));
        $desp = array_sum(array_column($summary, 'despesas'));
        // Donut usa categoria quando os lançamentos já têm categoria; senão cai pro grupo (comportamento antigo).
        $temCategoria = count(array_diff(array_keys($porCategoria), ['Sem categoria'])) > 0;
        $cores = [];
        foreach (Database::all('SELECT nome, cor FROM categorias') as $c) $cores[$c['nome']] = $c['cor'];
        $caixas = Caixa::comSaldo();

        // Mesmo período do ano anterior (até o mês atual se for o ano corrente) pra comparar.
        $ateMes = $ano === (int) date('Y') ? (int) date('n') : 12;
        $ant = Database::one("SELECT COALESCE(SUM(CASE WHEN tipo='income' THEN valor-taxa_mdr END),0) rec, COALESCE(SUM(CASE WHEN tipo='expense' THEN valor END),0) desp
                              FROM transactions WHERE YEAR(data)=? AND MONTH(data)<=?", [$ano - 1, $ateMes]);
        $atu = ['rec' => 0.0, 'desp' => 0.0];
        foreach ($summary as $i => $s) if ($i < $ateMes) { $atu['rec'] += $s['receitas'] - $s['deducoes']; $atu['desp'] += $s['despesas']; }

        return [
            'summary' => $summary,
            'dreLines' => [
                'Receita Bruta' => $bruta, '(-) Impostos e Deduções' => $ded, 'Receita Líquida' => $bruta - $ded,
                '(-) Despesas Operacionais' => $desp, 'Lucro / Prejuízo Líquido' => $bruta - $ded - $desp,
            ],
            'expensesByCategory' => $temCategoria ? $porCategoria : $porGrupo,
            'incomeByCategory' => $recCategoria,
            'categoryColors' => $cores ?: (object) [],
            'year' => (string) $ano,
            'emAberto' => Transacao::resumoEmAberto(),
            'saldoCaixas' => array_sum(array_column($caixas, 'saldo_atual')),
            'caixas' => array_map(fn($c) => ['nome' => $c['nome'], 'tipo' => $c['tipo'], 'saldo_atual' => $c['saldo_atual']], $caixas),
            'proximos' => Transacao::proximos(7, 8),
            'comparativo' => [
                'ate_mes' => $ateMes,
                'atual' => ['receita' => $atu['rec'], 'despesa' => $atu['desp'], 'resultado' => $atu['rec'] - $atu['desp']],
                'anterior' => ['receita' => (float) $ant['rec'], 'despesa' => (float) $ant['desp'], 'resultado' => (float) $ant['rec'] - (float) $ant['desp']],
            ],
        ];
    }

    // ─── DRE Resumo (mensal, com saldo acumulado) ───────────────────────────
    public static function resumo(int $ano): array
    {
        $txs = Database::all('SELECT tipo, data, valor, taxa_mdr FROM transactions WHERE YEAR(data)=?', [$ano]);
        $si = (float) Database::value('SELECT COALESCE(SUM(saldo_inicial),0) FROM caixas');
        $mes = array_fill(1, 12, ['bruta' => 0.0, 'ded' => 0.0, 'desp' => 0.0]);
        foreach ($txs as $t) {
            $m = mes_de($t['data']);
            if ($t['tipo'] === 'income') { $mes[$m]['bruta'] += (float) $t['valor']; $mes[$m]['ded'] += (float) $t['taxa_mdr']; }
            else $mes[$m]['desp'] += (float) $t['valor'];
        }
        $acum = $si; $summary = []; $totRec = 0; $totDesp = 0;
        foreach (MESES_LONGOS as $i => $nome) {
            $d = $mes[$i + 1];
            $receitas = $d['bruta'] - $d['ded'];
            $lucro = $receitas - $d['desp'];
            $saldoIni = $acum; $acum += $lucro;
            $totRec += $receitas; $totDesp += $d['desp'];
            $summary[] = [
                'mes' => $nome, 'saldo_inicial' => $saldoIni, 'receita_bruta' => $d['bruta'], 'deducoes' => $d['ded'],
                'receitas' => $receitas, 'despesas' => $d['desp'], 'total' => $saldoIni + $lucro, 'lucro' => $lucro,
                'lucratividade' => $receitas > 0 ? $lucro / $receitas : 0,
            ];
        }
        return ['summary' => $summary, 'totals' => [
            'receitas' => $totRec, 'despesas' => $totDesp, 'lucro' => $totRec - $totDesp,
            'lucratividade' => $totRec > 0 ? ($totRec - $totDesp) / $totRec : 0,
        ]];
    }

    // ─── DRE Detalhado (por grupo/conta ou categoria) ───────────────────────
    public static function detalhado(int $ano, bool $porCategoria): array
    {
        $txs = Database::all('SELECT tipo, data, valor, taxa_mdr, grupo, conta_nome, categoria_nome FROM transactions WHERE YEAR(data)=?', [$ano]);
        $grupos = [];
        foreach ($txs as $t) {
            if ($porCategoria) { $g = $t['categoria_nome'] ?: 'Sem Categoria'; $c = ''; }
            else { $g = $t['grupo'] ?: 'Sem Grupo'; $c = $t['conta_nome'] ?: 'Geral'; }
            $key = "{$t['tipo']}||$g||$c";
            if (!isset($grupos[$key])) $grupos[$key] = ['type' => $t['tipo'], 'grupo' => $g, 'conta' => $c, 'meses' => array_fill(0, 12, 0.0), 'total' => 0.0];
            $v = $t['tipo'] === 'income' ? (float) $t['valor'] - (float) $t['taxa_mdr'] : (float) $t['valor'];
            $grupos[$key]['meses'][mes_de($t['data']) - 1] += $v;
            $grupos[$key]['total'] += $v;
        }
        $sort = fn($a, $b) => $b['total'] <=> $a['total'];
        $receitas = array_values(array_filter($grupos, fn($g) => $g['type'] === 'income')); usort($receitas, $sort);
        $despesas = array_values(array_filter($grupos, fn($g) => $g['type'] === 'expense')); usort($despesas, $sort);
        $totR = array_fill(0, 12, 0.0); $totD = array_fill(0, 12, 0.0);
        foreach ($receitas as $r) foreach ($r['meses'] as $i => $v) $totR[$i] += $v;
        foreach ($despesas as $d) foreach ($d['meses'] as $i => $v) $totD[$i] += $v;
        return ['meses' => MESES_CURTOS, 'receitas' => $receitas, 'despesas' => $despesas, 'totReceita' => $totR, 'totDespesa' => $totD];
    }

    // ─── Demonstrativo completo (tela DRE) ──────────────────────────────────
    public const AGRUPAMENTOS = ['categoria' => 'Categoria', 'grupo' => 'Grupo / conta', 'centro' => 'Centro de custo'];
    public const REGIMES = ['competencia' => 'Competência (data do lançamento)', 'caixa' => 'Caixa (só o que foi pago/recebido)'];

    private static function movimentos(int $ano, string $regime): array
    {
        if ($regime === 'caixa') {
            return Database::all("SELECT tipo, COALESCE(data_pagamento, data) AS data, valor, taxa_mdr, grupo, conta_nome, categoria_nome, centro_custo_nome
                                  FROM transactions WHERE status='quitado' AND YEAR(COALESCE(data_pagamento, data))=?", [$ano]);
        }
        return Database::all('SELECT tipo, data, valor, taxa_mdr, grupo, conta_nome, categoria_nome, centro_custo_nome FROM transactions WHERE YEAR(data)=?', [$ano]);
    }

    private static function chave(array $t, string $agrupar): string
    {
        if ($agrupar === 'grupo') return trim(($t['grupo'] ?: 'Sem grupo') . ($t['conta_nome'] ? ' — ' . $t['conta_nome'] : ''));
        if ($agrupar === 'centro') return $t['centro_custo_nome'] ?: 'Sem centro de custo';
        return $t['categoria_nome'] ?: 'Sem categoria';
    }

    /** Totais anuais por item (pra comparação com o ano anterior). */
    private static function totaisAno(int $ano, string $regime, string $agrupar): array
    {
        $out = ['receita_bruta' => 0.0, 'deducoes' => 0.0, 'despesas' => 0.0, 'itens' => []];
        foreach (self::movimentos($ano, $regime) as $t) {
            $k = $t['tipo'] . '|' . self::chave($t, $agrupar);
            $out['itens'][$k] = ($out['itens'][$k] ?? 0) + (float) $t['valor'];
            if ($t['tipo'] === 'income') { $out['receita_bruta'] += (float) $t['valor']; $out['deducoes'] += (float) $t['taxa_mdr']; }
            else $out['despesas'] += (float) $t['valor'];
        }
        return $out;
    }

    public static function demonstrativo(int $ano, string $agrupar = 'categoria', string $regime = 'competencia'): array
    {
        if (!isset(self::AGRUPAMENTOS[$agrupar])) $agrupar = 'categoria';
        if (!isset(self::REGIMES[$regime])) $regime = 'competencia';
        $zero = array_fill(0, 12, 0.0);
        $rec = []; $desp = []; $bruta = $zero; $ded = $zero; $totDesp = $zero;
        foreach (self::movimentos($ano, $regime) as $t) {
            $i = mes_de($t['data']) - 1; $v = (float) $t['valor'];
            $k = self::chave($t, $agrupar);
            if ($t['tipo'] === 'income') {
                $rec[$k] = $rec[$k] ?? $zero; $rec[$k][$i] += $v;
                $bruta[$i] += $v; $ded[$i] += (float) $t['taxa_mdr'];
            } else {
                $desp[$k] = $desp[$k] ?? $zero; $desp[$k][$i] += $v;
                $totDesp[$i] += $v;
            }
        }
        $sub = fn(array $a, array $b) => array_map(fn($x, $y) => $x - $y, $a, $b);
        $liq = $sub($bruta, $ded);
        $res = $sub($liq, $totDesp);
        $liqTotal = array_sum($liq);
        $ant = self::totaisAno($ano - 1, $regime, $agrupar);
        $antLiq = $ant['receita_bruta'] - $ant['deducoes'];

        $cores = [];
        if ($agrupar === 'categoria') foreach (Database::all('SELECT nome, cor FROM categorias') as $c) $cores[$c['nome']] = $c['cor'];

        $linha = function (string $tipo, string $label, array $valores, ?float $anterior, array $extra = []) use ($liqTotal) {
            $total = array_sum($valores);
            return $extra + [
                'tipo' => $tipo, 'label' => $label, 'valores' => array_map(fn($v) => round($v, 2), $valores), 'total' => round($total, 2),
                'av' => $liqTotal != 0.0 ? $total / $liqTotal : null,
                'anterior' => $anterior !== null ? round($anterior, 2) : null,
                'ah' => $anterior !== null && abs($anterior) > 0.004 ? ($total - $anterior) / abs($anterior) : null,
            ];
        };
        $ordena = function (array $itens) { uasort($itens, fn($a, $b) => array_sum($b) <=> array_sum($a)); return $itens; };

        $linhas = [];
        $linhas[] = $linha('grupo', 'Receita bruta', $bruta, $ant['receita_bruta']);
        foreach ($ordena($rec) as $k => $vals) $linhas[] = $linha('item', $k, $vals, $ant['itens']["income|$k"] ?? 0.0, ['cor' => $cores[$k] ?? null, 'natureza' => 'income']);
        $linhas[] = $linha('deducao', '(−) Taxas e deduções (MDR)', $ded, $ant['deducoes']);
        $linhas[] = $linha('subtotal', 'Receita líquida', $liq, $antLiq);
        $linhas[] = $linha('grupo', '(−) Despesas', $totDesp, $ant['despesas']);
        foreach ($ordena($desp) as $k => $vals) $linhas[] = $linha('item', $k, $vals, $ant['itens']["expense|$k"] ?? 0.0, ['cor' => $cores[$k] ?? null, 'natureza' => 'expense']);
        $linhas[] = $linha('resultado', 'Resultado líquido (lucro / prejuízo)', $res, $antLiq - $ant['despesas']);

        $margem = array_map(fn($r, $l) => $l != 0.0 ? $r / $l : null, $res, $liq);
        return [
            'ano' => $ano, 'agrupar' => $agrupar, 'regime' => $regime,
            'agrupamentos' => self::AGRUPAMENTOS, 'regimes' => self::REGIMES,
            'meses' => MESES_CURTOS, 'linhas' => $linhas,
            'margem' => ['valores' => $margem, 'total' => $liqTotal != 0.0 ? array_sum($res) / $liqTotal : null],
            'tem_anterior' => $ant['receita_bruta'] > 0 || $ant['despesas'] > 0,
        ];
    }

    // ─── Fluxo de Caixa ─────────────────────────────────────────────────────
    public static function fluxoCaixa(int $y, int $m, ?string $caixa): array
    {
        $filtra = $caixa && $caixa !== 'Todos';
        $sqlReal = "SELECT tipo, COALESCE(data_pagamento, data) d, valor FROM transactions
                    WHERE status='quitado' AND YEAR(COALESCE(data_pagamento, data))=? AND MONTH(COALESCE(data_pagamento, data))=?";
        $sqlPrev = "SELECT tipo, COALESCE(data_vencimento, data) d, valor FROM transactions
                    WHERE status='em_aberto' AND YEAR(COALESCE(data_vencimento, data))=? AND MONTH(COALESCE(data_vencimento, data))=?";
        $sqlTr = 'SELECT data d, valor, origem_nome, destino_nome FROM transfers WHERE YEAR(data)=? AND MONTH(data)=?';
        $pR = $pP = $pT = [$y, $m];
        if ($filtra) {
            $sqlReal .= ' AND caixa_nome=?'; $pR[] = $caixa;
            $sqlPrev .= ' AND caixa_nome=?'; $pP[] = $caixa;
            $sqlTr .= ' AND (origem_nome=? OR destino_nome=?)'; $pT[] = $caixa; $pT[] = $caixa;
        }
        $real = Database::all($sqlReal, $pR); $prev = Database::all($sqlPrev, $pP); $trs = Database::all($sqlTr, $pT);
        $si = (float) ($filtra ? Database::value('SELECT COALESCE(SUM(saldo_inicial),0) FROM caixas WHERE nome=?', [$caixa]) : Database::value('SELECT COALESCE(SUM(saldo_inicial),0) FROM caixas'));

        $dia = fn($d) => (int) substr((string) $d, 8, 2);
        $n = dias_no_mes($m, $y);
        $acc = array_fill(1, $n, ['rec' => 0.0, 'desp' => 0.0, 'recP' => 0.0, 'despP' => 0.0, 'tr' => 0.0, 'trIn' => 0.0, 'trOut' => 0.0]);
        foreach ($real as $t) $acc[$dia($t['d'])][$t['tipo'] === 'income' ? 'rec' : 'desp'] += (float) $t['valor'];
        foreach ($prev as $t) $acc[$dia($t['d'])][$t['tipo'] === 'income' ? 'recP' : 'despP'] += (float) $t['valor'];
        foreach ($trs as $t) {
            $d = $dia($t['d']); $v = (float) $t['valor'];
            $acc[$d]['tr'] += $v;
            if ($filtra && $t['destino_nome'] === $caixa) $acc[$d]['trIn'] += $v;
            if ($filtra && $t['origem_nome'] === $caixa) $acc[$d]['trOut'] += $v;
        }
        // Saldo de abertura = saldo inicial + tudo realizado ANTES do dia 1 (cada mês continua do anterior).
        $ini = sprintf('%04d-%02d-01', $y, $m);
        $sqlAnt = "SELECT COALESCE(SUM(CASE WHEN tipo='income' THEN valor ELSE -valor END),0) FROM transactions
                   WHERE status='quitado' AND COALESCE(data_pagamento, data) < ?";
        $pA = [$ini];
        if ($filtra) { $sqlAnt .= ' AND caixa_nome=?'; $pA[] = $caixa; }
        $abertura = $si + (float) Database::value($sqlAnt, $pA);
        if ($filtra) {
            $abertura += (float) Database::value('SELECT COALESCE(SUM(valor),0) FROM transfers WHERE data < ? AND destino_nome=?', [$ini, $caixa]);
            $abertura -= (float) Database::value('SELECT COALESCE(SUM(valor),0) FROM transfers WHERE data < ? AND origem_nome=?', [$ini, $caixa]);
        }
        $saldo = $abertura; $saldoProj = $abertura; $out = [];
        for ($d = 1; $d <= $n; $d++) {
            $a = $acc[$d];
            $resultado = $a['rec'] - $a['desp'] + $a['trIn'] - $a['trOut'];
            $saldo += $resultado;
            $previsto = $a['recP'] - $a['despP'];
            $saldoProj += $resultado + $previsto;
            $out[] = [
                'data' => sprintf('%04d-%02d-%02d', $y, $m, $d), 'receita' => $a['rec'], 'despesa' => $a['desp'],
                'transferencia' => $a['tr'], 'resultado' => $resultado, 'saldo' => $saldo,
                'receita_prevista' => $a['recP'], 'despesa_prevista' => $a['despP'], 'previsto' => $previsto, 'saldo_projetado' => $saldoProj,
            ];
        }
        return ['saldo_abertura' => round($abertura, 2), 'dias' => $out];
    }

    // ─── Relatório por forma de pagamento ───────────────────────────────────
    public static function formasPagamento(int $y): array
    {
        $txs = Database::all('SELECT tipo, data, valor, forma_pagamento_nome, status FROM transactions WHERE YEAR(data)=?', [$y]);
        $out = [];
        foreach (Database::all('SELECT nome FROM formas_pagamento ORDER BY id') as $fp) {
            $meses = array_fill(0, 12, ['entradas' => 0.0, 'saidas' => 0.0, 'em_aberto' => 0.0]);
            foreach ($txs as $t) {
                if ($t['forma_pagamento_nome'] !== $fp['nome']) continue;
                $i = mes_de($t['data']) - 1; $v = (float) $t['valor'];
                $meses[$i][$t['tipo'] === 'income' ? 'entradas' : 'saidas'] += $v;
                if ($t['status'] === 'em_aberto') $meses[$i]['em_aberto'] += $v;
            }
            $te = array_sum(array_column($meses, 'entradas')); $ts = array_sum(array_column($meses, 'saidas'));
            if ($te > 0 || $ts > 0) $out[] = ['forma' => $fp['nome'], 'meses' => $meses, 'total_entradas' => $te, 'total_saidas' => $ts];
        }
        return ['meses' => MESES_CURTOS, 'formas' => $out];
    }
}
