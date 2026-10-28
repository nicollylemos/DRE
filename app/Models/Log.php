<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Model;

/** Histórico de atividades (quem, quando, o quê, IP). */
class Log extends Model
{
    protected static string $table = 'logs';

    /** Campos que nunca vão pro snapshot (senha, tokens). */
    private const OCULTAR = ['password', 'token_hash', 'permissoes'];

    /**
     * Nunca derruba a requisição principal: se falhar, só registra no error_log.
     * $dados = cópia do registro (ex.: o que foi apagado), guardada em JSON pra consulta na tela de Logs.
     */
    public static function registrar(string $acao, string $entidade, $id = null, $detalhes = null, ?string $username = null, ?array $dados = null): void
    {
        try {
            $u = Auth::user();
            Database::run(
                'INSERT INTO logs (user_id, username, action, entity, entity_id, details, ip, dados) VALUES (?,?,?,?,?,?,?,?)',
                [$u['id'] ?? null, $username ?? ($u['username'] ?? null), $acao, $entidade,
                 $id !== null ? (int) $id : null,
                 $detalhes !== null ? mb_substr((string) $detalhes, 0, 500) : null, client_ip(),
                 $dados !== null ? self::snapshot($dados) : null]
            );
        } catch (\Throwable $e) { error_log('[LOG] falha ao registrar: ' . $e->getMessage()); }
    }

    /** JSON do registro sem campos sensíveis (aceita um registro ou uma lista deles). */
    public static function snapshot(array $dados): string
    {
        $limpa = function (array $r) { foreach (self::OCULTAR as $k) unset($r[$k]); return $r; };
        $lista = array_is_list($dados) && $dados && is_array($dados[0]) ? array_map($limpa, $dados) : $limpa($dados);
        $json = json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return mb_substr((string) $json, 0, 4000000);
    }

    /** Snapshot de um log (tela de Logs → "Ver dados apagados"). */
    public static function dados(int $id): ?array
    {
        $r = Database::one('SELECT id, action, entity, entity_id, details, username, created_at, dados FROM logs WHERE id=?', [$id]);
        if (!$r) return null;
        $r['id'] = (int) $r['id'];
        $r['dados'] = $r['dados'] !== null ? json_decode((string) $r['dados'], true) : null;
        return $r;
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
        $rows = array_map(fn($r) => ['id' => (int) $r['id'], 'entity_id' => $r['entity_id'] !== null ? (int) $r['entity_id'] : null, 'tem_dados' => (bool) $r['tem_dados']] + $r,
            Database::all("SELECT id, user_id, username, action, entity, entity_id, details, ip, created_at, (dados IS NOT NULL) AS tem_dados
                           FROM logs$where ORDER BY id DESC LIMIT $limite OFFSET " . (($pagina - 1) * $limite), $p));
        return ['rows' => $rows, 'total' => $n, 'page' => $pagina, 'limit' => $limite, 'pages' => max((int) ceil($n / $limite), 1)];
    }
}
