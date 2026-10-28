<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Histórico de importações de planilha. */
class Importacao extends Model
{
    protected static string $table = 'importacoes';

    /** Tabela onde ficam os registros criados por cada tipo de importação. */
    public const TABELA_DO_TIPO = ['lancamentos' => 'transactions', 'clientes' => 'clientes', 'fornecedores' => 'fornecedores'];

    public static function listar(int $limite = 50): array
    {
        $rows = Database::all("SELECT * FROM importacoes ORDER BY id DESC LIMIT $limite");
        return array_map(function ($r) {
            $tab = self::TABELA_DO_TIPO[$r['tipo']] ?? null;
            $restantes = $tab && !$r['desfeita_em'] ? (int) Database::value("SELECT COUNT(*) FROM `$tab` WHERE importacao_id=?", [$r['id']]) : 0;
            return ['id' => (int) $r['id'], 'total_linhas' => (int) $r['total_linhas'], 'importados' => (int) $r['importados'],
                    'ignorados' => (int) $r['ignorados'], 'valor_total' => (float) $r['valor_total'], 'restantes' => $restantes] + $r;
        }, $rows);
    }

    /** Registros que a importação criou (cópia guardada no log antes de desfazer). */
    public static function registros(array $imp): array
    {
        $tab = self::TABELA_DO_TIPO[$imp['tipo']] ?? null;
        return $tab ? Database::all("SELECT * FROM `$tab` WHERE importacao_id=? ORDER BY id", [$imp['id']]) : [];
    }

    /** Apaga o que essa importação criou. Devolve quantos registros saíram. */
    public static function desfazer(array $imp): int
    {
        $tab = self::TABELA_DO_TIPO[$imp['tipo']] ?? null;
        if (!$tab) return 0;
        return Database::transaction(function () use ($tab, $imp) {
            if ($tab === 'transactions') Database::run('UPDATE notas_fiscais SET transaction_id=NULL WHERE transaction_id IN (SELECT id FROM transactions WHERE importacao_id=?)', [$imp['id']]);
            $n = Database::run("DELETE FROM `$tab` WHERE importacao_id=?", [$imp['id']]);
            Database::run('UPDATE importacoes SET desfeita_em=NOW() WHERE id=?', [$imp['id']]);
            return $n;
        });
    }
}
