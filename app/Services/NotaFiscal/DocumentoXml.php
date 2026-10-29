<?php
namespace App\Services\NotaFiscal;

use App\Core\HttpException;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * XML da nota já carregado com segurança + atalhos de busca pelo nome local da tag
 * (cada prefeitura usa um namespace, então o namespace é ignorado).
 * Segurança: XML com DOCTYPE é recusado (evita XXE / "billion laughs") e nada é baixado da rede.
 */
final class DocumentoXml
{
    private DOMXPath $xp;

    private function __construct(DOMXPath $xp) { $this->xp = $xp; }

    public static function carregar(string $xml): self
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
        return new self(new DOMXPath($dom));
    }

    /** Elemento raiz do XML. */
    public function raiz(): ?DOMNode { return $this->xp->document->documentElement; }

    /** "a/b/c" → .//*[local-name()='a']/*[local-name()='b']/*[local-name()='c'] */
    private function caminho(string $p): string
    {
        $partes = array_map(fn($n) => "*[local-name()='$n']", explode('/', $p));
        return './/' . implode('/', $partes);
    }

    public function no(string $p, ?DOMNode $ctx = null): ?DOMNode
    {
        $r = $this->xp->query($this->caminho($p), $ctx ?? $this->xp->document);
        return $r && $r->length ? $r->item(0) : null;
    }

    public function nos(string $p, ?DOMNode $ctx = null): array
    {
        $r = $this->xp->query($this->caminho($p), $ctx ?? $this->xp->document);
        $out = [];
        if ($r) foreach ($r as $n) $out[] = $n;
        return $out;
    }

    /** Primeiro texto não vazio entre vários caminhos. */
    public function txt(array $caminhos, ?DOMNode $ctx = null): ?string
    {
        foreach ($caminhos as $c) {
            $n = $this->no($c, $ctx);
            if ($n && trim($n->textContent) !== '') return trim(preg_replace('/\s+/u', ' ', $n->textContent));
        }
        return null;
    }

    /** Tem alguma destas tags? (usado pela fábrica pra reconhecer o tipo da nota) */
    public function temAlguma(string ...$tags): bool
    {
        foreach ($tags as $t) if ($this->no($t)) return true;
        return false;
    }
}
