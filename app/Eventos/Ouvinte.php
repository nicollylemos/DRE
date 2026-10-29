<?php
namespace App\Eventos;

/** PADRÃO OBSERVER — o "observador": reage a um evento sem quem disparou saber que ele existe. */
interface Ouvinte
{
    public function ouvir(Evento $evento): void;
}
