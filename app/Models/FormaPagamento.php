<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class FormaPagamento extends Model
{
    protected static string $table = 'formas_pagamento';

    public const TIPOS = ['credito', 'debito', 'pix', 'boleto', 'transferencia', 'dinheiro'];
    public const ROTULOS = ['nome' => 'nome', 'tipo' => 'tipo', 'banco' => 'banco', 'agencia' => 'agência', 'parcelas' => 'parcelas',
        'dias_recebimento' => 'dias p/ recebimento', 'taxa_intermediacao' => 'taxa intermediação %', 'taxa_parcelamento' => 'taxa parcelamento %',
        'tarifa_fixa' => 'tarifa fixa R$'];

    public static function out(array $r): array
    {
        foreach (['taxa_intermediacao', 'taxa_parcelamento', 'tarifa_fixa'] as $k) $r[$k] = (float) $r[$k];
        foreach (['id', 'parcelas', 'dias_recebimento', 'ativo'] as $k) $r[$k] = (int) ($r[$k] ?? 1);
        if (isset($r['qtd_lanc'])) $r['qtd_lanc'] = (int) $r['qtd_lanc'];
        return $r;
    }

    public static function listar(): array
    {
        return array_map([self::class, 'out'], Database::all('SELECT f.*, (SELECT COUNT(*) FROM transactions t WHERE t.forma_pagamento_nome=f.nome) qtd_lanc FROM formas_pagamento f ORDER BY f.ativo DESC, f.nome'));
    }

    public static function campos(array $in, ?int $id): array
    {
        $nome = trim((string) ($in['nome'] ?? ''));
        if ($nome === '') throw new HttpException(400, 'Campo obrigatório: Nome');
        if (Database::one('SELECT id FROM formas_pagamento WHERE nome=?' . ($id ? ' AND id<>?' : ''), $id ? [$nome, $id] : [$nome])) throw new HttpException(400, 'Já existe uma forma de pagamento com esse nome');
        $tipo = $in['tipo'] ?? '';
        if (!in_array($tipo, self::TIPOS, true)) throw new HttpException(400, 'Escolha o tipo da forma de pagamento');
        $pct = fn($k) => min(100, max(0, round(num($in[$k] ?? 0), 4)));
        $c = fn($k, $n) => cut(str_or_null($in[$k] ?? null), $n);
        return [
            'nome' => mb_substr($nome, 0, 100), 'tipo' => $tipo,
            'banco' => $tipo === 'dinheiro' ? null : $c('banco', 100),
            'agencia' => $tipo === 'dinheiro' ? null : $c('agencia', 30),
            'parcelas' => $tipo === 'credito' ? min(48, max(1, (int) num($in['parcelas'] ?? 1))) : 1,
            'dias_recebimento' => min(365, max(0, (int) num($in['dias_recebimento'] ?? 0))),
            'taxa_intermediacao' => $pct('taxa_intermediacao'),
            'taxa_parcelamento' => $tipo === 'credito' ? $pct('taxa_parcelamento') : 0,
            'tarifa_fixa' => max(0, round(num($in['tarifa_fixa'] ?? 0), 2)),
        ];
    }

    public static function uso(string $nome): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM transactions WHERE forma_pagamento_nome=?', [$nome]);
    }

    /** Só as ativas — alimenta os selects de lançamento (a inativa continua nos lançamentos antigos). */
    public static function resumo(): array
    {
        return Database::all('SELECT nome, tipo, taxa_intermediacao, tarifa_fixa FROM formas_pagamento WHERE ativo=1 ORDER BY id');
    }

    /** Primeira forma ativa de um tipo (usada pra sugerir a forma a partir do XML da nota). */
    public static function ativaDoTipo(string $tipo): ?string
    {
        $n = Database::value('SELECT nome FROM formas_pagamento WHERE ativo=1 AND tipo=? ORDER BY id LIMIT 1', [$tipo]);
        return $n !== null && $n !== false ? (string) $n : null;
    }
}
