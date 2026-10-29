<?php
namespace App\Services\NotaFiscal;

/** Produto concreto: NFS-e no padrão Nacional (gov.br/nfse). */
final class LeitorNfseNacional extends LeitorNotaFiscal
{
    public function reconhece(DocumentoXml $doc): bool { return $doc->temAlguma('infNFSe', 'infDPS'); }

    public function ler(DocumentoXml $doc): array
    {
        $out = self::base();
        $out['modelo'] = 'NFS-e';
        $inf = $doc->no('infNFSe') ?? $doc->no('infDPS');
        $out['numero'] = $doc->txt(['nNFSe', 'nDPS'], $inf);
        $out['serie'] = $doc->txt(['serie']);
        $id = $inf instanceof \DOMElement ? $inf->getAttribute('Id') : '';
        $out['chave'] = self::doc($id) ?: null;
        $out['data_emissao'] = self::data($doc->txt(['dhEmi', 'dhProc', 'dCompet']));
        $prest = self::pessoa($doc, $doc->no('emit') ?? $doc->no('prest'));
        $toma = self::pessoa($doc, $doc->no('toma'));
        $out['emitente_nome'] = $prest['nome']; $out['emitente_doc'] = $prest['doc']; $out['emitente_fantasia'] = $prest['fantasia'];
        $out['destinatario_nome'] = $toma['nome']; $out['destinatario_doc'] = $toma['doc'];
        $out['valor'] = self::valor($doc->txt(['valores/vLiq', 'vLiq', 'vServPrest/vServ', 'vServ']));
        $out['descricao'] = self::corta($doc->txt(['xDescServ', 'xTribNac', 'xTribMun']));
        $out['observacoes'] = $doc->txt(['xInfComp']);
        return $out;
    }
}
