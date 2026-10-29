<?php
namespace App\Services\Dre;

/**
 * PADRÃO STRATEGY — regime do DRE.
 * Cada regime sabe QUAIS lançamentos entram no ano e em QUE DATA cada um conta.
 * O DreService monta o demonstrativo do mesmo jeito para qualquer regime: trocar
 * ou criar um regime novo não mexe no cálculo.
 */
interface RegimeDre
{
    /** Identificador usado na URL/tela (?regime=...). */
    public function chave(): string;

    /** Texto mostrado no seletor da tela DRE. */
    public function rotulo(): string;

    /** Lançamentos do ano, com a coluna "data" já sendo a data que vale para o regime. */
    public function movimentos(int $ano): array;
}
