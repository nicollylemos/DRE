<?php
namespace App\Eventos\Ouvintes;

use App\Eventos\Evento;
use App\Eventos\Ouvinte;
use App\Services\Upload;

/** Lançamento apagado: o arquivo do comprovante vai junto (a cópia dos dados fica no log). */
final class ApagarComprovante implements Ouvinte
{
    public function ouvir(Evento $evento): void
    {
        Upload::apagar($evento->dados['comprovante_path'] ?? null);
    }
}
