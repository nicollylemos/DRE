<?php
namespace App\Core;

/**
 * Sessão PHP com cookie httpOnly + SameSite=Strict (+ Secure em HTTPS).
 * Sem "lembrar de mim" o cookie morre ao fechar o navegador e a sessão expira
 * após IDLE segundos sem uso; com "lembrar" o cookie dura REMEMBER segundos.
 */
final class Session
{
    public const IDLE = 8 * 3600;        // 8h parado → pede login de novo
    public const REMEMBER = 30 * 86400;  // "Manter conectado" por 30 dias

    public static function https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (env('TRUST_PROXY') === '1' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    private static function cookieParams(int $lifetime): array
    {
        return ['lifetime' => $lifetime, 'path' => base_path(), 'secure' => self::https(), 'httponly' => true, 'samesite' => 'Strict'];
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $dir = STORAGE_DIR . '/sessions';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
        session_name('dre_sess');
        session_set_cookie_params(self::cookieParams(0));
        ini_set('session.gc_maxlifetime', (string) self::REMEMBER);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    /** Novo id de sessão (evita fixação) e cookie com a validade certa. */
    public static function regenerate(bool $remember): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['remember'] = $remember;
        $_SESSION['last'] = time();
        if ($remember) {
            $p = self::cookieParams(self::REMEMBER);
            setcookie(session_name(), session_id(), [
                'expires' => time() + self::REMEMBER, 'path' => $p['path'], 'secure' => $p['secure'],
                'httponly' => true, 'samesite' => 'Strict',
            ]);
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        $p = self::cookieParams(0);
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
        session_destroy();
    }

    /** Sessão sem "lembrar" que ficou parada demais. */
    public static function expirada(): bool
    {
        $last = (int) ($_SESSION['last'] ?? 0);
        return empty($_SESSION['remember']) && $last && time() - $last > self::IDLE;
    }

    public static function touch(): void
    {
        // Grava no máximo 1x por minuto pra não reescrever o arquivo de sessão a cada requisição.
        if (time() - (int) ($_SESSION['last'] ?? 0) > 60) $_SESSION['last'] = time();
    }
}
