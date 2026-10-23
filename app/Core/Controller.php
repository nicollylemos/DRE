<?php
namespace App\Core;

use App\Models\Log;

/** Base dos Controllers: autorização, leitura da requisição e log. */
abstract class Controller
{
    /** Exige pelo menos "ver" em uma das telas. */
    protected function autorizar(string ...$telas): array
    {
        $u = Auth::require();
        if (!Auth::pode(...$telas)) throw new HttpException(403, 'Sem permissão de acesso a esta tela');
        return $u;
    }

    /** Exige "editar" na tela (criar/alterar/apagar). */
    protected function autorizarEdicao(string $tela): array
    {
        $u = Auth::require();
        if (!Auth::podeEditar($tela)) {
            throw new HttpException(403, Auth::pode($tela) ? 'Seu nível de acesso permite só consultar esta tela' : 'Sem permissão de acesso a esta tela');
        }
        return $u;
    }

    protected function somenteAdmin(): array
    {
        $u = Auth::require();
        if (!Auth::isAdmin()) throw new HttpException(403, 'Acesso restrito a administradores');
        return $u;
    }

    protected function input(string $k, $default = null) { return Request::input($k, $default); }
    protected function query(string $k, $default = null) { return Request::query($k, $default); }
    protected function body(): array { return Request::body(); }

    protected function falhar(int $status, string $msg): void { throw new HttpException($status, $msg); }

    protected function log(string $acao, string $entidade, $id = null, $detalhes = null, ?string $username = null): void
    {
        Log::registrar($acao, $entidade, $id, $detalhes, $username);
    }
}
