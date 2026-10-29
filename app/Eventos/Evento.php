<?php
namespace App\Eventos;

/** Algo que aconteceu no sistema (nome + dados), entregue aos ouvintes pelo Despachante. */
final class Evento
{
    public const LANCAMENTO_CRIADO = 'lancamento.criado';
    public const LANCAMENTO_APAGADO = 'lancamento.apagado';

    public string $nome;
    public array $dados;

    public function __construct(string $nome, array $dados = [])
    {
        $this->nome = $nome;
        $this->dados = $dados;
    }
}
