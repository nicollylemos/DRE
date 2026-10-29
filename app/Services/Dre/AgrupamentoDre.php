<?php
namespace App\Services\Dre;

/**
 * PADRÃO STRATEGY — agrupamento das linhas do DRE.
 * Cada estratégia diz em qual linha (item) um lançamento entra.
 */
interface AgrupamentoDre
{
    public function chave(): string;

    public function rotulo(): string;

    /** Nome da linha do demonstrativo onde o lançamento é somado. */
    public function item(array $lancamento): string;
}
