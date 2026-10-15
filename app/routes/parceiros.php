<?php
// ── PARCEIROS (CNPJ + foto + contrato) ───────────────────────────────────────
// multipart/form-data: o front manda POST tanto pra criar quanto pra editar
// (PHP não lê arquivos em PUT). PUT continua aceito pra compatibilidade.

const PARCEIRO_OBRIGATORIOS = ['nome' => 'Nome', 'razao_social' => 'Razão Social', 'cnpj' => 'CNPJ', 'responsavel' => 'Responsável', 'contato' => 'Contato'];
const EXT_CONTRATO = ['pdf', 'doc', 'docx', 'odt', 'png', 'jpg', 'jpeg', 'webp'];

function parceiro_out(array $p): array {
    $p['id'] = (int) $p['id'];
    $p['comissao'] = (float) $p['comissao'];
    $p['qtd_contratos'] = (int) ($p['qtd_contratos'] ?? 0);
    $p['ativo'] = (int) ($p['ativo'] ?? 1);
    foreach (['foto_path', 'contrato_path'] as $k) $p[$k] = public_path($p[$k] ?? null);
    return $p;
}

function parceiro_fields(?int $id): array {
    foreach (PARCEIRO_OBRIGATORIOS as $k => $label) if (trim((string) b($k, '')) === '') fail(400, "Campo obrigatório: $label");
    $cnpj = (string) b('cnpj');
    if (!cnpj_valido($cnpj)) fail(400, 'CNPJ inválido — confira os números');
    $cnpj = fmt_cnpj($cnpj);
    $dup = $id ? q1('SELECT nome FROM parceiros WHERE cnpj=? AND id<>?', [$cnpj, $id]) : q1('SELECT nome FROM parceiros WHERE cnpj=?', [$cnpj]);
    if ($dup) fail(400, "CNPJ já cadastrado para o parceiro \"{$dup['nome']}\"");
    $email = str_or_null(b('email'));
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(400, 'E-mail inválido');
    return [
        'nome' => trim((string) b('nome')), 'razao_social' => trim((string) b('razao_social')), 'cnpj' => $cnpj,
        'email' => $email, 'endereco' => str_or_null(b('endereco')),
        'comissao' => max(0, round(num(b('comissao')), 2)), // nunca negativa
        'contato' => trim((string) b('contato')), 'whatsapp' => str_or_null(b('whatsapp')),
        'responsavel' => trim((string) b('responsavel')), 'observacoes' => str_or_null(b('observacoes')),
        'ativo' => b('ativo', '1') === '0' ? 0 : 1,
    ];
}

Router::get('parceiros', function () {
    permitir('cad-parceiros', 'import');
    $rows = q("SELECT p.*, (SELECT COUNT(*) FROM documentos d WHERE d.parceiro_id=p.id AND d.tipo='contrato_parceiro') AS qtd_contratos
               FROM parceiros p ORDER BY p.nome");
    return array_map('parceiro_out', $rows);
});

Router::post('parceiros', function () {
    permitir('cad-parceiros');
    $f = parceiro_fields(null);
    $foto = save_upload('foto', 'parceiros', EXT_IMAGEM, 8 * 1024 * 1024);
    $contrato = save_upload('contrato', 'parceiros', EXT_CONTRATO);
    $f += ['foto_path' => $foto['path'] ?? null, 'foto_nome' => $foto['nome'] ?? null,
           'contrato_path' => $contrato['path'] ?? null, 'contrato_nome' => $contrato['nome'] ?? null];
    $c = array_keys($f);
    $id = insert_id('INSERT INTO parceiros (`' . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($f));
    log_action('create', 'parceiros', $id, "{$f['nome']} ({$f['cnpj']})");
    return ['id' => $id];
});

$atualizarParceiro = function ($id) {
    permitir('cad-parceiros');
    $id = (int) $id;
    $atual = q1('SELECT * FROM parceiros WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Parceiro não encontrado');
    $f = parceiro_fields($id);
    $f['foto_path'] = $atual['foto_path']; $f['foto_nome'] = $atual['foto_nome'];
    $f['contrato_path'] = $atual['contrato_path']; $f['contrato_nome'] = $atual['contrato_nome'];
    if ($foto = save_upload('foto', 'parceiros', EXT_IMAGEM, 8 * 1024 * 1024)) {
        delete_upload($atual['foto_path']); $f['foto_path'] = $foto['path']; $f['foto_nome'] = $foto['nome'];
    } elseif (b('remover_foto') === '1') {
        delete_upload($atual['foto_path']); $f['foto_path'] = $f['foto_nome'] = null;
    }
    if ($ct = save_upload('contrato', 'parceiros', EXT_CONTRATO)) {
        delete_upload($atual['contrato_path']); $f['contrato_path'] = $ct['path']; $f['contrato_nome'] = $ct['nome'];
    } elseif (b('remover_contrato') === '1') {
        delete_upload($atual['contrato_path']); $f['contrato_path'] = $f['contrato_nome'] = null;
    }
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($f)));
    exec_sql("UPDATE parceiros SET $set WHERE id=?", [...array_values($f), $id]);
    log_action('update', 'parceiros', $id, "{$f['nome']} ({$f['cnpj']})");
    return ['ok' => true];
};
Router::post('parceiros/:id', $atualizarParceiro);
Router::put('parceiros/:id', $atualizarParceiro);

Router::patch('parceiros/:id/situacao', function ($id) {
    permitir('cad-parceiros');
    $id = (int) $id;
    $row = q1('SELECT nome FROM parceiros WHERE id=?', [$id]);
    if (!$row) fail(404, 'Parceiro não encontrado');
    $ativo = b('ativo') ? 1 : 0;
    exec_sql('UPDATE parceiros SET ativo=? WHERE id=?', [$ativo, $id]);
    log_action('update', 'parceiros', $id, $row['nome'] . ($ativo ? ' — ativado' : ' — desativado'));
    return ['ok' => true];
});

Router::delete('parceiros/:id', function ($id) {
    permitir('cad-parceiros');
    $id = (int) $id;
    $row = q1('SELECT * FROM parceiros WHERE id=?', [$id]);
    if (!$row) fail(404, 'Parceiro não encontrado');
    exec_sql('DELETE FROM parceiros WHERE id=?', [$id]);
    delete_upload($row['foto_path']); delete_upload($row['contrato_path']);
    // Contratos extras do parceiro (aba Documentos) saem junto.
    foreach (q("SELECT id, arquivo_path FROM documentos WHERE parceiro_id=?", [$id]) as $d) delete_upload($d['arquivo_path']);
    exec_sql('DELETE FROM documentos WHERE parceiro_id=?', [$id]);
    log_action('delete', 'parceiros', $id, $row['nome']);
    return ['ok' => true];
});

// ── DOCUMENTOS (contratos de parceiro, notas fiscais, contratos de fornecedor) ─
const TIPOS_DOCUMENTO = ['contrato_parceiro', 'nota_fiscal', 'contrato_fornecedor'];

/** Contrato de parceiro também pode ser gerido de dentro da tela de Parceiros. */
function permitir_documento(?string $tipo): void {
    if ($tipo === 'contrato_parceiro') permitir('import', 'cad-parceiros');
    elseif ($tipo === 'contrato_fornecedor') permitir('import', 'cad-fornecedores');
    else permitir('import');
}

Router::get('documentos', function () {
    $tipo = qs('tipo');
    if ($tipo && !in_array($tipo, TIPOS_DOCUMENTO, true)) fail(400, 'Tipo inválido');
    permitir_documento($tipo);
    $sql = 'SELECT d.*, p.nome AS parceiro_nome, f.nome AS fornecedor_nome FROM documentos d
            LEFT JOIN parceiros p ON p.id=d.parceiro_id LEFT JOIN fornecedores f ON f.id=d.fornecedor_id WHERE 1=1';
    $p = [];
    if ($tipo) { $sql .= ' AND d.tipo=?'; $p[] = $tipo; }
    if ($v = qs('parceiro_id')) { $sql .= ' AND d.parceiro_id=?'; $p[] = (int) $v; }
    if ($v = qs('fornecedor_id')) { $sql .= ' AND d.fornecedor_id=?'; $p[] = (int) $v; }
    $sql .= ' ORDER BY d.created_at DESC, d.id DESC';
    return array_map(function ($d) { $d['arquivo_path'] = public_path($d['arquivo_path']); $d['id'] = (int) $d['id']; return $d; }, q($sql, $p));
});

Router::post('documentos', function () {
    $tipo = b('tipo');
    if (!in_array($tipo, TIPOS_DOCUMENTO, true)) fail(400, 'Tipo inválido');
    permitir_documento($tipo);
    $parceiroId = b('parceiro_id') ? (int) b('parceiro_id') : null;
    $fornecedorId = b('fornecedor_id') ? (int) b('fornecedor_id') : null;
    if ($tipo === 'contrato_parceiro' && (!$parceiroId || !q1('SELECT id FROM parceiros WHERE id=?', [$parceiroId]))) fail(400, 'Selecione o parceiro');
    if ($tipo === 'contrato_fornecedor' && (!$fornecedorId || !q1('SELECT id FROM fornecedores WHERE id=?', [$fornecedorId]))) fail(400, 'Selecione o fornecedor');
    $arq = save_upload('arquivo', 'documentos', EXT_DOCUMENTO);
    if (!$arq) fail(400, 'Arquivo não enviado');
    $id = insert_id('INSERT INTO documentos (tipo, parceiro_id, fornecedor_id, descricao, arquivo_path, arquivo_nome, user_id) VALUES (?,?,?,?,?,?,?)',
        [$tipo, $parceiroId, $fornecedorId, ($d = str_or_null(b('descricao'))) ? mb_substr($d, 0, 255) : null, $arq['path'], $arq['nome'], current_user()['id']]);
    log_action('create', 'documentos', $id, "$tipo: {$arq['nome']}");
    return ['id' => $id];
});

Router::delete('documentos/:id', function ($id) {
    $id = (int) $id;
    $row = q1('SELECT * FROM documentos WHERE id=?', [$id]);
    if (!$row) fail(404, 'Documento não encontrado');
    permitir_documento($row['tipo']);
    exec_sql('DELETE FROM documentos WHERE id=?', [$id]);
    delete_upload($row['arquivo_path']);
    log_action('delete', 'documentos', $id, $row['arquivo_nome']);
    return ['ok' => true];
});

// ── CONTRATO PRINCIPAL (anexar/substituir/remover sem reenviar o cadastro) ────
// Vale pra parceiros e fornecedores — usado pela janela "Contrato" do menu da linha.
function contrato_routes(string $tabela, string $pagina, string $pasta, string $label): void {
    Router::post("$tabela/:id/contrato", function ($id) use ($tabela, $pagina, $pasta, $label) {
        permitir($pagina);
        $id = (int) $id;
        $row = q1("SELECT id, nome, contrato_path FROM `$tabela` WHERE id=?", [$id]);
        if (!$row) fail(404, "$label não encontrado");
        $ct = save_upload('contrato', $pasta, EXT_CONTRATO);
        if (!$ct) fail(400, 'Arquivo não enviado');
        delete_upload($row['contrato_path']);
        exec_sql("UPDATE `$tabela` SET contrato_path=?, contrato_nome=? WHERE id=?", [$ct['path'], $ct['nome'], $id]);
        log_action('update', $tabela, $id, "{$row['nome']} — contrato anexado: {$ct['nome']}");
        return ['contrato_path' => public_path($ct['path']), 'contrato_nome' => $ct['nome']];
    });
    Router::delete("$tabela/:id/contrato", function ($id) use ($tabela, $pagina, $label) {
        permitir($pagina);
        $id = (int) $id;
        $row = q1("SELECT id, nome, contrato_path FROM `$tabela` WHERE id=?", [$id]);
        if (!$row) fail(404, "$label não encontrado");
        delete_upload($row['contrato_path']);
        exec_sql("UPDATE `$tabela` SET contrato_path=NULL, contrato_nome=NULL WHERE id=?", [$id]);
        log_action('update', $tabela, $id, "{$row['nome']} — contrato removido");
        return ['ok' => true];
    });
}
contrato_routes('parceiros', 'cad-parceiros', 'parceiros', 'Parceiro');
contrato_routes('fornecedores', 'cad-fornecedores', 'fornecedores', 'Fornecedor');
