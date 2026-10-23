<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class Cliente extends Model
{
    protected static string $table = 'clientes';

    public const ROTULOS = ['nome' => 'nome', 'razao_social' => 'razão social', 'cnpj_cpf' => 'CPF/CNPJ', 'endereco' => 'endereço',
        'estado' => 'estado', 'cidade' => 'cidade', 'telefone' => 'telefone', 'email' => 'e-mail', 'observacoes' => 'observações'];

    /** Com quantos lançamentos e quanto já recebeu (pelo nome, que é o que o lançamento guarda). */
    public static function listar(): array
    {
        $rows = Database::all("SELECT c.*, COUNT(t.id) AS qtd_lanc, COALESCE(SUM(CASE WHEN t.tipo='income' THEN t.valor ELSE 0 END),0) AS total_receitas
                               FROM clientes c LEFT JOIN transactions t ON t.cliente_fornecedor = c.nome
                               GROUP BY c.id ORDER BY c.nome");
        return array_map(fn($r) => ['id' => (int) $r['id'], 'qtd_lanc' => (int) $r['qtd_lanc'], 'total_receitas' => (float) $r['total_receitas']] + $r, $rows);
    }

    public static function campos(array $in, ?int $id): array
    {
        $nome = trim((string) ($in['nome'] ?? ''));
        if ($nome === '') throw new HttpException(400, 'Campo obrigatório: Nome');
        $doc = trim((string) ($in['cnpj_cpf'] ?? ''));
        if ($doc !== '') {
            if (!doc_valido($doc)) throw new HttpException(400, 'CPF/CNPJ inválido — confira os números');
            $doc = fmt_doc($doc);
            $dup = Database::one('SELECT nome FROM clientes WHERE cnpj_cpf=?' . ($id ? ' AND id<>?' : ''), $id ? [$doc, $id] : [$doc]);
            if ($dup) throw new HttpException(400, "CPF/CNPJ já cadastrado para o cliente \"{$dup['nome']}\"");
        }
        $email = str_or_null($in['email'] ?? null);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException(400, 'E-mail inválido');
        $estado = str_or_null($in['estado'] ?? null);
        return [
            'nome' => mb_substr($nome, 0, 255), 'razao_social' => cut(str_or_null($in['razao_social'] ?? null), 255),
            'cnpj_cpf' => $doc ?: null, 'endereco' => cut(str_or_null($in['endereco'] ?? null), 255),
            'estado' => $estado ? mb_strtoupper(mb_substr($estado, 0, 50)) : null, 'cidade' => cut(str_or_null($in['cidade'] ?? null), 100),
            'telefone' => cut(str_or_null($in['telefone'] ?? null), 30), 'email' => $email,
            'observacoes' => str_or_null($in['observacoes'] ?? null),
        ];
    }

    public static function nomes(): array
    {
        return array_column(Database::all('SELECT nome FROM clientes ORDER BY nome'), 'nome');
    }
}
