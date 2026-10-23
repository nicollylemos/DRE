<?php
namespace App\Core;

/** Erro com status HTTP. Lançado por controllers/models; o App transforma em resposta. */
class HttpException extends \Exception
{
    public int $status;
    public ?string $codigo;

    public function __construct(int $status, string $msg, ?string $codigo = null)
    {
        parent::__construct($msg);
        $this->status = $status;
        $this->codigo = $codigo;
    }
}
