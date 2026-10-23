<?php
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\Transacao;
use App\Models\Transferencia;

/** Lançamentos (receitas/despesas) e transferências entre caixas. */
class TransacaoController extends Controller
{
    public function index(): array
    {
        $this->autorizar('lancamentos');
        return Transacao::listar([
            'year' => $this->query('year'), 'month' => $this->query('month'), 'type' => $this->query('type'),
            'status' => $this->query('status'), 'categoria_id' => $this->query('categoria_id'),
            'centro_custo' => $this->query('centro_custo'), 'forma_pagamento' => $this->query('forma_pagamento'),
            'caixa_banco' => $this->query('caixa_banco'), 'importacao_id' => $this->query('importacao_id'),
        ]);
    }

    public function emAberto(): array
    {
        $this->autorizar('dashboard', 'lancamentos', 'calendario');
        return Transacao::emAberto();
    }

    public function store(): array
    {
        $u = $this->autorizarEdicao('lancamentos');
        $f = Transacao::campos($this->body());
        $f['user_id'] = $u['id'];
        $id = Transacao::create($f);
        $this->log('create', 'transactions', $id, "{$f['tipo']} - {$f['descricao']} - R\${$f['valor']}");
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $atual = Transacao::findOrFail($id, 'Lançamento não encontrado');
        $f = Transacao::campos($this->body());
        Transacao::update($id, $f);
        $diff = diff_log($atual, $f, Transacao::ROTULOS);
        $this->log('update', 'transactions', $id, "{$f['tipo']} - {$f['descricao']} - R\${$f['valor']}" . ($diff ? " — $diff" : ''));
        return ['ok' => true];
    }

    /** Baixa / reabrir (atualização parcial). */
    public function patch($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        Transacao::findOrFail($id, 'Lançamento não encontrado');
        $b = $this->body(); $d = [];
        if (array_key_exists('status', $b)) $d['status'] = $b['status'] === 'em_aberto' ? 'em_aberto' : 'quitado';
        if (array_key_exists('data_pagamento', $b)) $d['data_pagamento'] = ($b['data_pagamento'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $b['data_pagamento'])) ? $b['data_pagamento'] : null;
        if (array_key_exists('observacoes', $b)) $d['observacoes'] = (string) $b['observacoes'];
        if (!$d) throw new HttpException(400, 'Nenhum campo para atualizar');
        Transacao::update($id, $d);
        $this->log(($b['status'] ?? '') === 'quitado' ? 'baixa' : 'update_status', 'transactions', $id, $b['status'] ?? null);
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $row = Transacao::findOrFail($id, 'Lançamento não encontrado');
        Transacao::delete($id);
        $this->log('delete', 'transactions', $id, "{$row['tipo']} - {$row['descricao']} - R\${$row['valor']}");
        return ['ok' => true];
    }

    // ─── Transferências ─────────────────────────────────────────────────────
    public function transferencias(): array
    {
        $this->autorizar('lancamentos');
        return Transferencia::listar($this->query('year') ? (int) $this->query('year') : null, $this->query('month') ? (int) $this->query('month') : null);
    }

    public function criarTransferencia(): array
    {
        $u = $this->autorizarEdicao('lancamentos');
        $f = Transferencia::campos($this->body()) + ['user_id' => $u['id']];
        $id = Transferencia::create($f);
        $this->log('create', 'transfers', $id, "{$f['origem_nome']} → {$f['destino_nome']} - R\${$f['valor']}");
        return ['id' => $id];
    }

    public function apagarTransferencia($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $row = Transferencia::findOrFail($id, 'Transferência não encontrada');
        Transferencia::delete($id);
        $this->log('delete', 'transfers', $id, "{$row['origem_nome']} → {$row['destino_nome']} - R\${$row['valor']}");
        return ['ok' => true];
    }
}
