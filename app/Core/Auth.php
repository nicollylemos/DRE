<?php
namespace App\Core;

use App\Models\User;

/**
 * Usuário logado + permissões. O usuário é relido do banco a cada requisição
 * (não confia só na sessão), então trocar o nível de acesso, desativar ou
 * redefinir a senha de alguém vale na hora — a sessão antiga cai sozinha.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $carregado = false;

    /** Assinatura que muda quando a senha ou a situação do usuário muda. */
    public static function assinatura(array $u): string
    {
        return substr(hash('sha256', $u['password'] . '|' . (int) ($u['ativo'] ?? 1)), 0, 24);
    }

    public static function user(): ?array
    {
        if (self::$carregado) return self::$user;
        self::$carregado = true;
        Session::start();
        $id = (int) ($_SESSION['uid'] ?? 0);
        if (!$id) return null;
        if (Session::expirada()) { Session::destroy(); return null; }
        $u = User::comPerfil($id);
        if (!$u || !(int) $u['ativo'] || ($_SESSION['sig'] ?? '') !== self::assinatura($u)) {
            Session::destroy();
            return null;
        }
        Session::touch();
        return self::$user = $u;
    }

    public static function check(): bool { return self::user() !== null; }

    /** Exige login; 401 se não tiver. */
    public static function require(): array
    {
        $u = self::user();
        if (!$u) throw new HttpException(401, 'Não autenticado');
        return $u;
    }

    public static function login(array $u, bool $remember): void
    {
        Session::regenerate($remember);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['sig'] = self::assinatura($u);
        Csrf::renovar();
        self::$user = User::comPerfil((int) $u['id']);
        self::$carregado = true;
    }

    /** Depois de trocar a própria senha a assinatura muda — atualiza pra não se deslogar. */
    public static function reassinar(): void
    {
        $u = User::find((int) ($_SESSION['uid'] ?? 0));
        if ($u) $_SESSION['sig'] = self::assinatura($u);
        self::$carregado = false;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && (int) ($u['is_admin'] ?? 0) === 1;
    }

    /** Nível do usuário logado na tela: 0 nada, 1 ver, 2 editar. */
    public static function nivel(string $tela): int
    {
        if (self::isAdmin()) return Permissions::EDITAR;
        $u = self::user();
        return $u ? (int) (self::permissoes()[$tela] ?? 0) : 0;
    }

    public static function permissoes(): array
    {
        $u = self::user();
        if (!$u) return [];
        if ((int) ($u['is_admin'] ?? 0) === 1) return Permissions::todas();
        return Permissions::normalizar($u['perfil_permissoes'] ?? '[]');
    }

    /** Pode VER pelo menos uma das telas. */
    public static function pode(string ...$telas): bool
    {
        foreach ($telas as $t) if (self::nivel($t) >= Permissions::VER) return true;
        return false;
    }

    public static function podeEditar(string $tela): bool { return self::nivel($tela) >= Permissions::EDITAR; }

    /** Primeira tela liberada — destino depois do login e da logo do menu. */
    public static function home(): string
    {
        foreach (Permissions::TELAS as $k => $t) if (self::nivel($k) >= Permissions::VER) return $t[2];
        return self::isAdmin() ? 'usuarios' : 'sem-acesso';
    }

    /** Formato enviado pro front. */
    public static function out(?array $u = null): array
    {
        $u = $u ?? self::user();
        $admin = (int) ($u['is_admin'] ?? 0) === 1;
        return [
            'id' => (int) $u['id'], 'username' => $u['username'], 'nome' => $u['nome'] ?? null,
            'email' => $u['email'] ?? null,
            'foto_path' => public_path($u['foto_path'] ?? null),
            'perfil_id' => $u['perfil_id'] !== null ? (int) $u['perfil_id'] : null,
            'perfil_nome' => $u['perfil_nome'] ?? ($admin ? 'Administrador' : 'Sem nível'),
            'role' => $admin ? 'admin' : 'user', 'is_admin' => $admin,
            'permissoes' => $admin ? Permissions::todas() : Permissions::normalizar($u['perfil_permissoes'] ?? '[]'),
            'deve_trocar_senha' => (int) ($u['deve_trocar_senha'] ?? 0) === 1,
            'deve_aceitar_termos' => Termos::pendente($u),
            'termos_aceitos_em' => $u['termos_aceitos_em'] ?? null, 'termos_versao' => $u['termos_versao'] ?? null,
            'home' => self::home(),
        ];
    }
}
