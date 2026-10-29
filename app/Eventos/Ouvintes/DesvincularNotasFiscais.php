<?php
namespace App\Eventos\Ouvintes;

use App\Eventos\Evento;
use App\Eventos\Ouvinte;
use App\Models\NotaFiscal;

/** Lançamento apagado: a nota fiscal continua guardada, só perde o vínculo. */
final class DesvincularNotasFiscais implements Ouvinte
{
    public function ouvir(Evento $evento): void
    {
        NotaFiscal::desvincularLancamento((int) $evento->dados['id']);
    }
}
