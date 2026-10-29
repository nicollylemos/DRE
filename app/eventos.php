<?php
/**
 * eventos.php — quem reage a cada evento (PADRÃO OBSERVER).
 * Para uma reação nova: criar uma classe em app/Eventos/Ouvintes e registrar aqui.
 */

use App\Eventos\Despachante;
use App\Eventos\Evento;
use App\Eventos\Ouvintes\ApagarComprovante;
use App\Eventos\Ouvintes\DesvincularNotasFiscais;
use App\Eventos\Ouvintes\VincularNotaFiscal;

Despachante::ouvir(Evento::LANCAMENTO_CRIADO, new VincularNotaFiscal());
Despachante::ouvir(Evento::LANCAMENTO_APAGADO, new DesvincularNotasFiscais());
Despachante::ouvir(Evento::LANCAMENTO_APAGADO, new ApagarComprovante());
