<?php
// ── TRANSACTIONS ──────────────────────────────────────────────────────────────

function calc_taxa_mdr(?string $formaNome, float $valor): float {
    if (!$formaNome) return 0.0;
    $fp = q1('SELECT taxa_intermediacao, tarifa_fixa FROM formas_pagamento WHERE nome=?', [$formaNome]);
    if (!$fp) return 0.0;
    return round(num($fp['taxa_intermediacao']) / 100 * $valor + num($fp['tarifa_fixa']), 2);
}

/** Normaliza a linha do banco pro formato que o front usa (nomes antigos + novos). */
function normalize_tx(array $t): array {
    $t['id'] = (int) $t['id'];
    $t['valor'] = (float) $t['valor'];
    $t['taxa_mdr'] = (float) ($t['taxa_mdr'] ?? 0);
    $t['valor_liquido'] = $t['valor_liquido'] !== null ? (float) $t['valor_liquido'] : null;
    $t['categoria_id'] = $t['categoria_id'] !== null ? (int) $t['categoria_id'] : null;
    return $t + [
        'type' => $t['tipo'],
        'date' => substr((string) $t['data'], 0, 10),
        'amount' => (float) $t['valor'],
        'description' => $t['descricao'],
        'grupo_contas' => $t['grupo'],
        'conta' => $t['conta_nome'],
        'categoria' => $t['categoria_nome'],
        'forma_pagamento' => $t['forma_pagamento_nome'],
        'caixa_banco' => $t['caixa_nome'],
        'centro_custo' => $t['centro_custo_nome'],
    ];
}

/** Resolve a categoria escolhida (id) e valida se ela serve pro tipo do lançamento. */
function resolve_categoria($catId, string $tipo): array {
    if ($catId === null || $catId === '' || (int) $catId === 0) return [null, null];
    $c = q1('SELECT id, nome, tipo FROM categorias WHERE id=?', [(int) $catId]);
    if (!$c) fail(400, 'Categoria não encontrada');
    if ($c['tipo'] !== 'ambos' && $c['tipo'] !== $tipo) fail(400, 'Categoria "' . $c['nome'] . '" não é de ' . ($tipo === 'income' ? 'receita' : 'despesa'));
    return [(int) $c['id'], $c['nome']];
}

/** Monta os campos comuns do INSERT/UPDATE a partir do corpo da requisição. */
function tx_fields(): array {
    $tipo = bf(['type', 'tipo']);
    $data = bf(['date', 'data']);
    if (!$data) fail(400, 'Campo obrigatório: date');
    if (!in_array($tipo, ['income', 'expense'], true)) fail(400, 'Campo obrigatório: type');
    if (bf(['amount', 'valor'], null) === null) fail(400, 'Campo obrigatório: amount');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data)) fail(400, 'Data inválida');
    $valor = round(num(bf(['amount', 'valor'], 0)), 2);
    if ($valor < 0) fail(400, 'Valor não pode ser negativo');
    $forma = (string) bf(['forma_pagamento', 'forma_pagamento_nome']);
    // MDR só faz sentido em RECEITA (auditoria-dre.md, item 5).
    $taxa = $tipo === 'income' ? calc_taxa_mdr($forma, $valor) : 0.0;
    [$catId, $catNome] = resolve_categoria(b('categoria_id'), $tipo);
    $date = fn($k) => (($v = b($k)) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)) ? $v : null;
    return [
        'tipo' => $tipo, 'data' => $data,
        'descricao' => mb_substr((string) bf(['description', 'descricao']), 0, 255),
        'valor' => $valor,
        'conta_nome' => (string) bf(['conta', 'conta_nome']),
        'grupo' => (string) bf(['grupo_contas', 'grupo']),
        'categoria_id' => $catId, 'categoria_nome' => $catNome,
        'cliente_fornecedor' => (string) b('cliente_fornecedor', ''),
        'forma_pagamento_nome' => $forma,
        'caixa_nome' => (string) bf(['caixa_banco', 'caixa_nome']),
        'centro_custo_nome' => (string) bf(['centro_custo', 'centro_custo_nome']),
        // status só existe como 'em_aberto' ou 'quitado' (auditoria-dre.md, item 6)
        'status' => b('status') === 'em_aberto' ? 'em_aberto' : 'quitado',
        'data_vencimento' => $date('data_vencimento'),
        'data_pagamento' => $date('data_pagamento'),
        'taxa_mdr' => $taxa, 'valor_liquido' => round($valor - $taxa, 2),
        'observacoes' => (string) b('observacoes', ''),
    ];
}

Router::get('transactions', function () {
    permitir('lancamentos');
    $sql = 'SELECT * FROM transactions WHERE 1=1'; $p = [];
    if ($v = qs('year'))            { $sql .= ' AND YEAR(data)=?';            $p[] = (int) $v; }
    if ($v = qs('month'))           { $sql .= ' AND MONTH(data)=?';           $p[] = (int) $v; }
    if ($v = qs('type'))            { $sql .= ' AND tipo=?';                  $p[] = $v; }
    if ($v = qs('status'))          { $sql .= ' AND status=?';                $p[] = $v; }
    if ($v = qs('categoria_id'))    { $sql .= ' AND categoria_id=?';          $p[] = (int) $v; }
    if ($v = qs('centro_custo'))    { $sql .= ' AND centro_custo_nome=?';     $p[] = $v; }
    if ($v = qs('forma_pagamento')) { $sql .= ' AND forma_pagamento_nome=?';  $p[] = $v; }
    if ($v = qs('caixa_banco'))     { $sql .= ' AND caixa_nome=?';            $p[] = $v; }
    $sql .= ' ORDER BY data DESC, id DESC';
    return array_map('normalize_tx', q($sql, $p));
});

// Alertas do dashboard (títulos em aberto) — liberado pra quem vê Dashboard OU Lançamentos.
Router::get('transactions/em-aberto', function () {
    permitir('dashboard', 'lancamentos');
    return array_map('normalize_tx', q("SELECT * FROM transactions WHERE status='em_aberto' AND data_vencimento IS NOT NULL ORDER BY data_vencimento"));
});

Router::post('transactions', function () {
    permitir('lancamentos');
    $f = tx_fields();
    $f['user_id'] = current_user()['id'];
    $cols = array_keys($f);
    $id = insert_id('INSERT INTO transactions (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($f));
    log_action('create', 'transactions', $id, "{$f['tipo']} - {$f['descricao']} - R\${$f['valor']}");
    return ['id' => $id];
});

Router::put('transactions/:id', function ($id) {
    permitir('lancamentos');
    $id = (int) $id;
    $atual = q1('SELECT * FROM transactions WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Lançamento não encontrado');
    $f = tx_fields();
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($f)));
    exec_sql("UPDATE transactions SET $set WHERE id=?", [...array_values($f), $id]);
    // Log com o que mudou (valor, datas, situação...), não só o estado final.
    $diff = diff_log($atual, $f, ['tipo' => 'tipo', 'data' => 'data', 'descricao' => 'descrição', 'valor' => 'valor', 'categoria_nome' => 'categoria',
        'cliente_fornecedor' => 'cliente/fornecedor', 'forma_pagamento_nome' => 'forma', 'caixa_nome' => 'caixa', 'status' => 'situação',
        'data_vencimento' => 'vencimento', 'data_pagamento' => 'pagamento']);
    log_action('update', 'transactions', $id, "{$f['tipo']} - {$f['descricao']} - R\${$f['valor']}" . ($diff ? " — $diff" : ''));
    return ['ok' => true];
});

// Atualização parcial de status/baixa (RF06)
Router::patch('transactions/:id', function ($id) {
    permitir('lancamentos');
    $id = (int) $id; $fields = []; $vals = [];
    $body = body();
    if (array_key_exists('status', $body))         { $fields[] = 'status=?';         $vals[] = $body['status'] === 'em_aberto' ? 'em_aberto' : 'quitado'; }
    if (array_key_exists('data_pagamento', $body)) { $fields[] = 'data_pagamento=?'; $vals[] = $body['data_pagamento'] ?: null; }
    if (array_key_exists('observacoes', $body))    { $fields[] = 'observacoes=?';    $vals[] = (string) $body['observacoes']; }
    if (!$fields) fail(400, 'Nenhum campo para atualizar');
    $vals[] = $id;
    exec_sql('UPDATE transactions SET ' . implode(',', $fields) . ' WHERE id=?', $vals);
    log_action(($body['status'] ?? '') === 'quitado' ? 'baixa' : 'update_status', 'transactions', $id, $body['status'] ?? null);
    return ['ok' => true];
});

Router::delete('transactions/:id', function ($id) {
    permitir('lancamentos');
    $id = (int) $id;
    // Busca antes de apagar pro log guardar o que era (auditoria-dre.md, item 7).
    $row = q1('SELECT tipo, valor, descricao FROM transactions WHERE id=?', [$id]);
    exec_sql('DELETE FROM transactions WHERE id=?', [$id]);
    log_action('delete', 'transactions', $id, $row ? "{$row['tipo']} - {$row['descricao']} - R\${$row['valor']}" : null);
    return ['ok' => true];
});

// ── TRANSFERS ─────────────────────────────────────────────────────────────────
Router::get('transfers', function () {
    permitir('lancamentos');
    $sql = 'SELECT * FROM transfers WHERE 1=1'; $p = [];
    if ($v = qs('year'))  { $sql .= ' AND YEAR(data)=?';  $p[] = (int) $v; }
    if ($v = qs('month')) { $sql .= ' AND MONTH(data)=?'; $p[] = (int) $v; }
    $sql .= ' ORDER BY data DESC, id DESC';
    return array_map(fn($t) => $t + [
        'date' => substr((string) $t['data'], 0, 10), 'amount' => (float) $t['valor'],
        'caixa_saida' => $t['origem_nome'], 'caixa_entrada' => $t['destino_nome'],
    ], q($sql, $p));
});

Router::post('transfers', function () {
    permitir('lancamentos');
    $data = bf(['date', 'data']);
    if (!$data) fail(400, 'Campo obrigatório: date');
    $origem = (string) bf(['caixa_saida', 'origem_nome']); $destino = (string) bf(['caixa_entrada', 'destino_nome']);
    if ($origem !== '' && $origem === $destino) fail(400, 'Caixa de saída e entrada não podem ser iguais');
    $valor = round(num(bf(['amount', 'valor'], 0)), 2);
    $id = insert_id('INSERT INTO transfers (data, descricao, valor, origem_nome, destino_nome, observacoes, user_id) VALUES (?,?,?,?,?,?,?)',
        [$data, (string) bf(['descricao', 'description']), $valor, $origem, $destino, (string) b('observacoes', ''), current_user()['id']]);
    log_action('create', 'transfers', $id, "$origem → $destino - R\$$valor");
    return ['id' => $id];
});

Router::delete('transfers/:id', function ($id) {
    permitir('lancamentos');
    $id = (int) $id;
    $row = q1('SELECT valor, origem_nome, destino_nome FROM transfers WHERE id=?', [$id]);
    exec_sql('DELETE FROM transfers WHERE id=?', [$id]);
    log_action('delete', 'transfers', $id, $row ? "{$row['origem_nome']} → {$row['destino_nome']} - R\${$row['valor']}" : null);
    return ['ok' => true];
});
