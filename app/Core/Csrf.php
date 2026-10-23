<?php
namespace App\Core;

/**
 * Token anti-CSRF por sessão. As views colocam o token numa <meta> e o front
 * manda no cabeçalho X-CSRF-Token em todo POST/PUT/PATCH/DELETE.
 * (O cookie SameSite=Strict já barra a maioria dos ataques; isso é a segunda camada.)
 */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    public static function renovar(): string
    {
        Session::start();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    public static function verificar(): void
    {
        $enviado = (string) (Request::header('X-CSRF-Token') ?? Request::input('_csrf', ''));
        if ($enviado === '' || !hash_equals(self::token(), $enviado))
            throw new HttpException(403, 'Sessão desatualizada. Recarregue a página e tente de novo.', 'CSRF');
    }
}
