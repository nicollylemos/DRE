<?php
namespace App\Services\Dre;

/** Strategy concreta: uma linha por centro de custo. */
final class AgrupamentoPorCentroCusto implements AgrupamentoDre
{
    public function chave(): string { return 'centro'; }

    public function rotulo(): string { return 'Centro de custo'; }

    public function item(array $t): string { return $t['centro_custo_nome'] ?: 'Sem centro de custo'; }
}
