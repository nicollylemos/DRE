<?php
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\NotaFiscal;
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
        // Lançamento gerado a partir de uma nota fiscal: já deixa os dois vinculados.
        $nfId = (int) $this->input('nota_fiscal_id', 0);
        if ($nfId && Auth::podeEditar('notas-fiscais') && NotaFiscal::find($nfId)) {
            NotaFiscal::update($nfId, ['transaction_id' => $id, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->log('vincular', 'notas_fiscais', $nfId, "→ lançamento #$id");
        }
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('lancamentos');
        $id = (int) $id;
        $atual = Transacao::findOrFail($id, 'Lançamento não encontrado');
        $f = Transacao::campos($this->body());
        // Taxa de cartão é a do dia do lançamento: se a forma ou o valor não mudaram, mantém a taxa original
        // (mudar a taxa da forma de pagamento depois não pode alterar o DRE de meses passados).
        if ($f['tipo'] === $atual['tipo'] && $f['forma_pagamento_nome'] === (string) $atual['forma_pagamento_nome'] && abs($f['valor'] - (float) $atual['valor']) < 0.005) {
            $f['taxa_mdr'] = round((float) $atual['taxa_mdr'], 2);
            $f['valor_liquido'] = round($f['valor'] - $f['taxa_mdr'], 2);
        }
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
        // A nota fiscal continua guardada, só perde o vínculo.
        NotaFiscal::desvincularLancamento($id);
        $this->log('delete', 'transactions', $id, "{$row['tipo']} - {$row['descricao']} - R\${$row['valor']}", null, $row);
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
        $this->log('delete', 'transfers', $id, "{$row['origem_nome']} → {$row['destino_nome']} - R\${$row['valor']}", null, $row);
        return ['ok' => true];
    }
}
