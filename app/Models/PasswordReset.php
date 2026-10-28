<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Links de "Esqueci minha senha". No banco fica só o SHA-256 do token (quem lê o banco não consegue usar o link).
 * Cada link vale VALIDADE_MIN minutos e uma única vez; pedir de novo invalida os anteriores.
 */
class PasswordReset extends Model
{
    protected static string $table = 'password_resets';

    public const VALIDADE_MIN = 60;

    /** Cria o link e devolve o token (que vai só no e-mail). */
    public static function criar(int $userId, string $ip): string
    {
        Database::run('UPDATE password_resets SET usado_em=NOW() WHERE user_id=? AND usado_em IS NULL', [$userId]);
        $token = bin2hex(random_bytes(32));
        Database::run('INSERT INTO password_resets (user_id, token_hash, expira_em, ip) VALUES (?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE),?)',
            [$userId, hash('sha256', $token), self::VALIDADE_MIN, $ip]);
        if (mt_rand(1, 20) === 1) Database::run('DELETE FROM password_resets WHERE expira_em < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        return $token;
    }

    /** Pediu outro link há menos de 1 minuto? (evita encher a caixa de e-mail de alguém) */
    public static function recente(int $userId): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM password_resets WHERE user_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)', [$userId]);
    }

    /** Link válido → registro + usuário; senão null. */
    public static function valido(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        return Database::one('SELECT r.id AS reset_id, u.id, u.username, u.nome, u.email, u.ativo
                              FROM password_resets r JOIN users u ON u.id=r.user_id
                              WHERE r.token_hash=? AND r.usado_em IS NULL AND r.expira_em > NOW() AND u.ativo=1', [hash('sha256', $token)]);
    }

    public static function usar(int $resetId, int $userId): void
    {
        Database::run('UPDATE password_resets SET usado_em=NOW() WHERE user_id=? AND usado_em IS NULL', [$userId]);
    }
}
