<?php
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\Documento;
use App\Services\Upload;

/** Contratos/aditivos de parceiros e fornecedores, notas fiscais, contrato principal. */
class DocumentoController extends Controller
{
    /** Contrato de parceiro/fornecedor também é gerido de dentro da própria tela. */
    private function permitir(?string $tipo, bool $editar): void
    {
        $tela = $tipo === 'contrato_parceiro' ? 'cad-parceiros' : ($tipo === 'contrato_fornecedor' ? 'cad-fornecedores' : 'importacao');
        $editar ? $this->autorizarEdicao($tela) : $this->autorizar($tela, 'importacao');
    }

    public function index(): array
    {
        $tipo = $this->query('tipo');
        if ($tipo && !in_array($tipo, Documento::TIPOS, true)) throw new HttpException(400, 'Tipo inválido');
        $this->permitir($tipo, false);
        return Documento::listar($tipo, $this->query('parceiro_id') ? (int) $this->query('parceiro_id') : null, $this->query('fornecedor_id') ? (int) $this->query('fornecedor_id') : null);
    }

    public function store(): array
    {
        $tipo = $this->input('tipo');
        if (!in_array($tipo, Documento::TIPOS, true)) throw new HttpException(400, 'Tipo inválido');
        $this->permitir($tipo, true);
        $parceiroId = $this->input('parceiro_id') ? (int) $this->input('parceiro_id') : null;
        $fornecedorId = $this->input('fornecedor_id') ? (int) $this->input('fornecedor_id') : null;
        if ($tipo === 'contrato_parceiro' && (!$parceiroId || !Database::one('SELECT id FROM parceiros WHERE id=?', [$parceiroId]))) throw new HttpException(400, 'Selecione o parceiro');
        if ($tipo === 'contrato_fornecedor' && (!$fornecedorId || !Database::one('SELECT id FROM fornecedores WHERE id=?', [$fornecedorId]))) throw new HttpException(400, 'Selecione o fornecedor');
        $arq = Upload::salvar('arquivo', 'documentos', Upload::DOCUMENTO);
        if (!$arq) throw new HttpException(400, 'Arquivo não enviado');
        $id = Documento::create([
            'tipo' => $tipo, 'parceiro_id' => $parceiroId, 'fornecedor_id' => $fornecedorId,
            'descricao' => cut(str_or_null($this->input('descricao')), 255), 'arquivo_path' => $arq['path'], 'arquivo_nome' => $arq['nome'],
            'user_id' => Auth::user()['id'],
        ]);
        $this->log('create', 'documentos', $id, "$tipo: {$arq['nome']}");
        return ['id' => $id];
    }

    public function destroy($id): array
    {
        $id = (int) $id;
        $row = Documento::findOrFail($id, 'Documento não encontrado');
        $this->permitir($row['tipo'], true);
        Documento::delete($id);
        Upload::apagar($row['arquivo_path']);
        $this->log('delete', 'documentos', $id, $row['arquivo_nome'], null, $row);
        return ['ok' => true];
    }

    // ─── Contrato principal (anexar/substituir/remover sem reenviar o cadastro) ─
    private const CONTRATO = ['parceiros' => ['cad-parceiros', 'Parceiro'], 'fornecedores' => ['cad-fornecedores', 'Fornecedor']];

    public function anexarContrato(string $tabela, $id): array
    {
        [$tela, $label] = self::CONTRATO[$tabela];
        $this->autorizarEdicao($tela);
        $id = (int) $id;
        $row = Database::one("SELECT id, nome, contrato_path FROM `$tabela` WHERE id=?", [$id]);
        if (!$row) throw new HttpException(404, "$label não encontrado");
        $ct = Upload::salvar('contrato', $tabela, Upload::CONTRATO);
        if (!$ct) throw new HttpException(400, 'Arquivo não enviado');
        Upload::apagar($row['contrato_path']);
        Database::run("UPDATE `$tabela` SET contrato_path=?, contrato_nome=? WHERE id=?", [$ct['path'], $ct['nome'], $id]);
        $this->log('update', $tabela, $id, "{$row['nome']} — contrato anexado: {$ct['nome']}");
        return ['contrato_path' => public_path($ct['path']), 'contrato_nome' => $ct['nome']];
    }

    public function removerContrato(string $tabela, $id): array
    {
        [$tela, $label] = self::CONTRATO[$tabela];
        $this->autorizarEdicao($tela);
        $id = (int) $id;
        $row = Database::one("SELECT id, nome, contrato_path FROM `$tabela` WHERE id=?", [$id]);
        if (!$row) throw new HttpException(404, "$label não encontrado");
        Upload::apagar($row['contrato_path']);
        Database::run("UPDATE `$tabela` SET contrato_path=NULL, contrato_nome=NULL WHERE id=?", [$id]);
        $this->log('update', $tabela, $id, "{$row['nome']} — contrato removido");
        return ['ok' => true];
    }
}
