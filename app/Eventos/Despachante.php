<?php
namespace App\Eventos;

/**
 * PADRÃO OBSERVER — o "sujeito": guarda quem quer ouvir cada evento e avisa todos quando ele acontece.
 * Os controllers só disparam ("lançamento apagado"); o que fazer depois fica nos Ouvintes
 * registrados em app/eventos.php. Assim, uma reação nova não exige mexer no controller.
 */
final class Despachante
{
    /** @var array<string, Ouvinte[]> */
    private static array $ouvintes = [];
    private static bool $configurado = false;

    public static function ouvir(string $evento, Ouvinte $ouvinte): void
    {
        self::$ouvintes[$evento][] = $ouvinte;
    }

    /** Avisa os ouvintes na ordem em que foram registrados. */
    public static function disparar(Evento $evento): void
    {
        self::configurar();
        foreach (self::$ouvintes[$evento->nome] ?? [] as $ouvinte) $ouvinte->ouvir($evento);
    }

    /** Carrega a lista de ouvintes (app/eventos.php) só na primeira vez. */
    private static function configurar(): void
    {
        if (self::$configurado) return;
        self::$configurado = true;
        require APP_DIR . '/eventos.php';
    }
}
