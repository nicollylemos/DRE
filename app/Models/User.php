<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class User extends Model
{
    protected static string $table = 'users';

    public const USERNAME_RE = '/^[a-zA-Z0-9_.-]{3,32}$/';
    public const MAX_TENTATIVAS = 5;     // erros seguidos de senha antes de bloquear
    public const MINUTOS_BLOQUEIO = 15;

    private const SELECT_PERFIL = 'SELECT u.*, p.nome AS perfil_nome, p.is_admin AS perfil_admin, p.permissoes AS perfil_permissoes
                                   FROM users u LEFT JOIN perfis p ON p.id = u.perfil_id';

    /** Usuário + dados do nível de acesso. is_admin vem do perfil (ou do role antigo se ficou sem perfil). */
    public static function comPerfil(int $id): ?array
    {
        $u = Database::one(self::SELECT_PERFIL . ' WHERE u.id=?', [$id]);
        return $u ? self::montar($u) : null;
    }

    public static function porUsername(string $username): ?array
    {
        $u = Database::one(self::SELECT_PERFIL . ' WHERE u.username=?', [$username]);
        return $u ? self::montar($u) : null;
    }

    private static function montar(array $u): array
    {
        $u['is_admin'] = $u['perfil_id'] !== null && $u['perfil_admin'] !== null ? (int) $u['perfil_admin'] : ($u['role'] === 'admin' ? 1 : 0);
        return $u;
    }

    /** Lista pra tela de Usuários (sem o hash da senha). */
    public static function listar(): array
    {
        $rows = Database::all('SELECT u.id, u.username, u.nome, u.email, u.role, u.perfil_id, u.ativo, u.deve_trocar_senha, u.tentativas_falhas,
                                      u.bloqueado_ate, u.ultimo_login, u.ultimo_ip, u.foto_path, u.created_at, u.cargo, u.setor,
                                      p.nome AS perfil_nome, p.is_admin AS perfil_admin
                               FROM users u LEFT JOIN perfis p ON p.id=u.perfil_id ORDER BY u.ativo DESC, u.nome, u.username');
        $agora = date('Y-m-d H:i:s');
        return array_map(fn($u) => [
            'id' => (int) $u['id'], 'perfil_id' => $u['perfil_id'] !== null ? (int) $u['perfil_id'] : null,
            'ativo' => (int) $u['ativo'], 'deve_trocar_senha' => (int) $u['deve_trocar_senha'],
            'is_admin' => (int) ($u['perfil_admin'] ?? ($u['role'] === 'admin' ? 1 : 0)),
            'bloqueado' => $u['bloqueado_ate'] && $u['bloqueado_ate'] > $agora,
            'foto_path' => public_path($u['foto_path']),
        ] + $u, $rows);
    }

    public static function validarUsername(string $username, ?int $ignorarId = null): void
    {
        if (!preg_match(self::USERNAME_RE, $username)) throw new HttpException(400, 'Usuário deve ter 3 a 32 caracteres (letras, números, _ . -)');
        $dup = $ignorarId
            ? Database::one('SELECT id FROM users WHERE username=? AND id<>?', [$username, $ignorarId])
            : Database::one('SELECT id FROM users WHERE username=?', [$username]);
        if ($dup) throw new HttpException(400, 'Esse usuário já existe');
    }

    /** Quantos administradores ATIVOS sobram tirando $exceto. */
    public static function adminsRestantes(int $exceto): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM users u JOIN perfis p ON p.id=u.perfil_id
                                       WHERE p.is_admin=1 AND u.ativo=1 AND u.id<>?', [$exceto]);
    }

    // ─── Login ──────────────────────────────────────────────────────────────
    public static function bloqueadoAte(array $u): ?int
    {
        if (!$u['bloqueado_ate']) return null;
        $t = strtotime((string) $u['bloqueado_ate']);
        return $t > time() ? $t : null;
    }

    /** Conta um erro de senha; bloqueia ao chegar no limite. Devolve tentativas restantes. */
    public static function registrarFalha(array $u): int
    {
        $n = (int) $u['tentativas_falhas'] + 1;
        if ($n >= self::MAX_TENTATIVAS) {
            Database::run('UPDATE users SET tentativas_falhas=0, bloqueado_ate=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?', [self::MINUTOS_BLOQUEIO, $u['id']]);
            return 0;
        }
        Database::run('UPDATE users SET tentativas_falhas=? WHERE id=?', [$n, $u['id']]);
        return self::MAX_TENTATIVAS - $n;
    }

    public static function registrarLogin(int $id, string $ip): void
    {
        Database::run('UPDATE users SET tentativas_falhas=0, bloqueado_ate=NULL, ultimo_login=NOW(), ultimo_ip=? WHERE id=?', [$ip, $id]);
    }

    public static function desbloquear(int $id): void
    {
        Database::run('UPDATE users SET tentativas_falhas=0, bloqueado_ate=NULL WHERE id=?', [$id]);
    }

    public static function definirSenha(int $id, string $senha, bool $trocarNoProximoLogin): void
    {
        Database::run('UPDATE users SET password=?, deve_trocar_senha=?, senha_alterada_em=NOW(), tentativas_falhas=0, bloqueado_ate=NULL WHERE id=?',
            [password_hash($senha, PASSWORD_BCRYPT), $trocarNoProximoLogin ? 1 : 0, $id]);
    }

    /** Senha provisória legível (sem 0/O/1/l pra não confundir ao ditar). */
    public static function senhaProvisoria(): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $nums = '23456789';
        $s = '';
        for ($i = 0; $i < 6; $i++) $s .= $letras[random_int(0, strlen($letras) - 1)];
        for ($i = 0; $i < 4; $i++) $s .= $nums[random_int(0, strlen($nums) - 1)];
        return str_shuffle($s);
    }
}
