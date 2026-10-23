<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

/** Limite de tentativas por IP (guardado no banco: funciona com vários workers do PHP-FPM). */
class RateLimit extends Model
{
    protected static string $table = 'rate_limits';

    public static function verificar(string $chave, int $max, int $janelaSeg): void
    {
        $id = substr($chave . ':' . client_ip(), 0, 190);
        $agora = time();
        $row = Database::one('SELECT start_at, hits FROM rate_limits WHERE k=?', [$id]);
        if (!$row || $agora - (int) $row['start_at'] > $janelaSeg) {
            Database::run('REPLACE INTO rate_limits (k, start_at, hits) VALUES (?,?,1)', [$id, $agora]);
            if (mt_rand(1, 50) === 1) Database::run('DELETE FROM rate_limits WHERE start_at < ?', [$agora - 86400]);
            return;
        }
        $hits = (int) $row['hits'] + 1;
        Database::run('UPDATE rate_limits SET hits=? WHERE k=?', [$hits, $id]);
        if ($hits > $max) {
            $espera = max(1, (int) $row['start_at'] + $janelaSeg - $agora);
            header('Retry-After: ' . $espera);
            throw new HttpException(429, 'Muitas tentativas deste computador. Aguarde ' . self::tempo($espera) . ' e tente de novo.');
        }
    }

    /** Só confere (não conta). Usado no login: quem acerta a senha não gasta tentativa. */
    public static function checar(string $chave, int $max, int $janelaSeg): void
    {
        $row = Database::one('SELECT start_at, hits FROM rate_limits WHERE k=?', [substr($chave . ':' . client_ip(), 0, 190)]);
        if (!$row || time() - (int) $row['start_at'] > $janelaSeg || (int) $row['hits'] < $max) return;
        $espera = max(1, (int) $row['start_at'] + $janelaSeg - time());
        header('Retry-After: ' . $espera);
        throw new HttpException(429, 'Muitas tentativas erradas deste computador. Aguarde ' . self::tempo($espera) . ' e tente de novo.');
    }

    /** Conta uma tentativa errada (sem lançar erro). */
    public static function contar(string $chave, int $janelaSeg): void
    {
        $id = substr($chave . ':' . client_ip(), 0, 190);
        $agora = time();
        $row = Database::one('SELECT start_at, hits FROM rate_limits WHERE k=?', [$id]);
        if (!$row || $agora - (int) $row['start_at'] > $janelaSeg) Database::run('REPLACE INTO rate_limits (k, start_at, hits) VALUES (?,?,1)', [$id, $agora]);
        else Database::run('UPDATE rate_limits SET hits=hits+1 WHERE k=?', [$id]);
    }

    public static function limpar(string $chave): void
    {
        Database::run('DELETE FROM rate_limits WHERE k=?', [substr($chave . ':' . client_ip(), 0, 190)]);
    }

    public static function tempo(int $seg): string
    {
        $min = (int) ceil($seg / 60);
        return $min <= 1 ? '1 minuto' : "$min minutos";
    }
}
