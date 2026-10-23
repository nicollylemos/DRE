<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\Permissions;

/** Nível de acesso (perfil): conjunto de telas com "ver" ou "editar". */
class Perfil extends Model
{
    protected static string $table = 'perfis';

    public static function listar(): array
    {
        $rows = Database::all('SELECT p.*, (SELECT COUNT(*) FROM users u WHERE u.perfil_id=p.id) AS qtd_usuarios
                               FROM perfis p ORDER BY p.is_admin DESC, p.sistema DESC, p.nome');
        return array_map([self::class, 'out'], $rows);
    }

    public static function out(array $p): array
    {
        $admin = (int) $p['is_admin'] === 1;
        return [
            'id' => (int) $p['id'], 'nome' => $p['nome'], 'descricao' => $p['descricao'],
            'is_admin' => $admin, 'sistema' => (int) $p['sistema'] === 1,
            'permissoes' => $admin ? Permissions::todas() : Permissions::normalizar($p['permissoes']),
            'qtd_usuarios' => (int) ($p['qtd_usuarios'] ?? 0), 'created_at' => $p['created_at'],
        ];
    }

    /** Campos validados do formulário. */
    public static function campos(?int $id): array
    {
        $nome = trim((string) \App\Core\Request::input('nome', ''));
        if ($nome === '') throw new HttpException(400, 'Dê um nome para o nível de acesso');
        $nome = mb_substr($nome, 0, 80);
        $dup = $id ? Database::one('SELECT id FROM perfis WHERE nome=? AND id<>?', [$nome, $id]) : Database::one('SELECT id FROM perfis WHERE nome=?', [$nome]);
        if ($dup) throw new HttpException(400, 'Já existe um nível com esse nome');
        $perms = Permissions::normalizar(\App\Core\Request::input('permissoes', []));
        if (!array_filter($perms)) throw new HttpException(400, 'Libere pelo menos uma tela');
        return [
            'nome' => $nome,
            'descricao' => cut(str_or_null(\App\Core\Request::input('descricao')), 255),
            'permissoes' => json_encode($perms),
        ];
    }

    /** Texto curto das diferenças entre duas matrizes, pro log. */
    public static function diffPermissoes(array $antes, array $depois): string
    {
        $rot = ['nenhum', 'ver', 'editar'];
        $m = [];
        foreach (Permissions::TELAS as $k => $t) {
            $a = (int) ($antes[$k] ?? 0); $d = (int) ($depois[$k] ?? 0);
            if ($a !== $d) $m[] = "{$t[0]}: {$rot[$a]} → {$rot[$d]}";
        }
        return implode('; ', $m);
    }
}
