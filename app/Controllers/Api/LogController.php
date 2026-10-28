<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Models\Log;

class LogController extends Controller
{
    public function index(): array
    {
        $this->autorizar('logs');
        $lim = min(max((int) $this->query('limit', 50), 1), 200);
        $pg = max((int) $this->query('page', 1), 1);
        return Log::buscar([
            'action' => $this->query('action'), 'entity' => $this->query('entity'), 'username' => $this->query('username'),
            'from' => $this->query('from'), 'to' => $this->query('to'),
        ], $pg, $lim);
    }

    /** Cópia do que foi apagado/alterado (guardada junto com o log). */
    public function show($id): array
    {
        $this->autorizar('logs');
        $r = Log::dados((int) $id);
        if (!$r) throw new HttpException(404, 'Registro de log não encontrado');
        return $r;
    }
}
