<?php
namespace App\Eventos\Ouvintes;

use App\Core\Auth;
use App\Eventos\Evento;
use App\Eventos\Ouvinte;
use App\Models\Log;
use App\Models\NotaFiscal;

/** Lançamento criado a partir de uma nota fiscal ("Gerar lançamento"): já deixa os dois vinculados. */
final class VincularNotaFiscal implements Ouvinte
{
    public function ouvir(Evento $evento): void
    {
        $nfId = (int) ($evento->dados['nota_fiscal_id'] ?? 0);
        $txId = (int) $evento->dados['id'];
        if (!$nfId || !Auth::podeEditar('notas-fiscais') || !NotaFiscal::find($nfId)) return;
        NotaFiscal::update($nfId, ['transaction_id' => $txId, 'updated_at' => date('Y-m-d H:i:s')]);
        Log::registrar('vincular', 'notas_fiscais', $nfId, "→ lançamento #$txId");
    }
}
