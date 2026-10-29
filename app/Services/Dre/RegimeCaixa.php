<?php
namespace App\Services\Dre;

use App\Core\Database;

/** Strategy concreta: caixa — só o que foi pago/recebido, pela data do pagamento. */
final class RegimeCaixa implements RegimeDre
{
    public function chave(): string { return 'caixa'; }

    public function rotulo(): string { return 'Caixa (só o que foi pago/recebido)'; }

    public function movimentos(int $ano): array
    {
        return Database::all("SELECT tipo, COALESCE(data_pagamento, data) AS data, valor, taxa_mdr, grupo, conta_nome, categoria_nome, centro_custo_nome
                              FROM transactions WHERE status='quitado' AND YEAR(COALESCE(data_pagamento, data))=?", [$ano]);
    }
}
