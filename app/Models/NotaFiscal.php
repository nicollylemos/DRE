<?php
namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

/**
 * Nota fiscal (PDF e/ou XML) com vínculo opcional a um lançamento (inclusive conta a pagar/receber em aberto).
 * tipo: entrada = compra/serviço tomado (despesa) · saida = venda/serviço prestado (receita).
 */
class NotaFiscal extends Model
{
    protected static string $table = 'notas_fiscais';

    public const TIPOS = ['entrada', 'saida'];
    public const MODELOS = ['NF-e', 'NFC-e', 'NFS-e', 'Outro'];
    public const ROTULOS = ['tipo' => 'tipo', 'modelo' => 'modelo', 'numero' => 'número', 'serie' => 'série', 'chave' => 'chave',
        'data_emissao' => 'emissão', 'valor' => 'valor', 'emitente_nome' => 'emitente', 'destinatario_nome' => 'destinatário', 'descricao' => 'descrição'];

    private const SELECT = 'SELECT n.*, t.descricao AS tx_descricao, t.valor AS tx_valor, t.tipo AS tx_tipo, t.status AS tx_status,
                                   t.data AS tx_data, t.data_vencimento AS tx_vencimento, t.cliente_fornecedor AS tx_pessoa
                            FROM notas_fiscais n LEFT JOIN transactions t ON t.id = n.transaction_id';

    public static function out(array $r): array
    {
        $r['id'] = (int) $r['id'];
        $r['valor'] = (float) $r['valor'];
        $r['transaction_id'] = $r['transaction_id'] !== null ? (int) $r['transaction_id'] : null;
        // Lançamento apagado por fora: some o vínculo na resposta.
        if ($r['transaction_id'] && ($r['tx_tipo'] ?? null) === null) $r['transaction_id'] = null;
        $r['tx_valor'] = isset($r['tx_valor']) ? (float) $r['tx_valor'] : null;
        $r['tem_pdf'] = !empty($r['pdf_path']);
        $r['tem_xml'] = !empty($r['xml_path']);
        unset($r['pdf_path'], $r['xml_path']); // caminho interno não vai pro navegador
        return $r;
    }

    public static function buscar(int $id): ?array
    {
        $r = Database::one(self::SELECT . ' WHERE n.id=?', [$id]);
        return $r ? self::out($r) : null;
    }

    public static function listar(array $f): array
    {
        $sql = self::SELECT . ' WHERE 1=1'; $p = [];
        if (!empty($f['tipo']) && in_array($f['tipo'], self::TIPOS, true)) { $sql .= ' AND n.tipo=?'; $p[] = $f['tipo']; }
        if (($f['vinculo'] ?? '') === 'com') $sql .= ' AND t.id IS NOT NULL';
        if (($f['vinculo'] ?? '') === 'sem') $sql .= ' AND t.id IS NULL';
        if (!empty($f['year'])) { $sql .= ' AND YEAR(COALESCE(n.data_emissao, n.created_at))=?'; $p[] = (int) $f['year']; }
        if (!empty($f['month'])) { $sql .= ' AND MONTH(COALESCE(n.data_emissao, n.created_at))=?'; $p[] = (int) $f['month']; }
        if (!empty($f['transaction_id'])) { $sql .= ' AND n.transaction_id=?'; $p[] = (int) $f['transaction_id']; }
        if (!empty($f['busca'])) {
            $b = '%' . $f['busca'] . '%';
            $d = only_digits((string) $f['busca']);
            $sql .= ' AND (n.numero LIKE ? OR n.emitente_nome LIKE ? OR n.destinatario_nome LIKE ? OR n.descricao LIKE ? OR n.chave LIKE ?'
                  . ($d !== '' ? ' OR n.emitente_doc LIKE ? OR n.destinatario_doc LIKE ?' : '') . ')';
            array_push($p, $b, $b, $b, $b, $b);
            if ($d !== '') array_push($p, "%$d%", "%$d%");
        }
        $sql .= ' ORDER BY COALESCE(n.data_emissao, DATE(n.created_at)) DESC, n.id DESC';
        return array_map([self::class, 'out'], Database::all($sql, $p));
    }

    /** Campos do formulário (criação/edição). */
    public static function campos(array $in, ?int $id): array
    {
        $c = fn($k, $n) => cut(str_or_null($in[$k] ?? null), $n);
        $tipo = in_array($in['tipo'] ?? '', self::TIPOS, true) ? $in['tipo'] : 'entrada';
        $modelo = in_array($in['modelo'] ?? '', self::MODELOS, true) ? $in['modelo'] : null;
        $data = (!empty($in['data_emissao']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in['data_emissao'])) ? $in['data_emissao'] : null;
        $valor = round(num(str_replace(',', '.', (string) ($in['valor'] ?? 0))), 2);
        if ($valor < 0) throw new HttpException(400, 'O valor da nota não pode ser negativo');
        $chave = $c('chave', 60);
        if ($chave && preg_match('/^\d[\d\s.\-]+$/', $chave)) $chave = only_digits($chave);
        if ($chave && strlen($chave) === 44 && ($dup = self::comChave($chave, $id))) {
            throw new HttpException(400, 'Já existe uma nota com essa chave de acesso (nº ' . ($dup['numero'] ?: $dup['id']) . ')');
        }
        $doc = fn($k) => ($d = only_digits((string) ($in[$k] ?? ''))) !== '' ? substr($d, 0, 20) : null;
        return [
            'tipo' => $tipo, 'modelo' => $modelo, 'numero' => $c('numero', 20), 'serie' => $c('serie', 10), 'chave' => $chave,
            'data_emissao' => $data, 'valor' => $valor,
            'emitente_nome' => $c('emitente_nome', 255), 'emitente_doc' => $doc('emitente_doc'),
            'destinatario_nome' => $c('destinatario_nome', 255), 'destinatario_doc' => $doc('destinatario_doc'),
            'descricao' => $c('descricao', 255), 'observacoes' => str_or_null($in['observacoes'] ?? null),
        ];
    }

    public static function comChave(string $chave, ?int $ignorar = null): ?array
    {
        return Database::one('SELECT id, numero FROM notas_fiscais WHERE chave=?' . ($ignorar ? ' AND id<>?' : ''), $ignorar ? [$chave, $ignorar] : [$chave]);
    }

    /** Lançamento apagado: a nota continua, só perde o vínculo. */
    public static function desvincularLancamento(int $txId): void
    {
        Database::run('UPDATE notas_fiscais SET transaction_id=NULL WHERE transaction_id=?', [$txId]);
    }

    // ─── Sugestões a partir do XML ──────────────────────────────────────────
    private static function sqlDoc(string $col): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE($col,'.',''),'/',''),'-',''),' ','')";
    }

    public static function fornecedorPorDoc(?string $doc): ?string
    {
        if (!$doc) return null;
        $n = Database::value('SELECT nome FROM fornecedores WHERE ' . self::sqlDoc('cnpj_cpf') . '=? ORDER BY ativo DESC LIMIT 1', [$doc]);
        return $n ? (string) $n : null;
    }

    public static function clientePorDoc(?string $doc): ?string
    {
        if (!$doc) return null;
        $n = Database::value('SELECT nome FROM clientes WHERE ' . self::sqlDoc('cnpj_cpf') . '=? LIMIT 1', [$doc]);
        return $n ? (string) $n : null;
    }

    /**
     * CNPJ da própria empresa: o do .env (EMPRESA_CNPJ) ou, sem ele, o que mais aparece como destinatário
     * das notas de entrada / emitente das notas de saída já cadastradas.
     */
    public static function cnpjEmpresa(): ?string
    {
        $env = only_digits((string) env('EMPRESA_CNPJ', ''));
        if ($env !== '') return $env;
        $doc = Database::value("SELECT doc FROM (
                SELECT destinatario_doc AS doc FROM notas_fiscais WHERE tipo='entrada' AND destinatario_doc IS NOT NULL
                UNION ALL SELECT emitente_doc FROM notas_fiscais WHERE tipo='saida' AND emitente_doc IS NOT NULL
            ) x GROUP BY doc ORDER BY COUNT(*) DESC LIMIT 1");
        return $doc ? (string) $doc : null;
    }

    /**
     * Entrada ou saída? 1) CNPJ da empresa (.env ou deduzido das notas), 2) emitente é fornecedor cadastrado → entrada,
     * 3) destinatário é cliente cadastrado → saída, 4) na dúvida, entrada (nota recebida de fornecedor é o caso mais comum).
     */
    public static function sugerirTipo(array $x): string
    {
        $empresa = self::cnpjEmpresa();
        if ($empresa) {
            if (($x['emitente_doc'] ?? null) === $empresa) return 'saida';
            if (($x['destinatario_doc'] ?? null) === $empresa) return 'entrada';
        }
        if (self::fornecedorPorDoc($x['emitente_doc'] ?? null)) return 'entrada';
        if (self::clientePorDoc($x['destinatario_doc'] ?? null)) return 'saida';
        return 'entrada';
    }

    /**
     * Dados pra abrir o formulário de lançamento já preenchido.
     * $xml = resultado do NotaFiscalXml (duplicatas e formas de pagamento), quando a nota tem XML.
     */
    public static function sugerirLancamento(array $nf, ?array $xml = null): array
    {
        $entrada = $nf['tipo'] === 'entrada';
        $pessoa = $entrada
            ? (self::fornecedorPorDoc($nf['emitente_doc']) ?? ($xml['emitente_fantasia'] ?? null) ?? $nf['emitente_nome'])
            : (self::clientePorDoc($nf['destinatario_doc']) ?? $nf['destinatario_nome']);
        $nfTxt = trim(($nf['modelo'] ?: 'NF') . ' ' . ($nf['numero'] ?: ''));
        $desc = $nfTxt . ($nf['descricao'] ? ' — ' . $nf['descricao'] : (($entrada ? $nf['emitente_nome'] : $nf['destinatario_nome']) ? ' — ' . ($entrada ? $nf['emitente_nome'] : $nf['destinatario_nome']) : ''));

        $dups = array_values(array_filter($xml['duplicatas'] ?? [], fn($d) => $d['vencimento']));
        usort($dups, fn($a, $b) => strcmp($a['vencimento'], $b['vencimento']));
        $hoje = date('Y-m-d');
        $futuro = $dups && end($dups)['vencimento'] >= $hoje;
        $obs = [];
        if (count($dups) > 1) $obs[] = 'Parcelas: ' . implode('; ', array_map(fn($d) => ($d['numero'] ?: '') . ' venc. ' . date('d/m/Y', strtotime($d['vencimento'])) . ' ' . brl($d['valor']), $dups));
        if ($nf['chave']) $obs[] = 'Chave: ' . $nf['chave'];

        $forma = null;
        foreach ($xml['pagamentos'] ?? [] as $p) if ($p['tipo'] && ($forma = \App\Models\FormaPagamento::ativaDoTipo($p['tipo']))) break;
        if (!$forma && $dups) $forma = \App\Models\FormaPagamento::ativaDoTipo('boleto');

        return [
            'tipo' => $entrada ? 'expense' : 'income',
            'data' => $nf['data_emissao'] ?: $hoje,
            'valor' => (float) $nf['valor'],
            'descricao' => mb_substr($desc, 0, 255),
            'cliente_fornecedor' => $pessoa ? mb_substr($pessoa, 0, 255) : '',
            'forma_pagamento' => $forma ?? '',
            'status' => $futuro ? 'em_aberto' : 'quitado',
            'data_vencimento' => $dups ? $dups[0]['vencimento'] : null,
            'observacoes' => implode(' · ', $obs),
            'nota_fiscal_id' => (int) $nf['id'],
            'titulo' => 'Lançamento da ' . $nfTxt,
        ];
    }

    /**
     * Lançamentos que combinam com a nota (pra vincular): mesmo tipo, valor mais próximo e data mais perto primeiro.
     * $soAberto = só contas a pagar/receber em aberto.
     */
    public static function candidatos(array $nf, string $busca = '', bool $soAberto = false, int $limite = 40): array
    {
        $tipo = $nf['tipo'] === 'entrada' ? 'expense' : 'income';
        $sql = "SELECT t.id, t.tipo, t.data, t.descricao, t.valor, t.status, t.data_vencimento, t.cliente_fornecedor, t.categoria_nome,
                       (SELECT COUNT(*) FROM notas_fiscais n WHERE n.transaction_id=t.id) AS qtd_nf
                FROM transactions t WHERE t.tipo=?";
        $p = [$tipo];
        if ($soAberto) $sql .= " AND t.status='em_aberto'";
        if ($busca !== '') {
            $sql .= ' AND (t.descricao LIKE ? OR t.cliente_fornecedor LIKE ? OR t.observacoes LIKE ?)';
            $b = "%$busca%"; array_push($p, $b, $b, $b);
        }
        $ref = $nf['data_emissao'] ?: date('Y-m-d');
        $sql .= ' ORDER BY ABS(t.valor - ?) ASC, ABS(DATEDIFF(t.data, ?)) ASC, t.id DESC LIMIT ' . (int) $limite;
        array_push($p, (float) $nf['valor'], $ref);
        return array_map(fn($r) => ['id' => (int) $r['id'], 'valor' => (float) $r['valor'], 'qtd_nf' => (int) $r['qtd_nf'],
            'mesmo_valor' => abs((float) $r['valor'] - (float) $nf['valor']) < 0.005] + $r, Database::all($sql, $p));
    }
}
