<?php
namespace App\Core;

use PDO;
use PDOException;

/** Conexão PDO única + atalhos de consulta usados pelos Models. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $host = env('DB_HOST', 'localhost');
        $port = (int) env('DB_PORT', 3306);
        $name = env('DB_NAME', 'dre_oaf');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
        } catch (PDOException $e) {
            // 1049 = banco não existe: tenta criar. Em hospedagem compartilhada o usuário
            // pode não ter permissão — aí o erro sobe normal (crie o banco no painel).
            if ((int) ($e->errorInfo[1] ?? 0) !== 1049) throw $e;
            $tmp = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, $opts);
            $tmp->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $opts);
        }
        $pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        return self::$pdo = $pdo;
    }

    private static function stmt(string $sql, array $params): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }

    /** Todas as linhas. */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::stmt($sql, $params);
        return $st->columnCount() ? $st->fetchAll() : [];
    }

    /** Primeira linha ou null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::stmt($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Primeira coluna da primeira linha. */
    public static function value(string $sql, array $params = [])
    {
        $v = self::stmt($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** UPDATE/DELETE: devolve quantas linhas mudaram. */
    public static function run(string $sql, array $params = []): int
    {
        return self::stmt($sql, $params)->rowCount();
    }

    /** INSERT: devolve o id criado. */
    public static function insert(string $sql, array $params = []): int
    {
        self::stmt($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** Executa várias operações como uma só (tudo ou nada). */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
