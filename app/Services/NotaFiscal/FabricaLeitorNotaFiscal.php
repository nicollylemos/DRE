<?php
namespace App\Services\NotaFiscal;

use App\Core\HttpException;

/**
 * PADRÃO FACTORY — decide qual leitor cria para cada XML.
 * Quem usa (NotaFiscalXml, importação em lote, sugestão de lançamento) não sabe
 * nada de NF-e ou NFS-e: pede um leitor à fábrica e chama ler().
 * Para aceitar um layout novo: criar outra subclasse de LeitorNotaFiscal e incluí-la aqui.
 */
final class FabricaLeitorNotaFiscal
{
    /** Ordem importa: o primeiro que reconhecer o XML é o escolhido. */
    private static function candidatos(): array
    {
        return [new LeitorNfe(), new LeitorNfseNacional(), new LeitorNfseAbrasf()];
    }

    public static function criar(DocumentoXml $doc): LeitorNotaFiscal
    {
        foreach (self::candidatos() as $leitor) if ($leitor->reconhece($doc)) return $leitor;
        throw new HttpException(400, 'Esse XML não parece ser uma NF-e nem uma NFS-e');
    }
}
