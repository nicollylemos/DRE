<?php
// ── FORNECEDORES (mesmo modelo de Parceiros: CNPJ/CPF, contrato, situação) ────
// multipart/form-data via POST (criar e editar), igual parceiros.

function fornecedor_out(array $f): array {
    $f['id'] = (int) $f['id'];
    $f['ativo'] = (int) ($f['ativo'] ?? 1);
    $f['qtd_contratos'] = (int) ($f['qtd_contratos'] ?? 0);
    $f['contrato_path'] = public_path($f['contrato_path'] ?? null);
    return $f;
}

function fornecedor_fields(?int $id): array {
    $nome = trim((string) b('nome', ''));
    if ($nome === '') fail(400, 'Campo obrigatório: Nome');
    $doc = trim((string) b('cnpj_cpf', ''));
    if ($doc === '') fail(400, 'Campo obrigatório: CNPJ/CPF');
    if (!doc_valido($doc)) fail(400, 'CNPJ/CPF inválido — confira os números');
    $doc = fmt_doc($doc);
    $dup = $id ? q1('SELECT nome FROM fornecedores WHERE cnpj_cpf=? AND id<>?', [$doc, $id]) : q1('SELECT nome FROM fornecedores WHERE cnpj_cpf=?', [$doc]);
    if ($dup) fail(400, "CNPJ/CPF já cadastrado para o fornecedor \"{$dup['nome']}\"");
    $email = str_or_null(b('email'));
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(400, 'E-mail inválido');
    return [
        'nome' => mb_substr($nome, 0, 255), 'razao_social' => str_or_null(b('razao_social')), 'cnpj_cpf' => $doc,
        'email' => $email, 'endereco' => str_or_null(b('endereco')), 'cidade' => str_or_null(b('cidade')), 'estado' => str_or_null(b('estado')),
        'telefone' => str_or_null(b('telefone')), 'whatsapp' => str_or_null(b('whatsapp')),
        'responsavel' => str_or_null(b('responsavel')), 'observacoes' => str_or_null(b('observacoes')),
        'ativo' => b('ativo', '1') === '0' ? 0 : 1,
    ];
}

Router::get('fornecedores', function () {
    permitir('cad-fornecedores', 'import', 'lancamentos');
    $rows = q("SELECT f.*, (SELECT COUNT(*) FROM documentos d WHERE d.fornecedor_id=f.id AND d.tipo='contrato_fornecedor') AS qtd_contratos
               FROM fornecedores f ORDER BY f.nome");
    return array_map('fornecedor_out', $rows);
});

Router::post('fornecedores', function () {
    permitir('cad-fornecedores');
    $f = fornecedor_fields(null);
    $ct = save_upload('contrato', 'fornecedores', EXT_CONTRATO);
    $f += ['contrato_path' => $ct['path'] ?? null, 'contrato_nome' => $ct['nome'] ?? null];
    $c = array_keys($f);
    $id = insert_id('INSERT INTO fornecedores (`' . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($f));
    log_action('create', 'fornecedores', $id, "{$f['nome']} ({$f['cnpj_cpf']})");
    return ['id' => $id];
});

$atualizarFornecedor = function ($id) {
    permitir('cad-fornecedores');
    $id = (int) $id;
    $atual = q1('SELECT * FROM fornecedores WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Fornecedor não encontrado');
    $f = fornecedor_fields($id);
    // Campos que a tela nova não mostra (cidade/UF) ficam como estavam.
    foreach (['cidade', 'estado'] as $k) if (!array_key_exists($k, body())) $f[$k] = $atual[$k];
    $f['contrato_path'] = $atual['contrato_path']; $f['contrato_nome'] = $atual['contrato_nome'];
    if ($ct = save_upload('contrato', 'fornecedores', EXT_CONTRATO)) {
        delete_upload($atual['contrato_path']); $f['contrato_path'] = $ct['path']; $f['contrato_nome'] = $ct['nome'];
    } elseif (b('remover_contrato') === '1') {
        delete_upload($atual['contrato_path']); $f['contrato_path'] = $f['contrato_nome'] = null;
    }
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($f)));
    exec_sql("UPDATE fornecedores SET $set WHERE id=?", [...array_values($f), $id]);
    // Lançamentos guardam o nome do fornecedor como texto — mantém em dia ao renomear.
    if ($f['nome'] !== $atual['nome']) exec_sql('UPDATE transactions SET cliente_fornecedor=? WHERE cliente_fornecedor=?', [$f['nome'], $atual['nome']]);
    log_action('update', 'fornecedores', $id, "{$f['nome']} ({$f['cnpj_cpf']})");
    return ['ok' => true];
};
Router::post('fornecedores/:id', $atualizarFornecedor);
Router::put('fornecedores/:id', $atualizarFornecedor);

Router::patch('fornecedores/:id/situacao', function ($id) {
    permitir('cad-fornecedores');
    $id = (int) $id;
    $row = q1('SELECT nome FROM fornecedores WHERE id=?', [$id]);
    if (!$row) fail(404, 'Fornecedor não encontrado');
    $ativo = b('ativo') ? 1 : 0;
    exec_sql('UPDATE fornecedores SET ativo=? WHERE id=?', [$ativo, $id]);
    log_action('update', 'fornecedores', $id, $row['nome'] . ($ativo ? ' — ativado' : ' — desativado'));
    return ['ok' => true];
});

Router::delete('fornecedores/:id', function ($id) {
    permitir('cad-fornecedores');
    $id = (int) $id;
    $row = q1('SELECT * FROM fornecedores WHERE id=?', [$id]);
    if (!$row) fail(404, 'Fornecedor não encontrado');
    exec_sql('DELETE FROM fornecedores WHERE id=?', [$id]);
    delete_upload($row['contrato_path']);
    foreach (q('SELECT arquivo_path FROM documentos WHERE fornecedor_id=?', [$id]) as $d) delete_upload($d['arquivo_path']);
    exec_sql('DELETE FROM documentos WHERE fornecedor_id=?', [$id]);
    log_action('delete', 'fornecedores', $id, $row['nome']);
    return ['ok' => true];
});
