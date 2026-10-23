<?php
namespace App\Controllers\Api;

use App\Core\Controller;
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
}
