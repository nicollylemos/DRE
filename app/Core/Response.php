<?php
namespace App\Core;

/** Saídas HTTP: JSON, download, redirecionamento. Todas encerram a requisição. */
final class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }

    public static function download(string $content, string $mime, string $filename): void
    {
        header("Content-Type: $mime");
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
        exit;
    }

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        if (IS_PROD && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}
