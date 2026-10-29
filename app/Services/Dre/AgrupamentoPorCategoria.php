<?php
namespace App\Services\Dre;

/** Strategy concreta: uma linha por categoria. */
final class AgrupamentoPorCategoria implements AgrupamentoDre
{
    public function chave(): string { return 'categoria'; }

    public function rotulo(): string { return 'Categoria'; }

    public function item(array $t): string { return $t['categoria_nome'] ?: 'Sem categoria'; }
}
