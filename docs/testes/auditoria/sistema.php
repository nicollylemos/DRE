<?php
// Saída do SISTEMA (mesmas funções que as telas usam) em JSON
require dirname(__DIR__, 3) . '/app/bootstrap.php';
use App\Services\DreService; use App\Models\Caixa; use App\Models\Transacao;
$o = ['dash' => [], 'dem' => [], 'det' => [], 'res' => [], 'fluxo' => [], 'caixas' => Caixa::comSaldo(), 'aberto' => Transacao::resumoEmAberto()];
foreach ([2024, 2025, 2026] as $y) {
    $o['dash'][$y] = DreService::dashboard($y);
    $o['res'][$y] = DreService::resumo($y);
    foreach (['categoria', 'grupo'] as $a) $o['det'][$y][$a] = DreService::detalhado($y, $a === 'categoria');
    foreach (['competencia', 'caixa'] as $r) foreach (['categoria', 'grupo', 'centro'] as $a) $o['dem'][$y][$r][$a] = DreService::demonstrativo($y, $a, $r);
}
$cx = array_merge(['Todos'], Caixa::nomes());
foreach ([2025, 2026] as $y) for ($m = 1; $m <= 12; $m++) foreach ($cx as $c) $o['fluxo']["$y-$m-$c"] = DreService::fluxoCaixa($y, $m, $c);
echo json_encode($o, JSON_UNESCAPED_UNICODE);
