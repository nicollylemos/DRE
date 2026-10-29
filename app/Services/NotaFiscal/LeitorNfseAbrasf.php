<?php
namespace App\Services\NotaFiscal;

/** Produto concreto: NFS-e no padrão ABRASF (a maioria das prefeituras). */
final class LeitorNfseAbrasf extends LeitorNotaFiscal
{
    public function reconhece(DocumentoXml $doc): bool
    {
        return $doc->temAlguma('InfNfse', 'Nfse', 'CompNfse', 'InfDeclaracaoPrestacaoServico');
    }

    public function ler(DocumentoXml $doc): array
    {
        $out = self::base();
        $out['modelo'] = 'NFS-e';
        $inf = $doc->no('InfNfse') ?? $doc->no('Nfse') ?? $doc->raiz();
        $out['numero'] = $doc->txt(['Numero', 'NumeroNfse'], $inf);
        $out['chave'] = $doc->txt(['CodigoVerificacao'], $inf);
        $out['data_emissao'] = self::data($doc->txt(['DataEmissao', 'DataEmissaoNfse', 'Competencia'], $inf));

        $prest = $doc->no('PrestadorServico', $inf) ?? $doc->no('Prestador', $inf);
        $toma = $doc->no('TomadorServico', $inf) ?? $doc->no('Tomador', $inf);
        $p = self::pessoa($doc, $prest);
        $t = self::pessoa($doc, $toma);
        $out['emitente_nome'] = $p['nome']; $out['emitente_doc'] = $p['doc']; $out['emitente_fantasia'] = $p['fantasia'];
        $out['destinatario_nome'] = $t['nome']; $out['destinatario_doc'] = $t['doc'];
        $out['valor'] = self::valor($doc->txt(['ValoresNfse/ValorLiquidoNfse', 'ValorLiquidoNfse', 'Servico/Valores/ValorServicos', 'ValorServicos'], $inf));
        $out['descricao'] = self::corta($doc->txt(['Servico/Discriminacao', 'Discriminacao'], $inf));
        $out['observacoes'] = $doc->txt(['OutrasInformacoes'], $inf);
        return $out;
    }
}
