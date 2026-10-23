<?= App\Core\View::partial('partials/entidade', [
    'prefixo' => 'parceiros', 'titulo' => 'Parceiros', 'novo' => 'Novo parceiro', 'docLabel' => 'CNPJ',
    'descricao' => 'Sua rede de parceiros estratégicos: contatos, CNPJ e contratos em um só lugar.', 'podeEditar' => $podeEditar,
]) ?>
