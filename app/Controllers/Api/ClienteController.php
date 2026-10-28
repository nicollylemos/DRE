<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Cliente;

class ClienteController extends Controller
{
    public function index(): array
    {
        $this->autorizar('cad-clientes', 'lancamentos');
        return Cliente::listar();
    }

    public function store(): array
    {
        $this->autorizarEdicao('cad-clientes');
        $f = Cliente::campos($this->body(), null);
        $id = Cliente::create($f);
        $this->log('create', 'clientes', $id, $f['nome'] . ($f['cnpj_cpf'] ? " ({$f['cnpj_cpf']})" : ''));
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('cad-clientes');
        $id = (int) $id;
        $atual = Cliente::findOrFail($id, 'Cliente não encontrado');
        $f = Cliente::campos($this->body(), $id);
        Cliente::update($id, $f);
        // Lançamentos guardam o nome do cliente — mantém sincronizado ao renomear.
        if ($f['nome'] !== $atual['nome']) Database::run("UPDATE transactions SET cliente_fornecedor=? WHERE cliente_fornecedor=? AND tipo='income'", [$f['nome'], $atual['nome']]);
        $this->log('update', 'clientes', $id, nome_e_mudancas($f['nome'], diff_log($atual, $f, Cliente::ROTULOS)));
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('cad-clientes');
        $id = (int) $id;
        $row = Cliente::findOrFail($id, 'Cliente não encontrado');
        Cliente::delete($id);
        $this->log('delete', 'clientes', $id, $row['nome'] . ($row['cnpj_cpf'] ? " ({$row['cnpj_cpf']})" : ''), null, $row);
        return ['ok' => true];
    }
}
