<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Documento;
use App\Models\Parceiro;
use App\Services\Upload;

/** Parceiros (CNPJ + foto + contrato). multipart via POST pra criar e editar. */
class ParceiroController extends Controller
{
    public function index(): array
    {
        $this->autorizar('cad-parceiros', 'importacao');
        return Parceiro::listar();
    }

    public function store(): array
    {
        $this->autorizarEdicao('cad-parceiros');
        $f = Parceiro::campos($this->body(), null);
        $foto = Upload::salvar('foto', 'parceiros', Upload::IMAGEM, 8 * 1024 * 1024);
        $ct = Upload::salvar('contrato', 'parceiros', Upload::CONTRATO);
        $f += ['foto_path' => $foto['path'] ?? null, 'foto_nome' => $foto['nome'] ?? null,
               'contrato_path' => $ct['path'] ?? null, 'contrato_nome' => $ct['nome'] ?? null];
        $id = Parceiro::create($f);
        $this->log('create', 'parceiros', $id, "{$f['nome']} ({$f['cnpj']})");
        return ['id' => $id];
    }

    public function update($id): array
    {
        $this->autorizarEdicao('cad-parceiros');
        $id = (int) $id;
        $atual = Parceiro::findOrFail($id, 'Parceiro não encontrado');
        $f = Parceiro::campos($this->body(), $id);
        foreach (['foto_path', 'foto_nome', 'contrato_path', 'contrato_nome'] as $k) $f[$k] = $atual[$k];
        if ($foto = Upload::salvar('foto', 'parceiros', Upload::IMAGEM, 8 * 1024 * 1024)) {
            Upload::apagar($atual['foto_path']); $f['foto_path'] = $foto['path']; $f['foto_nome'] = $foto['nome'];
        } elseif ($this->input('remover_foto') === '1') {
            Upload::apagar($atual['foto_path']); $f['foto_path'] = $f['foto_nome'] = null;
        }
        if ($ct = Upload::salvar('contrato', 'parceiros', Upload::CONTRATO)) {
            Upload::apagar($atual['contrato_path']); $f['contrato_path'] = $ct['path']; $f['contrato_nome'] = $ct['nome'];
        } elseif ($this->input('remover_contrato') === '1') {
            Upload::apagar($atual['contrato_path']); $f['contrato_path'] = $f['contrato_nome'] = null;
        }
        Parceiro::update($id, $f);
        $this->log('update', 'parceiros', $id, "{$f['nome']} ({$f['cnpj']})");
        return ['ok' => true];
    }

    public function situacao($id): array
    {
        $this->autorizarEdicao('cad-parceiros');
        $id = (int) $id;
        $row = Parceiro::findOrFail($id, 'Parceiro não encontrado');
        $ativo = $this->input('ativo') ? 1 : 0;
        Parceiro::update($id, ['ativo' => $ativo]);
        $this->log('update', 'parceiros', $id, $row['nome'] . ($ativo ? ' — ativado' : ' — desativado'));
        return ['ok' => true];
    }

    public function destroy($id): array
    {
        $this->autorizarEdicao('cad-parceiros');
        $id = (int) $id;
        $row = Parceiro::findOrFail($id, 'Parceiro não encontrado');
        Parceiro::delete($id);
        Upload::apagar($row['foto_path']); Upload::apagar($row['contrato_path']);
        foreach (Documento::doParceiro($id) as $d) Upload::apagar($d['arquivo_path']);
        Database::run('DELETE FROM documentos WHERE parceiro_id=?', [$id]);
        $this->log('delete', 'parceiros', $id, $row['nome']);
        return ['ok' => true];
    }
}
