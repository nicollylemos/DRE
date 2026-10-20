<?php
// ── RELATÓRIOS DRE ────────────────────────────────────────────────────────────
// Lógica portada 1:1 do server.js, incluindo as correções da auditoria-dre.md.

const MESES_LONGOS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
const MESES_CURTOS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

function ano_param(): int { $y = (int) qs('year', date('Y')); return ($y >= 1900 && $y <= 2200) ? $y : (int) date('Y'); }
function mes_de($data): int { return (int) substr((string) $data, 5, 2); }

// DRE Resumo — receita sempre LÍQUIDA (bruta - taxa_mdr), consistente com o Dashboard (item 2).
Router::get('dre/resumo', function () {
    permitir('dre-resumo');
    $y = ano_param();
    $txs = q('SELECT tipo, data, valor, taxa_mdr FROM transactions WHERE YEAR(data)=?', [$y]);
    $si = (float) q1('SELECT COALESCE(SUM(saldo_inicial),0) si FROM caixas')['si'];
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
});

// DRE Detalhado — por grupo/conta e por mês. ?agrupar=categoria agrupa por categoria.
Router::get('dre/detalhado', function () {
    permitir('dre-detalhado');
    $y = ano_param();
    $porCategoria = qs('agrupar') === 'categoria';
    $txs = q('SELECT tipo, data, valor, taxa_mdr, grupo, conta_nome, categoria_nome FROM transactions WHERE YEAR(data)=?', [$y]);
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
});

// Fluxo de Caixa — REALIZADO (quitado, data do pagamento) x PREVISTO (em aberto,
// data de vencimento). Transferências entram no saldo quando filtrado por caixa (itens 3 e 4).
Router::get('dre/fluxo-caixa', function () {
    permitir('fluxo-caixa');
    $y = ano_param();
    $m = max(1, min(12, (int) qs('month', date('n'))));
    $caixa = qs('caixa');
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
    $real = q($sqlReal, $pR); $prev = q($sqlPrev, $pP); $trs = q($sqlTr, $pT);
    $si = (float) ($filtra ? q1('SELECT COALESCE(SUM(saldo_inicial),0) si FROM caixas WHERE nome=?', [$caixa]) : q1('SELECT COALESCE(SUM(saldo_inicial),0) si FROM caixas'))['si'];

    $dia = fn($d) => (int) substr((string) $d, 8, 2);
    $n = cal_days_in_month_safe($m, $y);
    $acc = array_fill(1, $n, ['rec' => 0.0, 'desp' => 0.0, 'recP' => 0.0, 'despP' => 0.0, 'tr' => 0.0, 'trIn' => 0.0, 'trOut' => 0.0]);
    foreach ($real as $t) $acc[$dia($t['d'])][$t['tipo'] === 'income' ? 'rec' : 'desp'] += (float) $t['valor'];
    foreach ($prev as $t) $acc[$dia($t['d'])][$t['tipo'] === 'income' ? 'recP' : 'despP'] += (float) $t['valor'];
    foreach ($trs as $t) {
        $d = $dia($t['d']); $v = (float) $t['valor'];
        $acc[$d]['tr'] += $v;
        if ($filtra && $t['destino_nome'] === $caixa) $acc[$d]['trIn'] += $v;
        if ($filtra && $t['origem_nome'] === $caixa) $acc[$d]['trOut'] += $v;
    }
    // Saldo de abertura do mês = saldo inicial dos caixas + tudo que foi realizado
    // ANTES do dia 1 (assim cada mês continua de onde o anterior parou).
    $ini = sprintf('%04d-%02d-01', $y, $m);
    $sqlAnt = "SELECT COALESCE(SUM(CASE WHEN tipo='income' THEN valor ELSE -valor END),0) v FROM transactions
               WHERE status='quitado' AND COALESCE(data_pagamento, data) < ?";
    $pA = [$ini];
    if ($filtra) { $sqlAnt .= ' AND caixa_nome=?'; $pA[] = $caixa; }
    $abertura = $si + (float) q1($sqlAnt, $pA)['v'];
    if ($filtra) {
        $abertura += (float) q1('SELECT COALESCE(SUM(valor),0) v FROM transfers WHERE data < ? AND destino_nome=?', [$ini, $caixa])['v'];
        $abertura -= (float) q1('SELECT COALESCE(SUM(valor),0) v FROM transfers WHERE data < ? AND origem_nome=?', [$ini, $caixa])['v'];
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
});

function cal_days_in_month_safe(int $m, int $y): int { return (int) (new DateTime("$y-$m-01"))->format('t'); }

// Relatório por forma de pagamento
Router::get('dre/formas-pagamento', function () {
    permitir('formas-pagamento-rel');
    $y = ano_param();
    $txs = q('SELECT tipo, data, valor, forma_pagamento_nome, status FROM transactions WHERE YEAR(data)=?', [$y]);
    $out = [];
    foreach (q('SELECT nome FROM formas_pagamento ORDER BY id') as $fp) {
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
});

// Dashboard
Router::get('dre', function () {
    permitir('dashboard');
    $y = ano_param();
    $txs = q('SELECT tipo, data, valor, taxa_mdr, centro_custo_nome, grupo, conta_nome, categoria_nome FROM transactions WHERE YEAR(data)=?', [$y]);
    $mes = array_fill(1, 12, ['r' => 0.0, 'd' => 0.0, 'x' => 0.0]);
    $porCategoria = []; $porGrupo = [];
    foreach ($txs as $t) {
        $m = mes_de($t['data']); $v = (float) $t['valor'];
        if ($t['tipo'] === 'income') { $mes[$m]['r'] += $v; $mes[$m]['d'] += (float) $t['taxa_mdr']; }
        else {
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
    foreach (q('SELECT nome, cor FROM categorias') as $c) $cores[$c['nome']] = $c['cor'];
    return [
        'summary' => $summary,
        'dreLines' => [
            'Receita Bruta' => $bruta, '(-) Impostos e Deduções' => $ded, 'Receita Líquida' => $bruta - $ded,
            '(-) Despesas Operacionais' => $desp, 'Lucro / Prejuízo Líquido' => $bruta - $ded - $desp,
        ],
        'expensesByCategory' => $temCategoria ? $porCategoria : $porGrupo,
        'categoryColors' => $temCategoria ? $cores : (object) [],
        'year' => (string) $y,
        'emAberto' => em_aberto_resumo(),
        'saldoCaixas' => array_sum(array_column(caixas_com_saldo(), 'saldo_atual')),
    ];
});

/** Totais em aberto (independe do ano): a receber, a pagar e quanto disso já venceu. */
function em_aberto_resumo(): array {
    $r = q1("SELECT
        COALESCE(SUM(CASE WHEN tipo='income'  THEN valor END),0) a_receber,
        COALESCE(SUM(CASE WHEN tipo='expense' THEN valor END),0) a_pagar,
        COALESCE(SUM(CASE WHEN tipo='income'  AND COALESCE(data_vencimento,data)<CURDATE() THEN valor END),0) receber_vencido,
        COALESCE(SUM(CASE WHEN tipo='expense' AND COALESCE(data_vencimento,data)<CURDATE() THEN valor END),0) pagar_vencido,
        SUM(CASE WHEN COALESCE(data_vencimento,data)<CURDATE() THEN 1 ELSE 0 END) qtd_vencidos
      FROM transactions WHERE status='em_aberto'");
    return array_map('floatval', $r);
}

// Agenda de vencimentos (calendário do Dashboard): contas a pagar e a receber do mês.
// Entra o que tem vencimento no mês (quitado ou não) e o que está em aberto sem
// vencimento definido (aí vale a data do lançamento).
Router::get('agenda', function () {
    permitir('dashboard');
    $y = ano_param();
    $m = max(1, min(12, (int) qs('month', date('n'))));
    $rows = q("SELECT id, tipo, descricao, valor, status, cliente_fornecedor, categoria_nome, forma_pagamento_nome, caixa_nome,
                      data, data_vencimento, data_pagamento, COALESCE(data_vencimento, data) AS venc
               FROM transactions
               WHERE (data_vencimento IS NOT NULL OR status='em_aberto')
                 AND YEAR(COALESCE(data_vencimento, data))=? AND MONTH(COALESCE(data_vencimento, data))=?
               ORDER BY venc, tipo, id", [$y, $m]);
    $hoje = date('Y-m-d');
    return array_map(fn($r) => ['id' => (int) $r['id'], 'valor' => (float) $r['valor'],
        'venc' => substr((string) $r['venc'], 0, 10),
        'vencido' => $r['status'] === 'em_aberto' && substr((string) $r['venc'], 0, 10) < $hoje] + $r, $rows);
});
