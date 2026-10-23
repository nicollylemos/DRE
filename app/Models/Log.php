<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Model;

/** Histórico de atividades (quem, quando, o quê, IP). */
class Log extends Model
{
    protected static string $table = 'logs';

    /** Nunca derruba a requisição principal: se falhar, só registra no error_log. */
    public static function registrar(string $acao, string $entidade, $id = null, $detalhes = null, ?string $username = null): void
    {
        try {
            $u = Auth::user();
            Database::run(
                'INSERT INTO logs (user_id, username, action, entity, entity_id, details, ip) VALUES (?,?,?,?,?,?,?)',
                [$u['id'] ?? null, $username ?? ($u['username'] ?? null), $acao, $entidade,
                 $id !== null ? (int) $id : null,
                 $detalhes !== null ? mb_substr((string) $detalhes, 0, 500) : null, client_ip()]
            );
        } catch (\Throwable $e) { error_log('[LOG] falha ao registrar: ' . $e->getMessage()); }
    }

    public static function buscar(array $f, int $pagina, int $limite): array
    {
        $where = ' WHERE 1=1'; $p = [];
        if (!empty($f['action']))   { $where .= ' AND action=?';        $p[] = $f['action']; }
        if (!empty($f['entity']))   { $where .= ' AND entity=?';        $p[] = $f['entity']; }
        if (!empty($f['username'])) { $where .= ' AND username LIKE ?'; $p[] = '%' . $f['username'] . '%'; }
        if (!empty($f['from']))     { $where .= ' AND created_at>=?';   $p[] = $f['from']; }
        if (!empty($f['to']))       { $where .= ' AND created_at<=?';   $p[] = $f['to'] . ' 23:59:59'; }
        $n = (int) Database::value("SELECT COUNT(*) FROM logs$where", $p);
        $rows = array_map(fn($r) => ['id' => (int) $r['id'], 'entity_id' => $r['entity_id'] !== null ? (int) $r['entity_id'] : null] + $r,
            Database::all("SELECT * FROM logs$where ORDER BY id DESC LIMIT $limite OFFSET " . (($pagina - 1) * $limite), $p));
        return ['rows' => $rows, 'total' => $n, 'page' => $pagina, 'limit' => $limite, 'pages' => max((int) ceil($n / $limite), 1)];
    }
}
