<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

/** Caixa físico, conta bancária ou investimento. */
class Caixa extends Model
{
    protected static string $table = 'caixas';

    public const TIPOS = ['banco', 'caixa', 'investimento'];
    public const ROTULOS = ['nome' => 'nome', 'tipo' => 'tipo', 'banco' => 'banco', 'agencia' => 'agência', 'conta' => 'conta',
        'saldo_inicial' => 'saldo inicial', 'observacoes' => 'observações'];

    public static function campos(array $in, ?int $id): array
    {
        $nome = trim((string) ($in['nome'] ?? ''));
        if ($nome === '') throw new HttpException(400, 'Campo obrigatório: Nome');
        if (Database::one('SELECT id FROM caixas WHERE nome=?' . ($id ? ' AND id<>?' : ''), $id ? [$nome, $id] : [$nome])) throw new HttpException(400, 'Já existe um caixa/conta com esse nome');
        $tipo = in_array($in['tipo'] ?? '', self::TIPOS, true) ? $in['tipo'] : 'banco';
        $c = fn($k, $n) => cut(str_or_null($in[$k] ?? null), $n);
        return [
            'nome' => mb_substr($nome, 0, 100), 'tipo' => $tipo,
            'banco' => $tipo === 'caixa' ? null : $c('banco', 100),
            'agencia' => $tipo === 'caixa' ? null : agencia_valida($in['agencia'] ?? null),
            'conta' => $tipo === 'caixa' ? null : conta_valida($in['conta'] ?? null),
            'saldo_inicial' => round(num($in['saldo_inicial'] ?? 0), 2),
            'observacoes' => $c('observacoes', 255),
        ];
    }

    /**
     * Saldo de cada caixa até hoje: saldo inicial + receitas quitadas − despesas quitadas
     * (pela data do pagamento, mesmo critério do Fluxo de Caixa) ± transferências.
     * Também devolve o que está em aberto (a receber / a pagar) apontado pra ele.
     */
    public static function comSaldo(): array
    {
        $mov = [];
        foreach (Database::all("SELECT caixa_nome n,
                      SUM(CASE WHEN status='quitado' AND tipo='income'  AND COALESCE(data_pagamento,data)<=CURDATE() THEN valor ELSE 0 END) rec,
                      SUM(CASE WHEN status='quitado' AND tipo='expense' AND COALESCE(data_pagamento,data)<=CURDATE() THEN valor ELSE 0 END) desp,
                      SUM(CASE WHEN status='em_aberto' AND tipo='income'  THEN valor ELSE 0 END) a_receber,
                      SUM(CASE WHEN status='em_aberto' AND tipo='expense' THEN valor ELSE 0 END) a_pagar,
                      COUNT(*) qtd
                    FROM transactions GROUP BY caixa_nome") as $r) $mov[(string) $r['n']] = $r;
        $trIn = []; $trOut = [];
        foreach (Database::all('SELECT destino_nome n, SUM(valor) v FROM transfers WHERE data<=CURDATE() GROUP BY destino_nome') as $r) $trIn[(string) $r['n']] = (float) $r['v'];
        foreach (Database::all('SELECT origem_nome n, SUM(valor) v FROM transfers WHERE data<=CURDATE() GROUP BY origem_nome') as $r) $trOut[(string) $r['n']] = (float) $r['v'];
        return array_map(function ($c) use ($mov, $trIn, $trOut) {
            $m = $mov[$c['nome']] ?? ['rec' => 0, 'desp' => 0, 'a_receber' => 0, 'a_pagar' => 0, 'qtd' => 0];
            $si = (float) $c['saldo_inicial'];
            $entradas = (float) $m['rec'] + ($trIn[$c['nome']] ?? 0);
            $saidas = (float) $m['desp'] + ($trOut[$c['nome']] ?? 0);
            return ['id' => (int) $c['id'], 'saldo_inicial' => $si, 'entradas' => $entradas, 'saidas' => $saidas,
                'saldo_atual' => round($si + $entradas - $saidas, 2),
                'a_receber' => (float) $m['a_receber'], 'a_pagar' => (float) $m['a_pagar'], 'qtd_lanc' => (int) $m['qtd']] + $c;
        }, Database::all('SELECT * FROM caixas ORDER BY nome'));
    }

    /** Lançamentos quitados sem caixa (ou com caixa apagado): entram no fluxo "Todos", mas em nenhum saldo. */
    public static function semCaixa(): array
    {
        $saldo = (float) Database::value("SELECT COALESCE(SUM(CASE WHEN tipo='income' THEN valor ELSE -valor END),0) FROM transactions
                           WHERE status='quitado' AND COALESCE(data_pagamento,data)<=CURDATE()
                             AND (caixa_nome IS NULL OR caixa_nome='' OR caixa_nome NOT IN (SELECT nome FROM caixas))");
        $qtd = (int) Database::value("SELECT COUNT(*) FROM transactions WHERE caixa_nome IS NULL OR caixa_nome='' OR caixa_nome NOT IN (SELECT nome FROM caixas)");
        return ['saldo' => $saldo, 'qtd' => $qtd];
    }

    public static function usoDoNome(string $nome): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM transactions WHERE caixa_nome=?', [$nome])
             + (int) Database::value('SELECT COUNT(*) FROM transfers WHERE origem_nome=? OR destino_nome=?', [$nome, $nome]);
    }

    /** Lançamentos e transferências guardam o nome — renomeia junto pra não perder o saldo. */
    public static function renomearReferencias(string $de, string $para): void
    {
        Database::run('UPDATE transactions SET caixa_nome=? WHERE caixa_nome=?', [$para, $de]);
        Database::run('UPDATE transfers SET origem_nome=? WHERE origem_nome=?', [$para, $de]);
        Database::run('UPDATE transfers SET destino_nome=? WHERE destino_nome=?', [$para, $de]);
    }

    public static function nomes(): array { return array_column(Database::all('SELECT nome FROM caixas ORDER BY id'), 'nome'); }
}
