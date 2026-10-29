<?php
namespace App\Services\Dre;

/** Strategy concreta: uma linha por grupo do plano de contas (+ conta, quando tem). */
final class AgrupamentoPorGrupoConta implements AgrupamentoDre
{
    public function chave(): string { return 'grupo'; }

    public function rotulo(): string { return 'Grupo / conta'; }

    public function item(array $t): string
    {
        return trim(($t['grupo'] ?: 'Sem grupo') . ($t['conta_nome'] ? ' — ' . $t['conta_nome'] : ''));
    }
}
