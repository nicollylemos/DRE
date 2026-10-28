<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Models\Caixa;

class CaixaController extends Controller
{
    public function index(): array
    {
        $this->autorizar('cad-caixas');
        return ['caixas' => Caixa::comSaldo(), 'sem_caixa' => Caixa::semCaixa()];
    }

    public function store(): array
    {
        $this->autorizarEdicao('cad-caixas');
        $f = Caixa::campos($this->body(), null);
        $id = Caixa::create($f);
        $this->log('create', 'caixas', $id, "{$f['nome']} — saldo inicial " . brl($f['saldo_inicial']));
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('cad-caixas');
        $id = (int) $id;
        $atual = Caixa::findOrFail($id, 'Caixa não encontrado');
        $f = Caixa::campos($this->body(), $id);
        Caixa::update($id, $f);
        if ($f['nome'] !== $atual['nome']) Caixa::renomearReferencias($atual['nome'], $f['nome']);
        $this->log('update', 'caixas', $id, nome_e_mudancas($f['nome'], diff_log($atual, $f, Caixa::ROTULOS)));
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('cad-caixas');
        $id = (int) $id;
        $row = Caixa::findOrFail($id, 'Caixa não encontrado');
        if ($uso = Caixa::usoDoNome($row['nome'])) throw new HttpException(400, "\"{$row['nome']}\" tem $uso movimentação(ões). Apagar faria o saldo sumir dos relatórios — renomeie em vez de apagar.");
        Caixa::delete($id);
        $this->log('delete', 'caixas', $id, $row['nome'], null, $row);
        return ['ok' => true];
    }
}
