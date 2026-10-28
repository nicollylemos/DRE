<?php
namespace App\Services;

use App\Core\HttpException;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Lê o XML de uma nota fiscal e devolve os campos que interessam ao financeiro.
 *
 * Formatos aceitos:
 *  - NF-e / NFC-e (modelo 55/65, layout da SEFAZ — arquivo "procNFe" ou só "NFe")
 *  - NFS-e no padrão ABRASF (a maioria das prefeituras) e no padrão Nacional (gov.br/nfse)
 *
 * Ignora namespaces (cada prefeitura usa um) procurando as tags pelo nome local.
 * Segurança: XML com DOCTYPE é recusado (evita XXE / "billion laughs") e nada é baixado da rede.
 */
final class NotaFiscalXml
{
    private DOMXPath $xp;

    /** Código tPag da SEFAZ → tipo de forma de pagamento do sistema. */
    public const TPAG = ['01' => 'dinheiro', '03' => 'credito', '04' => 'debito', '05' => 'credito', '15' => 'boleto',
                         '16' => 'transferencia', '17' => 'pix', '18' => 'transferencia'];

    public static function ler(string $xml): array
    {
        return (new self())->processar($xml);
    }

    private function processar(string $xml): array
    {
        $xml = ltrim($xml, "\xEF\xBB\xBF \t\r\n");
        if ($xml === '') throw new HttpException(400, 'XML vazio');
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) throw new HttpException(400, 'XML com DOCTYPE não é aceito');
        // Alguns sistemas exportam em ISO-8859-1 sem declarar — converte pra não quebrar acentos.
        if (!preg_match('//u', $xml) && !preg_match('/encoding=["\']ISO-8859-1/i', $xml)) $xml = mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1');

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) throw new HttpException(400, 'Não foi possível ler o XML (arquivo corrompido ou não é XML)');
        $this->xp = new DOMXPath($dom);

        if ($this->no('infNFe')) return $this->nfe();
        if ($this->no('infNFSe') || $this->no('infDPS')) return $this->nfseNacional();
        if ($this->no('InfNfse') || $this->no('Nfse') || $this->no('CompNfse') || $this->no('InfDeclaracaoPrestacaoServico')) return $this->nfseAbrasf();
        throw new HttpException(400, 'Esse XML não parece ser uma NF-e nem uma NFS-e');
    }

    // ─── Ajudantes de XPath (pelo nome local, sem namespace) ────────────────
    /** "a/b/c" → .//*[local-name()='a']/*[local-name()='b']/*[local-name()='c'] */
    private function caminho(string $p): string
    {
        $partes = array_map(fn($n) => "*[local-name()='$n']", explode('/', $p));
        return './/' . implode('/', $partes);
    }

    private function no(string $p, ?DOMNode $ctx = null): ?DOMNode
    {
        $r = $this->xp->query($this->caminho($p), $ctx ?? $this->xp->document);
        return $r && $r->length ? $r->item(0) : null;
    }

    private function nos(string $p, ?DOMNode $ctx = null): array
    {
        $r = $this->xp->query($this->caminho($p), $ctx ?? $this->xp->document);
        $out = [];
        if ($r) foreach ($r as $n) $out[] = $n;
        return $out;
    }

    /** Primeiro texto não vazio entre vários caminhos. */
    private function txt(array $caminhos, ?DOMNode $ctx = null): ?string
    {
        foreach ($caminhos as $c) {
            $n = $this->no($c, $ctx);
            if ($n && trim($n->textContent) !== '') return trim(preg_replace('/\s+/u', ' ', $n->textContent));
        }
        return null;
    }

    private static function data(?string $v): ?string
    {
        if (!$v) return null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) return $m[1];
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $v, $m)) return "$m[3]-$m[2]-$m[1]";
        return null;
    }

    private static function valor(?string $v): float
    {
        if ($v === null || $v === '') return 0.0;
        $v = trim($v);
        if (strpos($v, ',') !== false) $v = str_replace(['.', ','], ['', '.'], $v); // 1.234,56
        return round((float) $v, 2);
    }

    private static function doc(?string $v): ?string
    {
        $d = $v ? preg_replace('/\D/', '', $v) : '';
        return $d !== '' ? $d : null;
    }

    private function pessoa(?DOMNode $n, array $nomes = ['xNome', 'RazaoSocial', 'Nome']): array
    {
        if (!$n) return ['nome' => null, 'doc' => null, 'fantasia' => null];
        return [
            'nome' => $this->txt($nomes, $n),
            'doc' => self::doc($this->txt(['CNPJ', 'CPF', 'Cnpj', 'Cpf'], $n)),
            'fantasia' => $this->txt(['xFant', 'NomeFantasia'], $n),
        ];
    }

    private static function base(): array
    {
        return ['modelo' => null, 'numero' => null, 'serie' => null, 'chave' => null, 'data_emissao' => null, 'valor' => 0.0,
            'emitente_nome' => null, 'emitente_doc' => null, 'emitente_fantasia' => null,
            'destinatario_nome' => null, 'destinatario_doc' => null,
            'descricao' => null, 'observacoes' => null, 'natureza' => null, 'tp_nf' => null,
            'duplicatas' => [], 'pagamentos' => []];
    }

    // ─── NF-e / NFC-e ───────────────────────────────────────────────────────
    private function nfe(): array
    {
        $inf = $this->no('infNFe');
        $ide = $this->no('ide', $inf);
        $out = self::base();
        $mod = $this->txt(['mod'], $ide);
        $out['modelo'] = $mod === '65' ? 'NFC-e' : 'NF-e';
        $out['numero'] = $this->txt(['nNF'], $ide);
        $out['serie'] = $this->txt(['serie'], $ide);
        $id = $inf instanceof \DOMElement ? $inf->getAttribute('Id') : '';
        $chave = self::doc($id) ?: self::doc($this->txt(['chNFe']));
        $out['chave'] = $chave ?: null;
        $out['data_emissao'] = self::data($this->txt(['dhEmi', 'dEmi'], $ide));
        $out['natureza'] = $this->txt(['natOp'], $ide);
        $tp = $this->txt(['tpNF'], $ide);
        $out['tp_nf'] = $tp !== null ? (int) $tp : null;

        $e = $this->pessoa($this->no('emit', $inf));
        $d = $this->pessoa($this->no('dest', $inf));
        $out['emitente_nome'] = $e['nome']; $out['emitente_doc'] = $e['doc']; $out['emitente_fantasia'] = $e['fantasia'];
        $out['destinatario_nome'] = $d['nome']; $out['destinatario_doc'] = $d['doc'];
        $out['valor'] = self::valor($this->txt(['total/ICMSTot/vNF', 'vNF'], $inf));

        // Itens → descrição curta ("Produto A, Produto B e mais 3 itens")
        $itens = array_map(fn($p) => trim($p->textContent), $this->nos('det/prod/xProd', $inf));
        $out['descricao'] = self::resumoItens($itens) ?? $out['natureza'];

        foreach ($this->nos('cobr/dup', $inf) as $dup) {
            $out['duplicatas'][] = ['numero' => $this->txt(['nDup'], $dup), 'vencimento' => self::data($this->txt(['dVenc'], $dup)), 'valor' => self::valor($this->txt(['vDup'], $dup))];
        }
        foreach ($this->nos('pag/detPag', $inf) as $p) {
            $cod = str_pad((string) $this->txt(['tPag'], $p), 2, '0', STR_PAD_LEFT);
            $out['pagamentos'][] = ['codigo' => $cod, 'tipo' => self::TPAG[$cod] ?? null, 'valor' => self::valor($this->txt(['vPag'], $p))];
        }
        $out['observacoes'] = $this->txt(['infAdic/infCpl'], $inf);
        return $out;
    }

    // ─── NFS-e padrão Nacional ──────────────────────────────────────────────
    private function nfseNacional(): array
    {
        $out = self::base();
        $out['modelo'] = 'NFS-e';
        $inf = $this->no('infNFSe') ?? $this->no('infDPS');
        $out['numero'] = $this->txt(['nNFSe', 'nDPS'], $inf);
        $out['serie'] = $this->txt(['serie']);
        $id = $inf instanceof \DOMElement ? $inf->getAttribute('Id') : '';
        $out['chave'] = self::doc($id) ?: null;
        $out['data_emissao'] = self::data($this->txt(['dhEmi', 'dhProc', 'dCompet']));
        $prest = $this->pessoa($this->no('emit') ?? $this->no('prest'));
        $toma = $this->pessoa($this->no('toma'));
        $out['emitente_nome'] = $prest['nome']; $out['emitente_doc'] = $prest['doc']; $out['emitente_fantasia'] = $prest['fantasia'];
        $out['destinatario_nome'] = $toma['nome']; $out['destinatario_doc'] = $toma['doc'];
        $out['valor'] = self::valor($this->txt(['valores/vLiq', 'vLiq', 'vServPrest/vServ', 'vServ']));
        $out['descricao'] = self::corta($this->txt(['xDescServ', 'xTribNac', 'xTribMun']));
        $out['observacoes'] = $this->txt(['xInfComp']);
        return $out;
    }

    // ─── NFS-e padrão ABRASF (prefeituras) ──────────────────────────────────
    private function nfseAbrasf(): array
    {
        $out = self::base();
        $out['modelo'] = 'NFS-e';
        $inf = $this->no('InfNfse') ?? $this->no('Nfse') ?? $this->xp->document->documentElement;
        $out['numero'] = $this->txt(['Numero', 'NumeroNfse'], $inf);
        $out['chave'] = $this->txt(['CodigoVerificacao'], $inf);
        $out['data_emissao'] = self::data($this->txt(['DataEmissao', 'DataEmissaoNfse', 'Competencia'], $inf));

        $prest = $this->no('PrestadorServico', $inf) ?? $this->no('Prestador', $inf);
        $toma = $this->no('TomadorServico', $inf) ?? $this->no('Tomador', $inf);
        $p = $this->pessoa($prest);
        $t = $this->pessoa($toma);
        $out['emitente_nome'] = $p['nome']; $out['emitente_doc'] = $p['doc']; $out['emitente_fantasia'] = $p['fantasia'];
        $out['destinatario_nome'] = $t['nome']; $out['destinatario_doc'] = $t['doc'];
        $out['valor'] = self::valor($this->txt(['ValoresNfse/ValorLiquidoNfse', 'ValorLiquidoNfse', 'Servico/Valores/ValorServicos', 'ValorServicos'], $inf));
        $out['descricao'] = self::corta($this->txt(['Servico/Discriminacao', 'Discriminacao'], $inf));
        $out['observacoes'] = $this->txt(['OutrasInformacoes'], $inf);
        return $out;
    }

    private static function corta(?string $v, int $n = 200): ?string
    {
        if ($v === null) return null;
        $v = trim(preg_replace('/\s+/u', ' ', $v));
        return mb_strlen($v) > $n ? mb_substr($v, 0, $n - 1) . '…' : $v;
    }

    private static function resumoItens(array $itens): ?string
    {
        $itens = array_values(array_filter($itens));
        if (!$itens) return null;
        $txt = implode(', ', array_slice($itens, 0, 2));
        if (count($itens) > 2) $txt .= ' e mais ' . (count($itens) - 2) . ' ite' . (count($itens) - 2 === 1 ? 'm' : 'ns');
        return self::corta($txt);
    }
}
