<?php
namespace App\Services\NotaFiscal;

use DOMNode;

/**
 * Produto da FACTORY: um leitor por layout de nota fiscal.
 * Esta classe base traz o que todos os layouts usam (formato de saída, conversão
 * de data/valor/documento, emitente/destinatário). Cada subclasse só diz como
 * reconhecer o seu layout e onde estão os campos nele.
 */
abstract class LeitorNotaFiscal
{
    /** Esse XML é do layout que esta classe sabe ler? */
    abstract public function reconhece(DocumentoXml $doc): bool;

    /** Campos da nota, sempre no formato de base(). */
    abstract public function ler(DocumentoXml $doc): array;

    protected static function base(): array
    {
        return ['modelo' => null, 'numero' => null, 'serie' => null, 'chave' => null, 'data_emissao' => null, 'valor' => 0.0,
            'emitente_nome' => null, 'emitente_doc' => null, 'emitente_fantasia' => null,
            'destinatario_nome' => null, 'destinatario_doc' => null,
            'descricao' => null, 'observacoes' => null, 'natureza' => null, 'tp_nf' => null,
            'duplicatas' => [], 'pagamentos' => []];
    }

    protected static function data(?string $v): ?string
    {
        if (!$v) return null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) return $m[1];
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $v, $m)) return "$m[3]-$m[2]-$m[1]";
        return null;
    }

    protected static function valor(?string $v): float
    {
        if ($v === null || $v === '') return 0.0;
        $v = trim($v);
        if (strpos($v, ',') !== false) $v = str_replace(['.', ','], ['', '.'], $v); // 1.234,56
        return round((float) $v, 2);
    }

    protected static function doc(?string $v): ?string
    {
        $d = $v ? preg_replace('/\D/', '', $v) : '';
        return $d !== '' ? $d : null;
    }

    protected static function pessoa(DocumentoXml $doc, ?DOMNode $n, array $nomes = ['xNome', 'RazaoSocial', 'Nome']): array
    {
        if (!$n) return ['nome' => null, 'doc' => null, 'fantasia' => null];
        return [
            'nome' => $doc->txt($nomes, $n),
            'doc' => self::doc($doc->txt(['CNPJ', 'CPF', 'Cnpj', 'Cpf'], $n)),
            'fantasia' => $doc->txt(['xFant', 'NomeFantasia'], $n),
        ];
    }

    protected static function corta(?string $v, int $n = 200): ?string
    {
        if ($v === null) return null;
        $v = trim(preg_replace('/\s+/u', ' ', $v));
        return mb_strlen($v) > $n ? mb_substr($v, 0, $n - 1) . '…' : $v;
    }

    protected static function resumoItens(array $itens): ?string
    {
        $itens = array_values(array_filter($itens));
        if (!$itens) return null;
        $txt = implode(', ', array_slice($itens, 0, 2));
        if (count($itens) > 2) $txt .= ' e mais ' . (count($itens) - 2) . ' ite' . (count($itens) - 2 === 1 ? 'm' : 'ns');
        return self::corta($txt);
    }
}
