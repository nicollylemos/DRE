<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Importacao;
use App\Services\ImportService;
use App\Services\Spreadsheet\XlsxWriter;

/** Importação de planilhas (lançamentos, clientes, fornecedores). */
class ImportacaoController extends Controller
{
    /** Histórico. */
    public function index(): array
    {
        $this->autorizar('importacao');
        return Importacao::listar();
    }

    private function tipo(): string
    {
        $t = (string) ($this->input('tipo') ?? $this->query('tipo', ''));
        if (!isset(ImportService::TIPOS[$t])) throw new HttpException(400, 'Escolha o que vai importar');
        return $t;
    }

    /** Importar cadastros exige editar o cadastro correspondente também. */
    private function autorizarTipo(string $tipo): void
    {
        $this->autorizarEdicao('importacao');
        if ($tipo === 'lancamentos') $this->autorizarEdicao('lancamentos');
        if ($tipo === 'clientes') $this->autorizarEdicao('cad-clientes');
        if ($tipo === 'fornecedores') $this->autorizarEdicao('cad-fornecedores');
    }

    /** Passo 1: recebe o arquivo e devolve colunas + mapeamento sugerido. */
    public function analisar(): array
    {
        $tipo = $this->tipo();
        $this->autorizarTipo($tipo);
        if (empty($_FILES['arquivo'])) throw new HttpException(400, 'Envie o arquivo da planilha');
        return ImportService::analisar($tipo, $_FILES['arquivo'], (string) $this->input('cabecalho', '1') !== '0');
    }

    /** Passo 2: prévia linha a linha. */
    public function validar(): array
    {
        $tipo = $this->tipo();
        $this->autorizarTipo($tipo);
        return ImportService::processar((string) $this->input('token', ''), (array) $this->input('mapeamento', []), (array) $this->input('opcoes', []));
    }

    /** Passo 3: grava. */
    public function confirmar(): array
    {
        $tipo = $this->tipo();
        $this->autorizarTipo($tipo);
        $r = ImportService::confirmar((string) $this->input('token', ''), (array) $this->input('mapeamento', []), (array) $this->input('opcoes', []));
        $this->log('import', 'importacoes', $r['id'], ImportService::TIPOS[$tipo] . ": {$r['importados']} importado(s), {$r['ignorados']} ignorado(s) — {$r['arquivo']}");
        return $r;
    }

    /** Desfaz: apaga o que a importação criou. */
    public function desfazer($id): array
    {
        $imp = Importacao::findOrFail((int) $id, 'Importação não encontrada');
        $this->autorizarTipo($imp['tipo']);
        if ($imp['desfeita_em']) throw new HttpException(400, 'Essa importação já foi desfeita');
        $n = Importacao::desfazer($imp);
        $this->log('undo_import', 'importacoes', (int) $id, "{$imp['arquivo_nome']} — $n registro(s) removido(s)");
        return ['ok' => true, 'removidos' => $n];
    }

    /** Planilha modelo (xlsx ou csv). */
    public function modelo(): void
    {
        $this->autorizar('importacao');
        $tipo = (string) $this->query('tipo', 'lancamentos');
        if (!isset(ImportService::TIPOS[$tipo])) throw new HttpException(400, 'Tipo inválido');
        $rows = ImportService::modelo($tipo);
        if ($this->query('formato') === 'csv') {
            $f = fopen('php://temp', 'w+');
            foreach ($rows as $r) fputcsv($f, array_map(fn($v) => is_float($v) ? number_format($v, 2, ',', '') : $v, $r), ';', '"', '');
            rewind($f);
            Response::download("\xEF\xBB\xBF" . stream_get_contents($f), 'text/csv; charset=utf-8', "modelo_$tipo.csv");
        }
        $x = new XlsxWriter();
        $x->addSheet(ImportService::TIPOS[$tipo], $rows);
        Response::download($x->output(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', "modelo_$tipo.xlsx");
    }
}
