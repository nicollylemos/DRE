<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\Documento;
use App\Models\Fornecedor;
use App\Services\Upload;

/** Fornecedores (multipart/form-data via POST pra criar e editar, igual parceiros). */
class FornecedorController extends Controller
{
    public function index(): array
    {
        $this->autorizar('cad-fornecedores', 'importacao', 'lancamentos');
        return Fornecedor::listar();
    }

    public function store(): array
    {
        $this->autorizarEdicao('cad-fornecedores');
        $f = Fornecedor::campos($this->body(), null);
        $ct = Upload::salvar('contrato', 'fornecedores', Upload::CONTRATO);
        $f += ['contrato_path' => $ct['path'] ?? null, 'contrato_nome' => $ct['nome'] ?? null];
        $id = Fornecedor::create($f);
        $this->log('create', 'fornecedores', $id, "{$f['nome']} ({$f['cnpj_cpf']})");
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('cad-fornecedores');
        $id = (int) $id;
        $atual = Fornecedor::findOrFail($id, 'Fornecedor não encontrado');
        $f = Fornecedor::campos($this->body(), $id);
        // Campos que a tela não mostra (cidade/UF) ficam como estavam.
        foreach (['cidade', 'estado', 'telefone'] as $k) if (!Request::has($k)) $f[$k] = $atual[$k];
        $f['contrato_path'] = $atual['contrato_path']; $f['contrato_nome'] = $atual['contrato_nome'];
        if ($ct = Upload::salvar('contrato', 'fornecedores', Upload::CONTRATO)) {
            Upload::apagar($atual['contrato_path']); $f['contrato_path'] = $ct['path']; $f['contrato_nome'] = $ct['nome'];
        } elseif ($this->input('remover_contrato') === '1') {
            Upload::apagar($atual['contrato_path']); $f['contrato_path'] = $f['contrato_nome'] = null;
        }
        Fornecedor::update($id, $f);
        // Lançamentos guardam o nome do fornecedor como texto — mantém em dia ao renomear.
        if ($f['nome'] !== $atual['nome']) Database::run('UPDATE transactions SET cliente_fornecedor=? WHERE cliente_fornecedor=?', [$f['nome'], $atual['nome']]);
        $this->log('update', 'fornecedores', $id, "{$f['nome']} ({$f['cnpj_cpf']})");
        return ['ok' => true];
    }

    public function situacao($id): array
    {
        $this->autorizarEdicao('cad-fornecedores');
        $id = (int) $id;
        $row = Fornecedor::findOrFail($id, 'Fornecedor não encontrado');
        $ativo = $this->input('ativo') ? 1 : 0;
        Fornecedor::update($id, ['ativo' => $ativo]);
        $this->log('update', 'fornecedores', $id, $row['nome'] . ($ativo ? ' — ativado' : ' — desativado'));
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('cad-fornecedores');
        $id = (int) $id;
        $row = Fornecedor::findOrFail($id, 'Fornecedor não encontrado');
        Fornecedor::delete($id);
        Upload::apagar($row['contrato_path']);
        foreach (Documento::doFornecedor($id) as $d) Upload::apagar($d['arquivo_path']);
        Database::run('DELETE FROM documentos WHERE fornecedor_id=?', [$id]);
        $this->log('delete', 'fornecedores', $id, $row['nome'], null, $row);
        return ['ok' => true];
    }
}
