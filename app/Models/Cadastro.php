<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Cadastros simples (plano de contas e centros de custo): uma tabela, poucas colunas.
 * Só entram colunas da lista — nada do JSON vira nome de coluna.
 */
final class Cadastro
{
    /** tabela => [colunas, obrigatórias] */
    public const TABELAS = [
        'contas_receita' => [['grupo', 'conta'], ['grupo']],
        'contas_despesa' => [['grupo', 'conta'], ['grupo']],
        'centros_custo'  => [['nome'], ['nome']],
    ];

    public static function listar(string $t): array
    {
        return array_map(fn($r) => ['id' => (int) $r['id']] + $r, Database::all("SELECT * FROM `$t` ORDER BY id"));
    }

    public static function campos(string $t, array $in, bool $parcial): array
    {
        [$cols, $obrig] = self::TABELAS[$t];
        $out = [];
        foreach ($cols as $c) {
            if (!array_key_exists($c, $in)) { if (!$parcial) $out[$c] = null; continue; }
            $out[$c] = cut(str_or_null($in[$c]), 150);
        }
        foreach ($obrig as $r) if (($parcial ? array_key_exists($r, $out) : true) && empty($out[$r])) throw new HttpException(400, "Campo obrigatório: $r");
        return $out;
    }

    public static function criar(string $t, array $d): int
    {
        $c = array_keys($d);
        return Database::insert("INSERT INTO `$t` (`" . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($d));
    }

    public static function atualizar(string $t, int $id, array $d): void
    {
        if (!$d) return;
        Database::run("UPDATE `$t` SET " . implode(',', array_map(fn($c) => "`$c`=?", array_keys($d))) . ' WHERE id=?', [...array_values($d), $id]);
    }

    public static function find(string $t, int $id): ?array { return Database::one("SELECT * FROM `$t` WHERE id=?", [$id]); }
    public static function apagar(string $t, int $id): void { Database::run("DELETE FROM `$t` WHERE id=?", [$id]); }
}
