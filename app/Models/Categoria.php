<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class Categoria extends Model
{
    protected static string $table = 'categorias';

    /** Lista com o uso no ano (quantos lançamentos e quanto movimentou). */
    public static function listar(int $ano): array
    {
        $rows = Database::all("SELECT c.*,
                     COALESCE(SUM(CASE WHEN YEAR(t.data)=? THEN 1 ELSE 0 END),0) AS qtd_ano,
                     COALESCE(SUM(CASE WHEN YEAR(t.data)=? THEN t.valor ELSE 0 END),0) AS total_ano,
                     COUNT(t.id) AS qtd_total
                   FROM categorias c LEFT JOIN transactions t ON t.categoria_id = c.id
                   GROUP BY c.id ORDER BY c.ativo DESC, c.tipo, c.nome", [$ano, $ano]);
        return array_map(fn($r) => ['id' => (int) $r['id'], 'ativo' => (int) $r['ativo'], 'qtd_ano' => (int) $r['qtd_ano'],
            'total_ano' => (float) $r['total_ano'], 'qtd_total' => (int) $r['qtd_total']] + $r, $rows);
    }

    public static function ativas(): array
    {
        return array_map(fn($c) => ['id' => (int) $c['id']] + $c, Database::all('SELECT id, nome, tipo, cor FROM categorias WHERE ativo=1 ORDER BY nome'));
    }

    public static function campos(array $in): array
    {
        $nome = trim((string) ($in['nome'] ?? ''));
        if ($nome === '') throw new HttpException(400, 'Campo obrigatório: nome');
        $tipo = $in['tipo'] ?? 'ambos';
        if (!in_array($tipo, ['income', 'expense', 'ambos'], true)) throw new HttpException(400, 'Tipo inválido');
        $cor = (string) ($in['cor'] ?? '#1F5FBF');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) $cor = '#1F5FBF';
        return [
            'nome' => mb_substr($nome, 0, 150), 'tipo' => $tipo, 'cor' => strtoupper($cor),
            'descricao' => cut(str_or_null($in['descricao'] ?? null), 255),
            'ativo' => !empty($in['ativo']) || !array_key_exists('ativo', $in) ? 1 : 0,
        ];
    }

    public static function duplicada(string $nome, string $tipo, ?int $ignorar = null): bool
    {
        return (bool) ($ignorar
            ? Database::one('SELECT id FROM categorias WHERE nome=? AND tipo=? AND id<>?', [$nome, $tipo, $ignorar])
            : Database::one('SELECT id FROM categorias WHERE nome=? AND tipo=?', [$nome, $tipo]));
    }

    /** Acha pelo nome (sem diferenciar maiúsculas/acentos via collation) aceitando o tipo do lançamento. */
    public static function porNome(string $nome, string $tipo): ?array
    {
        return Database::one("SELECT id, nome, tipo FROM categorias WHERE nome=? AND (tipo=? OR tipo='ambos') ORDER BY tipo=? DESC LIMIT 1", [$nome, $tipo, $tipo]);
    }
}
