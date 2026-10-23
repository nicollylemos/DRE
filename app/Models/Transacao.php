<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

/** Lançamento (receita ou despesa). */
class Transacao extends Model
{
    protected static string $table = 'transactions';

    public const ROTULOS = ['tipo' => 'tipo', 'data' => 'data', 'descricao' => 'descrição', 'valor' => 'valor', 'categoria_nome' => 'categoria',
        'cliente_fornecedor' => 'cliente/fornecedor', 'forma_pagamento_nome' => 'forma', 'caixa_nome' => 'caixa', 'status' => 'situação',
        'data_vencimento' => 'vencimento', 'data_pagamento' => 'pagamento'];

    /** Taxa MDR da forma de pagamento (só faz sentido em receita — auditoria-dre.md, item 5). */
    public static function taxaMdr(?string $formaNome, float $valor): float
    {
        if (!$formaNome) return 0.0;
        $fp = Database::one('SELECT taxa_intermediacao, tarifa_fixa FROM formas_pagamento WHERE nome=?', [$formaNome]);
        if (!$fp) return 0.0;
        return round(num($fp['taxa_intermediacao']) / 100 * $valor + num($fp['tarifa_fixa']), 2);
    }

    /** Normaliza a linha do banco pro formato do front (nomes novos + antigos). */
    public static function out(array $t): array
    {
        $t['id'] = (int) $t['id'];
        $t['valor'] = (float) $t['valor'];
        $t['taxa_mdr'] = (float) ($t['taxa_mdr'] ?? 0);
        $t['valor_liquido'] = $t['valor_liquido'] !== null ? (float) $t['valor_liquido'] : null;
        $t['categoria_id'] = $t['categoria_id'] !== null ? (int) $t['categoria_id'] : null;
        $t['importacao_id'] = isset($t['importacao_id']) && $t['importacao_id'] !== null ? (int) $t['importacao_id'] : null;
        return $t + [
            'type' => $t['tipo'], 'date' => substr((string) $t['data'], 0, 10), 'amount' => (float) $t['valor'],
            'description' => $t['descricao'], 'grupo_contas' => $t['grupo'], 'conta' => $t['conta_nome'],
            'categoria' => $t['categoria_nome'], 'forma_pagamento' => $t['forma_pagamento_nome'],
            'caixa_banco' => $t['caixa_nome'], 'centro_custo' => $t['centro_custo_nome'],
        ];
    }

    /** Categoria escolhida (id) → [id, nome], validando se serve pro tipo do lançamento. */
    public static function resolverCategoria($catId, string $tipo): array
    {
        if ($catId === null || $catId === '' || (int) $catId === 0) return [null, null];
        $c = Database::one('SELECT id, nome, tipo FROM categorias WHERE id=?', [(int) $catId]);
        if (!$c) throw new HttpException(400, 'Categoria não encontrada');
        if ($c['tipo'] !== 'ambos' && $c['tipo'] !== $tipo) throw new HttpException(400, 'Categoria "' . $c['nome'] . '" não é de ' . ($tipo === 'income' ? 'receita' : 'despesa'));
        return [(int) $c['id'], $c['nome']];
    }

    /**
     * Monta os campos do INSERT/UPDATE a partir de um array (corpo da requisição ou linha importada).
     * Aceita os nomes novos e os antigos do front.
     */
    public static function campos(array $in): array
    {
        $g = function (array $keys, $def = '') use ($in) {
            foreach ($keys as $k) if (isset($in[$k]) && $in[$k] !== '') return $in[$k];
            return $def;
        };
        $tipo = $g(['type', 'tipo']);
        $data = $g(['date', 'data']);
        if (!in_array($tipo, ['income', 'expense'], true)) throw new HttpException(400, 'Informe se é receita ou despesa');
        if (!$data || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data)) throw new HttpException(400, 'Data inválida');
        if ($g(['amount', 'valor'], null) === null) throw new HttpException(400, 'Informe o valor');
        $valor = round(num($g(['amount', 'valor'], 0)), 2);
        if ($valor <= 0) throw new HttpException(400, 'O valor precisa ser maior que zero');
        $forma = (string) $g(['forma_pagamento', 'forma_pagamento_nome']);
        $taxa = $tipo === 'income' ? self::taxaMdr($forma, $valor) : 0.0;
        [$catId, $catNome] = self::resolverCategoria($in['categoria_id'] ?? null, $tipo);
        $date = fn($k) => (!empty($in[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in[$k])) ? $in[$k] : null;
        $status = ($in['status'] ?? '') === 'em_aberto' ? 'em_aberto' : 'quitado';
        return [
            'tipo' => $tipo, 'data' => $data,
            'descricao' => mb_substr((string) $g(['description', 'descricao']), 0, 255),
            'valor' => $valor,
            'conta_nome' => mb_substr((string) $g(['conta', 'conta_nome']), 0, 150),
            'grupo' => mb_substr((string) $g(['grupo_contas', 'grupo']), 0, 150),
            'categoria_id' => $catId, 'categoria_nome' => $catNome,
            'cliente_fornecedor' => mb_substr((string) ($in['cliente_fornecedor'] ?? ''), 0, 255),
            'forma_pagamento_nome' => mb_substr($forma, 0, 100),
            'caixa_nome' => mb_substr((string) $g(['caixa_banco', 'caixa_nome']), 0, 100),
            'centro_custo_nome' => mb_substr((string) $g(['centro_custo', 'centro_custo_nome']), 0, 150),
            // status só existe como 'em_aberto' ou 'quitado' (auditoria-dre.md, item 6)
            'status' => $status,
            'data_vencimento' => $date('data_vencimento'),
            // Quitado sem data de pagamento: usa a data do lançamento (mesmo critério do fluxo de caixa).
            'data_pagamento' => $date('data_pagamento') ?? null,
            'taxa_mdr' => $taxa, 'valor_liquido' => round($valor - $taxa, 2),
            'observacoes' => (string) ($in['observacoes'] ?? ''),
        ];
    }

    public static function listar(array $f): array
    {
        $sql = 'SELECT * FROM transactions WHERE 1=1'; $p = [];
        $map = ['year' => 'YEAR(data)=?', 'month' => 'MONTH(data)=?', 'type' => 'tipo=?', 'status' => 'status=?',
                'categoria_id' => 'categoria_id=?', 'centro_custo' => 'centro_custo_nome=?', 'forma_pagamento' => 'forma_pagamento_nome=?',
                'caixa_banco' => 'caixa_nome=?', 'importacao_id' => 'importacao_id=?'];
        foreach ($map as $k => $cond) {
            if (!isset($f[$k]) || $f[$k] === '' || $f[$k] === null) continue;
            $sql .= " AND $cond";
            $p[] = in_array($k, ['year', 'month', 'categoria_id', 'importacao_id'], true) ? (int) $f[$k] : $f[$k];
        }
        $sql .= ' ORDER BY data DESC, id DESC';
        return array_map([self::class, 'out'], Database::all($sql, $p));
    }

    public static function emAberto(): array
    {
        return array_map([self::class, 'out'], Database::all("SELECT * FROM transactions WHERE status='em_aberto' AND data_vencimento IS NOT NULL ORDER BY data_vencimento"));
    }

    /** Já existe lançamento igual? (usado na importação pra não duplicar) */
    public static function existeIgual(string $tipo, string $data, float $valor, string $descricao): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM transactions WHERE tipo=? AND data=? AND valor=? AND descricao=?', [$tipo, $data, $valor, $descricao]);
    }

    /** Totais em aberto (independe do ano): a receber, a pagar e quanto disso já venceu. */
    public static function resumoEmAberto(): array
    {
        $r = Database::one("SELECT
            COALESCE(SUM(CASE WHEN tipo='income'  THEN valor END),0) a_receber,
            COALESCE(SUM(CASE WHEN tipo='expense' THEN valor END),0) a_pagar,
            COALESCE(SUM(CASE WHEN tipo='income'  AND COALESCE(data_vencimento,data)<CURDATE() THEN valor END),0) receber_vencido,
            COALESCE(SUM(CASE WHEN tipo='expense' AND COALESCE(data_vencimento,data)<CURDATE() THEN valor END),0) pagar_vencido,
            COALESCE(SUM(CASE WHEN COALESCE(data_vencimento,data)<CURDATE() THEN 1 ELSE 0 END),0) qtd_vencidos
          FROM transactions WHERE status='em_aberto'");
        return array_map('floatval', $r);
    }

    /**
     * Agenda de vencimentos do mês (calendário): o que tem vencimento no mês (quitado
     * ou não) e o que está em aberto sem vencimento (aí vale a data do lançamento).
     */
    public static function agenda(int $ano, int $mes): array
    {
        $rows = Database::all("SELECT id, tipo, descricao, valor, status, cliente_fornecedor, categoria_id, categoria_nome, forma_pagamento_nome, caixa_nome,
                                      centro_custo_nome, grupo, conta_nome, observacoes, data, data_vencimento, data_pagamento,
                                      COALESCE(data_vencimento, data) AS venc
                               FROM transactions
                               WHERE (data_vencimento IS NOT NULL OR status='em_aberto')
                                 AND YEAR(COALESCE(data_vencimento, data))=? AND MONTH(COALESCE(data_vencimento, data))=?
                               ORDER BY venc, tipo, id", [$ano, $mes]);
        $hoje = date('Y-m-d');
        return array_map(fn($r) => ['id' => (int) $r['id'], 'valor' => (float) $r['valor'],
            'categoria_id' => $r['categoria_id'] !== null ? (int) $r['categoria_id'] : null,
            'venc' => substr((string) $r['venc'], 0, 10),
            'vencido' => $r['status'] === 'em_aberto' && substr((string) $r['venc'], 0, 10) < $hoje] + $r, $rows);
    }

    /** Próximos vencimentos em aberto (inclui os vencidos), pro Dashboard. */
    public static function proximos(int $dias = 7, int $limite = 8): array
    {
        $rows = Database::all("SELECT id, tipo, descricao, valor, status, cliente_fornecedor, categoria_nome, COALESCE(data_vencimento, data) AS venc
                               FROM transactions WHERE status='em_aberto' AND COALESCE(data_vencimento, data) <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                               ORDER BY venc, id LIMIT $limite", [$dias]);
        $hoje = date('Y-m-d');
        return array_map(fn($r) => ['id' => (int) $r['id'], 'valor' => (float) $r['valor'], 'venc' => substr((string) $r['venc'], 0, 10),
            'vencido' => substr((string) $r['venc'], 0, 10) < $hoje] + $r, $rows);
    }
}
