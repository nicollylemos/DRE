<?php
/**
 * routes.php — todas as rotas do sistema.
 * Páginas (HTML) ficam embaixo; a API (JSON) usada pelas telas fica em /api/*.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Api\AuthController;
use App\Controllers\Api\CadastroController;
use App\Controllers\Api\CaixaController;
use App\Controllers\Api\CategoriaController;
use App\Controllers\Api\ClienteController;
use App\Controllers\Api\DocumentoController;
use App\Controllers\Api\DreController;
use App\Controllers\Api\ExportController;
use App\Controllers\Api\FormaPagamentoController;
use App\Controllers\Api\FornecedorController;
use App\Controllers\Api\ImportacaoController;
use App\Controllers\Api\LogController;
use App\Controllers\Api\NotaFiscalController;
use App\Controllers\Api\ParceiroController;
use App\Controllers\Api\PerfilController;
use App\Controllers\Api\TransacaoController;
use App\Controllers\Api\UsuarioController;
use App\Controllers\PageController;

// ─── Autenticação ───────────────────────────────────────────────────────────
$router->get('api/auth/setup-status', [AuthController::class, 'setupStatus']);
$router->post('api/auth/register', [AuthController::class, 'register']);
$router->post('api/auth/login', [AuthController::class, 'login']);
$router->post('api/auth/logout', [AuthController::class, 'logout']);
$router->get('api/auth/me', [AuthController::class, 'me']);
$router->post('api/auth/trocar-senha', [AuthController::class, 'trocarSenha']);
$router->add('POST|PUT', 'api/auth/profile', [AuthController::class, 'perfil']);
$router->post('api/auth/aceitar-termos', [AuthController::class, 'aceitarTermos']);

// ─── Relatórios ─────────────────────────────────────────────────────────────
$router->get('api/dre', [DreController::class, 'dashboard']);
$router->get('api/dre/resumo', [DreController::class, 'resumo']);
$router->get('api/dre/detalhado', [DreController::class, 'detalhado']);
$router->get('api/dre/demonstrativo', [DreController::class, 'demonstrativo']);
$router->get('api/dre/fluxo-caixa', [DreController::class, 'fluxoCaixa']);
$router->get('api/dre/formas-pagamento', [DreController::class, 'formasPagamento']);
$router->get('api/agenda', [DreController::class, 'agenda']);

// ─── Lançamentos e transferências ───────────────────────────────────────────
$router->get('api/transactions', [TransacaoController::class, 'index']);
$router->get('api/transactions/em-aberto', [TransacaoController::class, 'emAberto']);
$router->post('api/transactions', [TransacaoController::class, 'store']);
$router->put('api/transactions/:id', [TransacaoController::class, 'update']);
$router->patch('api/transactions/:id', [TransacaoController::class, 'patch']);
$router->delete('api/transactions/:id', [TransacaoController::class, 'destroy']);
$router->get('api/transactions/:id/comprovante', [TransacaoController::class, 'verComprovante']);
$router->post('api/transactions/:id/comprovante', [TransacaoController::class, 'anexarComprovante']);
$router->delete('api/transactions/:id/comprovante', [TransacaoController::class, 'removerComprovante']);
$router->get('api/transfers', [TransacaoController::class, 'transferencias']);
$router->post('api/transfers', [TransacaoController::class, 'criarTransferencia']);
$router->delete('api/transfers/:id', [TransacaoController::class, 'apagarTransferencia']);

// ─── Notas fiscais (rotas fixas antes das que têm :id) ──────────────────────
$router->get('api/notas-fiscais', [NotaFiscalController::class, 'index']);
$router->post('api/notas-fiscais', [NotaFiscalController::class, 'store']);
$router->post('api/notas-fiscais/ler-xml', [NotaFiscalController::class, 'lerXml']);
$router->post('api/notas-fiscais/lote', [NotaFiscalController::class, 'lote']);
$router->get('api/notas-fiscais/:id', [NotaFiscalController::class, 'show']);
$router->add('POST|PUT', 'api/notas-fiscais/:id', [NotaFiscalController::class, 'update']);
$router->delete('api/notas-fiscais/:id', [NotaFiscalController::class, 'destroy']);
$router->get('api/notas-fiscais/:id/arquivo/:qual', [NotaFiscalController::class, 'arquivo']);
$router->delete('api/notas-fiscais/:id/arquivo/:qual', [NotaFiscalController::class, 'removerArquivo']);
$router->get('api/notas-fiscais/:id/xml', [NotaFiscalController::class, 'xml']);
$router->patch('api/notas-fiscais/:id/vinculo', [NotaFiscalController::class, 'vincular']);
$router->get('api/notas-fiscais/:id/candidatos', [NotaFiscalController::class, 'candidatos']);
$router->get('api/notas-fiscais/:id/lancamento', [NotaFiscalController::class, 'sugestaoLancamento']);

// ─── Cadastros ──────────────────────────────────────────────────────────────
$router->get('api/cadastros', [CadastroController::class, 'resumo']);
foreach (['contas_receita', 'contas_despesa', 'centros_custo'] as $t) {
    $router->get("api/$t", [CadastroController::class, 'listar', $t]);
    $router->post("api/$t", [CadastroController::class, 'criar', $t]);
    $router->put("api/$t/:id", [CadastroController::class, 'atualizar', $t]);
    $router->delete("api/$t/:id", [CadastroController::class, 'apagar', $t]);
}

$router->get('api/clientes', [ClienteController::class, 'index']);
$router->post('api/clientes', [ClienteController::class, 'store']);
$router->put('api/clientes/:id', [ClienteController::class, 'update']);
$router->delete('api/clientes/:id', [ClienteController::class, 'destroy']);

$router->get('api/fornecedores', [FornecedorController::class, 'index']);
$router->post('api/fornecedores', [FornecedorController::class, 'store']);
$router->add('POST|PUT', 'api/fornecedores/:id', [FornecedorController::class, 'update']);
$router->patch('api/fornecedores/:id/situacao', [FornecedorController::class, 'situacao']);
$router->delete('api/fornecedores/:id', [FornecedorController::class, 'destroy']);

$router->get('api/parceiros', [ParceiroController::class, 'index']);
$router->post('api/parceiros', [ParceiroController::class, 'store']);
$router->add('POST|PUT', 'api/parceiros/:id', [ParceiroController::class, 'update']);
$router->patch('api/parceiros/:id/situacao', [ParceiroController::class, 'situacao']);
$router->delete('api/parceiros/:id', [ParceiroController::class, 'destroy']);

foreach (['parceiros', 'fornecedores'] as $t) {
    $router->post("api/$t/:id/contrato", [DocumentoController::class, 'anexarContrato', $t]);
    $router->delete("api/$t/:id/contrato", [DocumentoController::class, 'removerContrato', $t]);
}
$router->get('api/documentos', [DocumentoController::class, 'index']);
$router->post('api/documentos', [DocumentoController::class, 'store']);
$router->delete('api/documentos/:id', [DocumentoController::class, 'destroy']);

$router->get('api/categorias', [CategoriaController::class, 'index']);
$router->post('api/categorias', [CategoriaController::class, 'store']);
$router->put('api/categorias/:id', [CategoriaController::class, 'update']);
$router->delete('api/categorias/:id', [CategoriaController::class, 'destroy']);

$router->get('api/caixas', [CaixaController::class, 'index']);
$router->post('api/caixas', [CaixaController::class, 'store']);
$router->put('api/caixas/:id', [CaixaController::class, 'update']);
$router->delete('api/caixas/:id', [CaixaController::class, 'destroy']);

$router->get('api/formas_pagamento', [FormaPagamentoController::class, 'index']);
$router->post('api/formas_pagamento', [FormaPagamentoController::class, 'store']);
$router->put('api/formas_pagamento/:id', [FormaPagamentoController::class, 'update']);
$router->patch('api/formas_pagamento/:id/situacao', [FormaPagamentoController::class, 'situacao']);
$router->delete('api/formas_pagamento/:id', [FormaPagamentoController::class, 'destroy']);

// ─── Usuários e níveis de acesso (admin) ────────────────────────────────────
$router->get('api/users', [UsuarioController::class, 'index']);
$router->post('api/users', [UsuarioController::class, 'store']);
$router->put('api/users/:id', [UsuarioController::class, 'update']);
$router->post('api/users/:id/senha', [UsuarioController::class, 'senha']);
$router->patch('api/users/:id/situacao', [UsuarioController::class, 'situacao']);
$router->post('api/users/:id/desbloquear', [UsuarioController::class, 'desbloquear']);
$router->delete('api/users/:id', [UsuarioController::class, 'destroy']);

$router->get('api/perfis', [PerfilController::class, 'index']);
$router->post('api/perfis', [PerfilController::class, 'store']);
$router->put('api/perfis/:id', [PerfilController::class, 'update']);
$router->delete('api/perfis/:id', [PerfilController::class, 'destroy']);

$router->get('api/logs', [LogController::class, 'index']);
$router->get('api/logs/:id', [LogController::class, 'show']);

// ─── Exportar / Importar ────────────────────────────────────────────────────
$router->get('api/export/excel', [ExportController::class, 'excel']);
$router->get('api/export/csv', [ExportController::class, 'csv']);
$router->get('api/export/xml', [ExportController::class, 'xml']);
$router->get('api/export/pdf', [ExportController::class, 'pdf']);
$router->get('api/export/dre-excel', [ExportController::class, 'dreExcel']);

$router->get('api/importacao', [ImportacaoController::class, 'index']);
$router->get('api/importacao/modelo', [ImportacaoController::class, 'modelo']);
$router->post('api/importacao/analisar', [ImportacaoController::class, 'analisar']);
$router->post('api/importacao/validar', [ImportacaoController::class, 'validar']);
$router->post('api/importacao/confirmar', [ImportacaoController::class, 'confirmar']);
$router->delete('api/importacao/:id', [ImportacaoController::class, 'desfazer']);

// ─── Páginas ────────────────────────────────────────────────────────────────
$router->get('', [PageController::class, 'inicio']);
$router->get('login', [PageController::class, 'login']);
$router->get('privacidade', [PageController::class, 'privacidade']);
$router->get('termos', [PageController::class, 'termos']);
$router->get(':pagina', [PageController::class, 'pagina']);
