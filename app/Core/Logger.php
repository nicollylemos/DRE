<?php
namespace App\Core;

/**
 * Logger — log estruturado (uma linha JSON por evento) no stderr.
 *
 * Na AWS o container manda o stderr pro CloudWatch Logs (driver awslogs do Docker),
 * e o CloudWatch consegue filtrar por campo: { $.level = "ERROR" } vira métrica/alarme.
 * No XAMPP/PHP embutido a linha cai no log de erros do Apache/terminal, igual ao error_log.
 *
 * Nunca passe senha, token, cookie ou dado sensível no $ctx — as chaves abaixo são
 * apagadas por garantia, mas o certo é nem mandar.
 */
final class Logger
{
    private const SENSIVEIS = ['password', 'senha', 'senha_atual', 'nova_senha', 'token', 'csrf', 'cookie', 'authorization', 'db_pass', 'secret'];

    public static function info(string $msg, array $ctx = []): void  { self::escrever('INFO', $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void  { self::escrever('WARN', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::escrever('ERROR', $msg, $ctx); }

    /** Monta a linha (público pra ser testado sem escrever nada). */
    public static function linha(string $nivel, string $msg, array $ctx = []): string
    {
        foreach ($ctx as $k => $_) if (in_array(strtolower((string) $k), self::SENSIVEIS, true)) $ctx[$k] = '***';
        $base = [
            'ts'      => date('c'),
            'level'   => $nivel,
            'env'     => (string) env('APP_ENV', 'production'),
            'version' => defined('APP_VERSION') ? APP_VERSION : null,
            'msg'     => $msg,
        ];
        if (PHP_SAPI !== 'cli') {
            $base['method'] = $_SERVER['REQUEST_METHOD'] ?? null;
            $base['path'] = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: null;
            $base['ip'] = function_exists('client_ip') ? client_ip() : null;
        }
        return (string) json_encode($base + $ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    private static function escrever(string $nivel, string $msg, array $ctx): void
    {
        $linha = self::linha($nivel, $msg, $ctx);
        // stderr direto quando der (container/CLI); senão o error_log padrão do PHP.
        if (defined('STDERR')) { fwrite(STDERR, $linha . PHP_EOL); return; }
        $fp = @fopen('php://stderr', 'w');
        if ($fp) { fwrite($fp, $linha . PHP_EOL); fclose($fp); return; }
        error_log($linha);
    }
}
