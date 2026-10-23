<?php
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\Cadastro;
use App\Models\Caixa;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\FormaPagamento;
use App\Models\Fornecedor;

/** Resumo dos cadastros (alimenta os selects) + plano de contas e centros de custo. */
class CadastroController extends Controller
{
    public function resumo(): array
    {
        Auth::require();
        $cr = Database::all('SELECT id, grupo, conta FROM contas_receita ORDER BY id');
        $cd = Database::all('SELECT id, grupo, conta FROM contas_despesa ORDER BY id');
        return [
            'formasPagamento'     => array_column(FormaPagamento::resumo(), 'nome'),
            'formasFull'          => array_map(fn($f) => ['nome' => $f['nome'], 'taxa_intermediacao' => (float) $f['taxa_intermediacao'], 'tarifa_fixa' => (float) $f['tarifa_fixa']], FormaPagamento::resumo()),
            'caixasBancos'        => Caixa::nomes(),
            'centrosCusto'        => array_column(Database::all('SELECT nome FROM centros_custo ORDER BY id'), 'nome'),
            'gruposContasReceita' => array_values(array_unique(array_column($cr, 'grupo'))),
            'gruposContasDespesa' => array_values(array_unique(array_column($cd, 'grupo'))),
            'contasReceita'       => $cr,
            'contasDespesa'       => $cd,
            'clientes'            => Cliente::nomes(),
            'fornecedores'        => Fornecedor::nomesAtivos(),
            'categorias'          => Categoria::ativas(),
        ];
    }

    /** contas_receita / contas_despesa exigem Lançamentos (editar); centros de custo só login. */
    private function tabela(string $t, bool $editar): void
    {
        if (!isset(Cadastro::TABELAS[$t])) throw new HttpException(404, 'Cadastro não encontrado');
        if ($t === 'centros_custo') { $editar ? $this->autorizarEdicao('lancamentos') : Auth::require(); return; }
        $editar ? $this->autorizarEdicao('lancamentos') : $this->autorizar('lancamentos', 'dre');
    }

    public function listar(string $t): array { $this->tabela($t, false); return Cadastro::listar($t); }

    public function criar(string $t): array
    {
        $this->tabela($t, true);
        $d = Cadastro::campos($t, $this->body(), false);
        $id = Cadastro::criar($t, $d);
        $this->log('create', $t, $id, $d['nome'] ?? $d['grupo'] ?? null);
        return ['id' => $id];
    }

    public function atualizar(string $t, $id): array
    {
        $this->tabela($t, true);
        $d = Cadastro::campos($t, $this->body(), true);
        Cadastro::atualizar($t, (int) $id, $d);
        $this->log('update', $t, (int) $id, $d['nome'] ?? $d['grupo'] ?? null);
        return ['ok' => true];
    }

    public function apagar(string $t, $id): array
    {
        $this->tabela($t, true);
        $row = Cadastro::find($t, (int) $id);
        if (!$row) throw new HttpException(404, 'Registro não encontrado');
        Cadastro::apagar($t, (int) $id);
        $this->log('delete', $t, (int) $id, $row['nome'] ?? $row['grupo'] ?? null);
        return ['ok' => true];
    }
}
