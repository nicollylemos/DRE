<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\FormaPagamento;

class FormaPagamentoController extends Controller
{
    public function index(): array
    {
        $this->autorizar('cad-formas');
        return FormaPagamento::listar();
    }

    public function store(): array
    {
        $this->autorizarEdicao('cad-formas');
        $f = FormaPagamento::campos($this->body(), null);
        $id = FormaPagamento::create($f);
        $this->log('create', 'formas_pagamento', $id, "{$f['nome']} ({$f['tipo']})");
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('cad-formas');
        $id = (int) $id;
        $atual = FormaPagamento::findOrFail($id, 'Forma de pagamento não encontrada');
        $f = FormaPagamento::campos($this->body(), $id);
        FormaPagamento::update($id, $f);
        if ($f['nome'] !== $atual['nome']) Database::run('UPDATE transactions SET forma_pagamento_nome=? WHERE forma_pagamento_nome=?', [$f['nome'], $atual['nome']]);
        $this->log('update', 'formas_pagamento', $id, nome_e_mudancas($f['nome'], diff_log($atual, $f, FormaPagamento::ROTULOS)));
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('cad-formas');
        $id = (int) $id;
        $row = FormaPagamento::findOrFail($id, 'Forma de pagamento não encontrada');
        if ($uso = FormaPagamento::uso($row['nome'])) throw new HttpException(400, "\"{$row['nome']}\" está em $uso lançamento(s). Apagar tiraria a taxa desses lançamentos dos relatórios — renomeie ou ajuste em vez de apagar.");
        FormaPagamento::delete($id);
        $this->log('delete', 'formas_pagamento', $id, $row['nome']);
        return ['ok' => true];
    }
}
