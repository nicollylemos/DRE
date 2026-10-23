<?php
namespace App\Core;

/** Leitura da requisição atual (método, caminho, corpo JSON/form, query). */
final class Request
{
    private static ?array $body = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Caminho sem a pasta base, sem barras nas pontas. Ex: "api/transactions/5". */
    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        $base = base_path();
        if ($base !== '/' && strncmp($uri, $base, strlen($base)) === 0) $uri = substr($uri, strlen($base));
        elseif ($base !== '/' && rtrim($uri, '/') === rtrim($base, '/')) $uri = '';
        $uri = preg_replace('#^index\.php/?#', '', ltrim($uri, '/'));
        return trim($uri, '/');
    }

    public static function isApi(): bool
    {
        return strncmp(self::path(), 'api/', 4) === 0 || self::path() === 'api';
    }

    /** Corpo: JSON (fetch do front) ou form-data (uploads). */
    public static function body(): array
    {
        if (self::$body !== null) return self::$body;
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $b = json_decode(file_get_contents('php://input') ?: '', true);
            self::$body = is_array($b) ? $b : [];
        } else {
            self::$body = $_POST;
        }
        return self::$body;
    }

    public static function has(string $k): bool { return array_key_exists($k, self::body()); }

    public static function input(string $k, $default = null)
    {
        $b = self::body();
        return array_key_exists($k, $b) ? $b[$k] : $default;
    }

    /** Primeiro valor não-vazio entre várias chaves (o front manda nomes antigos e novos). */
    public static function first(array $keys, $default = '')
    {
        foreach ($keys as $k) { $v = self::input($k); if ($v !== null && $v !== '') return $v; }
        return $default;
    }

    public static function query(string $k, $default = null)
    {
        return isset($_GET[$k]) && $_GET[$k] !== '' ? $_GET[$k] : $default;
    }

    public static function header(string $name): ?string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$k] ?? null;
    }
}
