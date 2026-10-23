<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Arquivos anexados: contratos/aditivos de parceiro e fornecedor, notas fiscais. */
class Documento extends Model
{
    protected static string $table = 'documentos';

    public const TIPOS = ['contrato_parceiro', 'nota_fiscal', 'contrato_fornecedor'];

    public static function listar(?string $tipo, ?int $parceiroId, ?int $fornecedorId): array
    {
        $sql = 'SELECT d.*, p.nome AS parceiro_nome, f.nome AS fornecedor_nome FROM documentos d
                LEFT JOIN parceiros p ON p.id=d.parceiro_id LEFT JOIN fornecedores f ON f.id=d.fornecedor_id WHERE 1=1';
        $p = [];
        if ($tipo) { $sql .= ' AND d.tipo=?'; $p[] = $tipo; }
        if ($parceiroId) { $sql .= ' AND d.parceiro_id=?'; $p[] = $parceiroId; }
        if ($fornecedorId) { $sql .= ' AND d.fornecedor_id=?'; $p[] = $fornecedorId; }
        $sql .= ' ORDER BY d.created_at DESC, d.id DESC';
        return array_map(fn($d) => ['id' => (int) $d['id'], 'arquivo_path' => public_path($d['arquivo_path'])] + $d, Database::all($sql, $p));
    }

    public static function doParceiro(int $id): array { return Database::all('SELECT id, arquivo_path FROM documentos WHERE parceiro_id=?', [$id]); }
    public static function doFornecedor(int $id): array { return Database::all('SELECT id, arquivo_path FROM documentos WHERE fornecedor_id=?', [$id]); }
}
