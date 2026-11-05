<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;

/**
 * GET /health — usado pelo CloudFront/monitoramento, pelo deploy (só troca a versão se ficar UP)
 * e pelo teste de carga. Não exige login e não expõe nada sensível.
 */
final class HealthController
{
    public static function check(): void
    {
        $inicio = microtime(true);
        $db = 'UP';
        try {
            Database::one('SELECT 1 AS ok');
        } catch (\Throwable $e) {
            $db = 'DOWN';
            \App\Core\Logger::error('health: banco indisponível', ['erro' => $e->getMessage()]);
        }
        header('Cache-Control: no-store');
        Response::json([
            'status'      => $db === 'UP' ? 'UP' : 'DOWN',
            'version'     => APP_VERSION,
            'environment' => (string) env('APP_ENV', 'production'),
            'checks'      => ['database' => $db],
            'latency_ms'  => (int) round((microtime(true) - $inicio) * 1000),
        ], $db === 'UP' ? 200 : 503);
    }
}
