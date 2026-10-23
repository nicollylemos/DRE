<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class Parceiro extends Model
{
    protected static string $table = 'parceiros';

    public const OBRIGATORIOS = ['nome' => 'Nome', 'razao_social' => 'Razão Social', 'cnpj' => 'CNPJ', 'responsavel' => 'Responsável', 'contato' => 'Contato'];

    public static function out(array $p): array
    {
        $p['id'] = (int) $p['id'];
        $p['comissao'] = (float) $p['comissao'];
        $p['qtd_contratos'] = (int) ($p['qtd_contratos'] ?? 0);
        $p['ativo'] = (int) ($p['ativo'] ?? 1);
        foreach (['foto_path', 'contrato_path'] as $k) $p[$k] = public_path($p[$k] ?? null);
        return $p;
    }

    public static function listar(): array
    {
        return array_map([self::class, 'out'], Database::all("SELECT p.*, (SELECT COUNT(*) FROM documentos d WHERE d.parceiro_id=p.id AND d.tipo='contrato_parceiro') AS qtd_contratos
                                                               FROM parceiros p ORDER BY p.nome"));
    }

    public static function campos(array $in, ?int $id): array
    {
        foreach (self::OBRIGATORIOS as $k => $label) if (trim((string) ($in[$k] ?? '')) === '') throw new HttpException(400, "Campo obrigatório: $label");
        $cnpj = (string) $in['cnpj'];
        if (!cnpj_valido($cnpj)) throw new HttpException(400, 'CNPJ inválido — confira os números');
        $cnpj = fmt_cnpj($cnpj);
        $dup = $id ? Database::one('SELECT nome FROM parceiros WHERE cnpj=? AND id<>?', [$cnpj, $id]) : Database::one('SELECT nome FROM parceiros WHERE cnpj=?', [$cnpj]);
        if ($dup) throw new HttpException(400, "CNPJ já cadastrado para o parceiro \"{$dup['nome']}\"");
        $email = str_or_null($in['email'] ?? null);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        return [
            'nome' => mb_substr(trim((string) $in['nome']), 0, 255), 'razao_social' => mb_substr(trim((string) $in['razao_social']), 0, 255), 'cnpj' => $cnpj,
            'email' => $email, 'endereco' => cut(str_or_null($in['endereco'] ?? null), 255),
            'comissao' => max(0, round(num($in['comissao'] ?? 0), 2)), // nunca negativa
            'contato' => mb_substr(trim((string) $in['contato']), 0, 150), 'whatsapp' => cut(str_or_null($in['whatsapp'] ?? null), 30),
            'responsavel' => mb_substr(trim((string) $in['responsavel']), 0, 150), 'observacoes' => str_or_null($in['observacoes'] ?? null),
            'ativo' => (string) ($in['ativo'] ?? '1') === '0' ? 0 : 1,
        ];
    }
}
