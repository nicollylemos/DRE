<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

/** Transferência entre caixas/contas. */
class Transferencia extends Model
{
    protected static string $table = 'transfers';

    public static function listar(?int $ano, ?int $mes): array
    {
        $sql = 'SELECT * FROM transfers WHERE 1=1'; $p = [];
        if ($ano) { $sql .= ' AND YEAR(data)=?'; $p[] = $ano; }
        if ($mes) { $sql .= ' AND MONTH(data)=?'; $p[] = $mes; }
        $sql .= ' ORDER BY data DESC, id DESC';
        return array_map(fn($t) => ['id' => (int) $t['id'], 'valor' => (float) $t['valor']] + $t + [
            'date' => substr((string) $t['data'], 0, 10), 'amount' => (float) $t['valor'],
            'caixa_saida' => $t['origem_nome'], 'caixa_entrada' => $t['destino_nome'],
        ], Database::all($sql, $p));
    }

    public static function campos(array $in): array
    {
        $g = function (array $keys, $def = '') use ($in) { foreach ($keys as $k) if (isset($in[$k]) && $in[$k] !== '') return $in[$k]; return $def; };
        $data = (string) $g(['date', 'data']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) throw new HttpException(400, 'Data inválida');
        $origem = (string) $g(['caixa_saida', 'origem_nome']);
        $destino = (string) $g(['caixa_entrada', 'destino_nome']);
        if ($origem === '' || $destino === '') throw new HttpException(400, 'Escolha o caixa de saída e o de entrada');
        if ($origem === $destino) throw new HttpException(400, 'Caixa de saída e entrada não podem ser iguais');
        $valor = round(num($g(['amount', 'valor'], 0)), 2);
        if ($valor <= 0) throw new HttpException(400, 'O valor precisa ser maior que zero');
        return ['data' => $data, 'descricao' => mb_substr((string) $g(['descricao', 'description']), 0, 255), 'valor' => $valor,
                'origem_nome' => $origem, 'destino_nome' => $destino, 'observacoes' => (string) ($in['observacoes'] ?? '')];
    }
}
