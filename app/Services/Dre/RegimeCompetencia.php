<?php
namespace App\Services\Dre;

use App\Core\Database;

/** Strategy concreta: competência — tudo pela data do lançamento, pago ou não. */
final class RegimeCompetencia implements RegimeDre
{
    public function chave(): string { return 'competencia'; }

    public function rotulo(): string { return 'Competência (data do lançamento)'; }

    public function movimentos(int $ano): array
    {
        return Database::all('SELECT tipo, data, valor, taxa_mdr, grupo, conta_nome, categoria_nome, centro_custo_nome FROM transactions WHERE YEAR(data)=?', [$ano]);
    }
}
