<?php
namespace App\Services;

use App\Services\NotaFiscal\DocumentoXml;
use App\Services\NotaFiscal\FabricaLeitorNotaFiscal;
use App\Services\NotaFiscal\LeitorNfe;

/**
 * Lê o XML de uma nota fiscal e devolve os campos que interessam ao financeiro.
 *
 * Formatos aceitos (um leitor para cada, escolhido pela FabricaLeitorNotaFiscal):
 *  - NF-e / NFC-e (modelo 55/65, layout da SEFAZ — arquivo "procNFe" ou só "NFe")
 *  - NFS-e no padrão ABRASF (a maioria das prefeituras) e no padrão Nacional (gov.br/nfse)
 */
final class NotaFiscalXml
{
    /** Código tPag da SEFAZ → tipo de forma de pagamento do sistema. */
    public const TPAG = LeitorNfe::TPAG;

    public static function ler(string $xml): array
    {
        $doc = DocumentoXml::carregar($xml);
        return FabricaLeitorNotaFiscal::criar($doc)->ler($doc);
    }
}
