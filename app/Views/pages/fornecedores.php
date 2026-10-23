<?= App\Core\View::partial('partials/entidade', [
    'prefixo' => 'fornecedores', 'titulo' => 'Fornecedores', 'novo' => 'Novo fornecedor', 'docLabel' => 'CNPJ / CPF',
    'descricao' => 'Quem fornece para a empresa, com documentos, contatos e contratos sempre à mão.', 'podeEditar' => $podeEditar,
]) ?>
