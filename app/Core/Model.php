<?php
namespace App\Core;

/**
 * Base dos Models: cada Model representa uma tabela e concentra o SQL dela
 * (os Controllers não escrevem SQL). Os atalhos abaixo cobrem o CRUD comum.
 */
abstract class Model
{
    /** Nome da tabela — definido em cada Model. */
    protected static string $table = '';

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM `' . static::$table . '` WHERE id=?', [$id]);
    }

    public static function findOrFail(int $id, string $msg = 'Registro não encontrado'): array
    {
        $row = static::find($id);
        if (!$row) throw new HttpException(404, $msg);
        return $row;
    }

    /** INSERT a partir de um array coluna => valor. */
    public static function create(array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . static::$table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        return Database::insert($sql, array_values($data));
    }

    /** UPDATE de um registro a partir de um array coluna => valor. */
    public static function update(int $id, array $data): int
    {
        if (!$data) return 0;
        $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
        return Database::run('UPDATE `' . static::$table . "` SET $set WHERE id=?", [...array_values($data), $id]);
    }

    public static function delete(int $id): int
    {
        return Database::run('DELETE FROM `' . static::$table . '` WHERE id=?', [$id]);
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM `' . static::$table . "` WHERE $where", $params);
    }
}
