<?php
namespace App\Services\Dre;

/**
 * Catálogo das estratégias do DRE: escolhe a Strategy pelo que veio da tela
 * (?regime= e ?agrupar=) e cai no padrão quando o valor é desconhecido.
 * Para criar um agrupamento novo: uma classe que implementa AgrupamentoDre + uma linha aqui.
 */
final class EstrategiasDre
{
    /** @return RegimeDre[] na ordem em que aparecem na tela */
    public static function regimes(): array
    {
        return [new RegimeCompetencia(), new RegimeCaixa()];
    }

    /** @return AgrupamentoDre[] na ordem em que aparecem na tela */
    public static function agrupamentos(): array
    {
        return [new AgrupamentoPorCategoria(), new AgrupamentoPorGrupoConta(), new AgrupamentoPorCentroCusto()];
    }

    public static function regime(string $chave): RegimeDre
    {
        foreach (self::regimes() as $r) if ($r->chave() === $chave) return $r;
        return self::regimes()[0];
    }

    public static function agrupamento(string $chave): AgrupamentoDre
    {
        foreach (self::agrupamentos() as $a) if ($a->chave() === $chave) return $a;
        return self::agrupamentos()[0];
    }

    /** chave => rótulo, para os seletores da tela. */
    public static function rotulos(array $estrategias): array
    {
        $out = [];
        foreach ($estrategias as $e) $out[$e->chave()] = $e->rotulo();
        return $out;
    }
}
