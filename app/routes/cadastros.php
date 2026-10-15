<?php
// ── CADASTROS CRUD GENÉRICO ───────────────────────────────────────────────────
// Diferente do server.js, aqui só entram colunas da lista (antes qualquer chave
// do JSON virava nome de coluna no INSERT/UPDATE).

function crud_routes(string $table, array $cols, array $required, ?string $pagina, array $numericas = [], array $leituraExtra = []): void {
    $guard = function () use ($pagina) { $pagina ? permitir($pagina) : auth(); };
    // Leitura pode ser liberada pra outras telas que só precisam da lista (ex: Documentos lista fornecedores).
    $guardLeitura = function () use ($pagina, $leituraExtra) { $pagina ? permitir($pagina, ...$leituraExtra) : auth(); };
    $pegar = function (bool $parcial) use ($cols, $numericas): array {
        $body = body(); $out = [];
        foreach ($cols as $c) {
            if (!array_key_exists($c, $body)) { if (!$parcial) $out[$c] = in_array($c, $numericas, true) ? 0 : null; continue; }
            $out[$c] = in_array($c, $numericas, true) ? num($body[$c]) : str_or_null($body[$c]);
        }
        return $out;
    };

    Router::get($table, function () use ($guardLeitura, $table, $numericas) {
        $guardLeitura();
        // DECIMAL chega como string do PDO — converte pra número pro front não concatenar.
        return array_map(function ($r) use ($numericas) {
            $r['id'] = (int) $r['id'];
            foreach ($numericas as $c) if (isset($r[$c])) $r[$c] = (float) $r[$c];
            return $r;
        }, q("SELECT * FROM `$table` ORDER BY id"));
    });
    Router::post($table, function () use ($guard, $table, $required, $pegar) {
        $guard();
        $d = $pegar(false);
        foreach ($required as $r) if (empty($d[$r])) fail(400, "Campo obrigatório: $r");
        $c = array_keys($d);
        $id = insert_id("INSERT INTO `$table` (`" . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($d));
        log_action('create', $table, $id, $d['nome'] ?? $d['grupo'] ?? null);
        return ['id' => $id];
    });
    Router::put("$table/:id", function ($id) use ($guard, $table, $required, $pegar) {
        $guard();
        $id = (int) $id;
        $d = $pegar(true);
        foreach ($required as $r) if (array_key_exists($r, $d) && empty($d[$r])) fail(400, "Campo obrigatório: $r");
        if (!$d) return ['ok' => true];
        $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($d)));
        exec_sql("UPDATE `$table` SET $set WHERE id=?", [...array_values($d), $id]);
        log_action('update', $table, $id, $d['nome'] ?? $d['grupo'] ?? null);
        return ['ok' => true];
    });
    Router::delete("$table/:id", function ($id) use ($guard, $table) {
        $guard();
        $id = (int) $id;
        $row = q1("SELECT * FROM `$table` WHERE id=?", [$id]);
        exec_sql("DELETE FROM `$table` WHERE id=?", [$id]);
        log_action('delete', $table, $id, $row['nome'] ?? $row['grupo'] ?? null);
        return ['ok' => true];
    });
}

$pessoa = ['nome', 'cnpj_cpf', 'estado', 'cidade', 'endereco', 'telefone', 'email', 'observacoes'];
crud_routes('clientes', $pessoa, ['nome'], 'cad-clientes');
// fornecedores: rotas próprias em fornecedores.php (multipart, contrato, situação)
crud_routes('contas_receita', ['grupo', 'conta'], ['grupo'], 'cad-contas');
crud_routes('contas_despesa', ['grupo', 'conta'], ['grupo'], 'cad-contas');
crud_routes('centros_custo', ['nome'], ['nome'], null);
crud_routes('caixas', ['nome', 'saldo_inicial'], ['nome'], 'cad-caixas', ['saldo_inicial']);
crud_routes('formas_pagamento',
    ['nome', 'banco', 'agencia', 'parcelas', 'dias_recebimento', 'taxa_intermediacao', 'taxa_parcelamento', 'tarifa_fixa'],
    ['nome'], 'cad-formas', ['parcelas', 'dias_recebimento', 'taxa_intermediacao', 'taxa_parcelamento', 'tarifa_fixa']);

// ── RESUMO DOS CADASTROS (alimenta os selects do lançamento) ─────────────────
Router::get('cadastros', function () {
    auth();
    $col = fn(string $sql, string $c = 'nome') => array_column(q($sql), $c);
    $cr = q('SELECT id, grupo, conta FROM contas_receita ORDER BY id');
    $cd = q('SELECT id, grupo, conta FROM contas_despesa ORDER BY id');
    return [
        'formasPagamento'     => $col('SELECT nome FROM formas_pagamento ORDER BY id'),
        'formasFull'          => array_map(fn($f) => ['nome' => $f['nome'], 'taxa_intermediacao' => (float) $f['taxa_intermediacao'], 'tarifa_fixa' => (float) $f['tarifa_fixa']],
                                     q('SELECT nome, taxa_intermediacao, tarifa_fixa FROM formas_pagamento ORDER BY id')),
        'caixasBancos'        => $col('SELECT nome FROM caixas ORDER BY id'),
        'centrosCusto'        => $col('SELECT nome FROM centros_custo ORDER BY id'),
        'gruposContasReceita' => array_values(array_unique(array_column($cr, 'grupo'))),
        'gruposContasDespesa' => array_values(array_unique(array_column($cd, 'grupo'))),
        'contasReceita'       => $cr,
        'contasDespesa'       => $cd,
        'clientes'            => $col('SELECT nome FROM clientes ORDER BY nome'),
        'fornecedores'        => $col('SELECT nome FROM fornecedores WHERE ativo=1 ORDER BY nome'),
        'categorias'          => array_map(fn($c) => ['id' => (int) $c['id']] + $c, q('SELECT id, nome, tipo, cor FROM categorias WHERE ativo=1 ORDER BY nome')),
    ];
});

// ── CATEGORIAS ────────────────────────────────────────────────────────────────
function categoria_fields(): array {
    $nome = trim((string) b('nome', ''));
    if ($nome === '') fail(400, 'Campo obrigatório: nome');
    $tipo = b('tipo', 'ambos');
    if (!in_array($tipo, ['income', 'expense', 'ambos'], true)) fail(400, 'Tipo inválido');
    $cor = (string) b('cor', '#1F5FBF');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) $cor = '#1F5FBF';
    return [
        'nome' => mb_substr($nome, 0, 150), 'tipo' => $tipo, 'cor' => strtoupper($cor),
        'descricao' => ($d = str_or_null(b('descricao'))) ? mb_substr($d, 0, 255) : null,
        'ativo' => b('ativo', 1) ? 1 : 0,
    ];
}

// Lista com uso no ano (quantos lançamentos e quanto movimentou), pra tela de categorias.
Router::get('categorias', function () {
    permitir('cad-categorias', 'lancamentos');
    $y = (int) qs('year', date('Y'));
    $rows = q("SELECT c.*,
                 COALESCE(SUM(CASE WHEN YEAR(t.data)=? THEN 1 ELSE 0 END),0) AS qtd_ano,
                 COALESCE(SUM(CASE WHEN YEAR(t.data)=? THEN t.valor ELSE 0 END),0) AS total_ano,
                 COUNT(t.id) AS qtd_total
               FROM categorias c LEFT JOIN transactions t ON t.categoria_id = c.id
               GROUP BY c.id ORDER BY c.ativo DESC, c.tipo, c.nome", [$y, $y]);
    return array_map(fn($r) => ['id' => (int) $r['id'], 'ativo' => (int) $r['ativo'], 'qtd_ano' => (int) $r['qtd_ano'],
        'total_ano' => (float) $r['total_ano'], 'qtd_total' => (int) $r['qtd_total']] + $r, $rows);
});

Router::post('categorias', function () {
    permitir('cad-categorias');
    $f = categoria_fields();
    if (q1('SELECT id FROM categorias WHERE nome=? AND tipo=?', [$f['nome'], $f['tipo']])) fail(400, 'Já existe uma categoria com esse nome e tipo');
    $id = insert_id('INSERT INTO categorias (nome, tipo, cor, descricao, ativo) VALUES (?,?,?,?,?)', array_values($f));
    log_action('create', 'categorias', $id, $f['nome']);
    return ['id' => $id];
});

Router::put('categorias/:id', function ($id) {
    permitir('cad-categorias');
    $id = (int) $id;
    $atual = q1('SELECT * FROM categorias WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Categoria não encontrada');
    $f = categoria_fields();
    if (q1('SELECT id FROM categorias WHERE nome=? AND tipo=? AND id<>?', [$f['nome'], $f['tipo'], $id])) fail(400, 'Já existe uma categoria com esse nome e tipo');
    // Não deixa mudar o tipo se isso deixaria lançamentos com categoria incompatível.
    if ($f['tipo'] !== 'ambos' && $f['tipo'] !== $atual['tipo']) {
        $conflito = (int) q1('SELECT COUNT(*) n FROM transactions WHERE categoria_id=? AND tipo<>?', [$id, $f['tipo']])['n'];
        if ($conflito) fail(400, "Existem $conflito lançamento(s) do outro tipo usando essa categoria. Use \"Ambos\" ou troque a categoria deles antes.");
    }
    exec_sql('UPDATE categorias SET nome=?, tipo=?, cor=?, descricao=?, ativo=? WHERE id=?', [...array_values($f), $id]);
    // Lançamentos guardam o nome (igual conta/grupo) — mantém sincronizado ao renomear.
    if ($f['nome'] !== $atual['nome']) exec_sql('UPDATE transactions SET categoria_nome=? WHERE categoria_id=?', [$f['nome'], $id]);
    log_action('update', 'categorias', $id, $f['nome']);
    return ['ok' => true];
});

Router::delete('categorias/:id', function ($id) {
    permitir('cad-categorias');
    $id = (int) $id;
    $row = q1('SELECT nome FROM categorias WHERE id=?', [$id]);
    if (!$row) fail(404, 'Categoria não encontrada');
    $uso = (int) q1('SELECT COUNT(*) n FROM transactions WHERE categoria_id=?', [$id])['n'];
    if ($uso) fail(400, "Categoria usada em $uso lançamento(s). Desative em vez de excluir, pra não perder o histórico.");
    exec_sql('DELETE FROM categorias WHERE id=?', [$id]);
    log_action('delete', 'categorias', $id, $row['nome']);
    return ['ok' => true];
});
