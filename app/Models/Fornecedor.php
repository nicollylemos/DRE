<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

/** Fornecedor: mesmo modelo de Parceiro (CNPJ/CPF, contrato, situação). */
class Fornecedor extends Model
{
    protected static string $table = 'fornecedores';

    public static function out(array $f): array
    {
        $f['id'] = (int) $f['id'];
        $f['ativo'] = (int) ($f['ativo'] ?? 1);
        $f['qtd_contratos'] = (int) ($f['qtd_contratos'] ?? 0);
        $f['contrato_path'] = public_path($f['contrato_path'] ?? null);
        return $f;
    }

    public static function listar(): array
    {
        return array_map([self::class, 'out'], Database::all("SELECT f.*, (SELECT COUNT(*) FROM documentos d WHERE d.fornecedor_id=f.id AND d.tipo='contrato_fornecedor') AS qtd_contratos
                                                               FROM fornecedores f ORDER BY f.nome"));
    }

    /** $exigirDoc=false na importação (planilhas antigas às vezes não têm o CNPJ). */
    public static function campos(array $in, ?int $id, bool $exigirDoc = true): array
    {
        $nome = trim((string) ($in['nome'] ?? ''));
        if ($nome === '') throw new HttpException(400, 'Campo obrigatório: Nome');
        $doc = trim((string) ($in['cnpj_cpf'] ?? ''));
        if ($doc === '' && $exigirDoc) throw new HttpException(400, 'Campo obrigatório: CNPJ/CPF');
        if ($doc !== '') {
            if (!doc_valido($doc)) throw new HttpException(400, 'CNPJ/CPF inválido — confira os números');
            $doc = fmt_doc($doc);
            $dup = $id ? Database::one('SELECT nome FROM fornecedores WHERE cnpj_cpf=? AND id<>?', [$doc, $id]) : Database::one('SELECT nome FROM fornecedores WHERE cnpj_cpf=?', [$doc]);
            if ($dup) throw new HttpException(400, "CNPJ/CPF já cadastrado para o fornecedor \"{$dup['nome']}\"");
        }
        $email = str_or_null($in['email'] ?? null);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        return [
            'nome' => mb_substr($nome, 0, 255), 'razao_social' => cut(str_or_null($in['razao_social'] ?? null), 255), 'cnpj_cpf' => $doc ?: null,
            'email' => $email, 'endereco' => cut(str_or_null($in['endereco'] ?? null), 255),
            'cidade' => cut(str_or_null($in['cidade'] ?? null), 100), 'estado' => cut(str_or_null($in['estado'] ?? null), 50),
            'telefone' => cut(str_or_null($in['telefone'] ?? null), 30), 'whatsapp' => cut(str_or_null($in['whatsapp'] ?? null), 30),
            'responsavel' => cut(str_or_null($in['responsavel'] ?? null), 150), 'observacoes' => str_or_null($in['observacoes'] ?? null),
            'ativo' => (string) ($in['ativo'] ?? '1') === '0' ? 0 : 1,
        ];
    }

    public static function nomesAtivos(): array
    {
        return array_column(Database::all('SELECT nome FROM fornecedores WHERE ativo=1 ORDER BY nome'), 'nome');
    }
}
