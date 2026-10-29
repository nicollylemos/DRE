<?php
namespace App\Services\NotaFiscal;

/** Produto concreto: NF-e / NFC-e (modelo 55/65, layout da SEFAZ — "procNFe" ou só "NFe"). */
final class LeitorNfe extends LeitorNotaFiscal
{
    /** Código tPag da SEFAZ → tipo de forma de pagamento do sistema. */
    public const TPAG = ['01' => 'dinheiro', '03' => 'credito', '04' => 'debito', '05' => 'credito', '15' => 'boleto',
                         '16' => 'transferencia', '17' => 'pix', '18' => 'transferencia'];

    public function reconhece(DocumentoXml $doc): bool { return $doc->temAlguma('infNFe'); }

    public function ler(DocumentoXml $doc): array
    {
        $inf = $doc->no('infNFe');
        $ide = $doc->no('ide', $inf);
        $out = self::base();
        $mod = $doc->txt(['mod'], $ide);
        $out['modelo'] = $mod === '65' ? 'NFC-e' : 'NF-e';
        $out['numero'] = $doc->txt(['nNF'], $ide);
        $out['serie'] = $doc->txt(['serie'], $ide);
        $id = $inf instanceof \DOMElement ? $inf->getAttribute('Id') : '';
        $chave = self::doc($id) ?: self::doc($doc->txt(['chNFe']));
        $out['chave'] = $chave ?: null;
        $out['data_emissao'] = self::data($doc->txt(['dhEmi', 'dEmi'], $ide));
        $out['natureza'] = $doc->txt(['natOp'], $ide);
        $tp = $doc->txt(['tpNF'], $ide);
        $out['tp_nf'] = $tp !== null ? (int) $tp : null;

        $e = self::pessoa($doc, $doc->no('emit', $inf));
        $d = self::pessoa($doc, $doc->no('dest', $inf));
        $out['emitente_nome'] = $e['nome']; $out['emitente_doc'] = $e['doc']; $out['emitente_fantasia'] = $e['fantasia'];
        $out['destinatario_nome'] = $d['nome']; $out['destinatario_doc'] = $d['doc'];
        $out['valor'] = self::valor($doc->txt(['total/ICMSTot/vNF', 'vNF'], $inf));

        // Itens → descrição curta ("Produto A, Produto B e mais 3 itens")
        $itens = array_map(fn($p) => trim($p->textContent), $doc->nos('det/prod/xProd', $inf));
        $out['descricao'] = self::resumoItens($itens) ?? $out['natureza'];

        foreach ($doc->nos('cobr/dup', $inf) as $dup) {
            $out['duplicatas'][] = ['numero' => $doc->txt(['nDup'], $dup), 'vencimento' => self::data($doc->txt(['dVenc'], $dup)), 'valor' => self::valor($doc->txt(['vDup'], $dup))];
        }
        foreach ($doc->nos('pag/detPag', $inf) as $p) {
            $cod = str_pad((string) $doc->txt(['tPag'], $p), 2, '0', STR_PAD_LEFT);
            $out['pagamentos'][] = ['codigo' => $cod, 'tipo' => self::TPAG[$cod] ?? null, 'valor' => self::valor($doc->txt(['vPag'], $p))];
        }
        $out['observacoes'] = $doc->txt(['infAdic/infCpl'], $inf);
        return $out;
    }
}
