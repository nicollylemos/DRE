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

// clientes, caixas e formas de pagamento: rotas próprias logo abaixo.
// fornecedores: rotas próprias em fornecedores.php (multipart, contrato, situação)
crud_routes('contas_receita', ['grupo', 'conta'], ['grupo'], 'cad-contas');
crud_routes('contas_despesa', ['grupo', 'conta'], ['grupo'], 'cad-contas');
crud_routes('centros_custo', ['nome'], ['nome'], null);

/** Texto curto do que mudou entre o registro antigo e o novo, pro log. */
function diff_log(array $antes, array $depois, array $rotulos): string {
    $m = [];
    foreach ($rotulos as $c => $label) {
        $a = $antes[$c] ?? null; $d = $depois[$c] ?? null;
        if (is_numeric($a) && is_numeric($d) ? (float) $a == (float) $d : (string) $a === (string) $d) continue;
        $m[] = "$label: " . (($a === null || $a === '') ? '∅' : $a) . ' → ' . (($d === null || $d === '') ? '∅' : $d);
    }
    return implode('; ', $m);
}
function nome_e_mudancas(string $nome, string $diff): string { return $diff ? "$nome — $diff" : $nome; }

// ── CLIENTES ──────────────────────────────────────────────────────────────────
const CLIENTE_ROTULOS = ['nome' => 'nome', 'razao_social' => 'razão social', 'cnpj_cpf' => 'CPF/CNPJ', 'endereco' => 'endereço',
    'estado' => 'estado', 'cidade' => 'cidade', 'telefone' => 'telefone', 'email' => 'e-mail', 'observacoes' => 'observações'];

function cliente_fields(?int $id): array {
    $nome = trim((string) b('nome', ''));
    if ($nome === '') fail(400, 'Campo obrigatório: Nome');
    $doc = trim((string) b('cnpj_cpf', ''));
    if ($doc !== '') {
        if (!doc_valido($doc)) fail(400, 'CPF/CNPJ inválido — confira os números');
        $doc = fmt_doc($doc);
        $dup = q1('SELECT nome FROM clientes WHERE cnpj_cpf=?' . ($id ? ' AND id<>?' : ''), $id ? [$doc, $id] : [$doc]);
        if ($dup) fail(400, "CPF/CNPJ já cadastrado para o cliente \"{$dup['nome']}\"");
    }
    $email = str_or_null(b('email'));
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(400, 'E-mail inválido');
    $estado = str_or_null(b('estado'));
    return [
        'nome' => mb_substr($nome, 0, 255), 'razao_social' => ($r = str_or_null(b('razao_social'))) ? mb_substr($r, 0, 255) : null,
        'cnpj_cpf' => $doc ?: null, 'endereco' => ($e = str_or_null(b('endereco'))) ? mb_substr($e, 0, 255) : null,
        'estado' => $estado ? mb_strtoupper(mb_substr($estado, 0, 50)) : null, 'cidade' => ($c = str_or_null(b('cidade'))) ? mb_substr($c, 0, 100) : null,
        'telefone' => ($t = str_or_null(b('telefone'))) ? mb_substr($t, 0, 30) : null, 'email' => $email,
        'observacoes' => str_or_null(b('observacoes')),
    ];
}

Router::get('clientes', function () {
    permitir('cad-clientes', 'lancamentos');
    // Quantos lançamentos e quanto já recebeu de cada cliente (pelo nome, que é o que o lançamento guarda).
    $rows = q("SELECT c.*, COUNT(t.id) AS qtd_lanc, COALESCE(SUM(CASE WHEN t.tipo='income' THEN t.valor ELSE 0 END),0) AS total_receitas
               FROM clientes c LEFT JOIN transactions t ON t.cliente_fornecedor = c.nome
               GROUP BY c.id ORDER BY c.nome");
    return array_map(fn($r) => ['id' => (int) $r['id'], 'qtd_lanc' => (int) $r['qtd_lanc'], 'total_receitas' => (float) $r['total_receitas']] + $r, $rows);
});

Router::post('clientes', function () {
    permitir('cad-clientes');
    $f = cliente_fields(null);
    $c = array_keys($f);
    $id = insert_id('INSERT INTO clientes (`' . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($f));
    log_action('create', 'clientes', $id, $f['nome'] . ($f['cnpj_cpf'] ? " ({$f['cnpj_cpf']})" : ''));
    return ['id' => $id];
});

Router::put('clientes/:id', function ($id) {
    permitir('cad-clientes');
    $id = (int) $id;
    $atual = q1('SELECT * FROM clientes WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Cliente não encontrado');
    $f = cliente_fields($id);
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($f)));
    exec_sql("UPDATE clientes SET $set WHERE id=?", [...array_values($f), $id]);
    // Lançamentos guardam o nome do cliente — mantém sincronizado ao renomear.
    if ($f['nome'] !== $atual['nome']) exec_sql("UPDATE transactions SET cliente_fornecedor=? WHERE cliente_fornecedor=? AND tipo='income'", [$f['nome'], $atual['nome']]);
    log_action('update', 'clientes', $id, nome_e_mudancas($f['nome'], diff_log($atual, $f, CLIENTE_ROTULOS)));
    return ['ok' => true];
});

Router::delete('clientes/:id', function ($id) {
    permitir('cad-clientes');
    $id = (int) $id;
    $row = q1('SELECT nome, cnpj_cpf FROM clientes WHERE id=?', [$id]);
    if (!$row) fail(404, 'Cliente não encontrado');
    exec_sql('DELETE FROM clientes WHERE id=?', [$id]);
    log_action('delete', 'clientes', $id, $row['nome'] . ($row['cnpj_cpf'] ? " ({$row['cnpj_cpf']})" : ''));
    return ['ok' => true];
});

// ── CAIXAS / BANCOS ───────────────────────────────────────────────────────────
const CAIXA_TIPOS = ['banco', 'caixa', 'investimento'];
const CAIXA_ROTULOS = ['nome' => 'nome', 'tipo' => 'tipo', 'banco' => 'banco', 'agencia' => 'agência', 'conta' => 'conta',
    'saldo_inicial' => 'saldo inicial', 'observacoes' => 'observações'];

function caixa_fields(?int $id): array {
    $nome = trim((string) b('nome', ''));
    if ($nome === '') fail(400, 'Campo obrigatório: Nome');
    if (q1('SELECT id FROM caixas WHERE nome=?' . ($id ? ' AND id<>?' : ''), $id ? [$nome, $id] : [$nome])) fail(400, 'Já existe um caixa/conta com esse nome');
    $tipo = in_array(b('tipo'), CAIXA_TIPOS, true) ? b('tipo') : 'banco';
    $cut = fn($k, $n) => ($v = str_or_null(b($k))) ? mb_substr($v, 0, $n) : null;
    return [
        'nome' => mb_substr($nome, 0, 100), 'tipo' => $tipo,
        'banco' => $tipo === 'caixa' ? null : $cut('banco', 100),
        'agencia' => $tipo === 'caixa' ? null : $cut('agencia', 30),
        'conta' => $tipo === 'caixa' ? null : $cut('conta', 30),
        'saldo_inicial' => round(num(b('saldo_inicial', 0)), 2),
        'observacoes' => $cut('observacoes', 255),
    ];
}

/**
 * Saldo de cada caixa até hoje: saldo inicial + receitas quitadas − despesas quitadas
 * (pela data do pagamento, mesmo critério do Fluxo de Caixa) ± transferências.
 * Também devolve o que está em aberto (a receber / a pagar) apontado pra ele.
 */
function caixas_com_saldo(): array {
    $mov = [];
    foreach (q("SELECT caixa_nome n,
                  SUM(CASE WHEN status='quitado' AND tipo='income'  AND COALESCE(data_pagamento,data)<=CURDATE() THEN valor ELSE 0 END) rec,
                  SUM(CASE WHEN status='quitado' AND tipo='expense' AND COALESCE(data_pagamento,data)<=CURDATE() THEN valor ELSE 0 END) desp,
                  SUM(CASE WHEN status='em_aberto' AND tipo='income'  THEN valor ELSE 0 END) a_receber,
                  SUM(CASE WHEN status='em_aberto' AND tipo='expense' THEN valor ELSE 0 END) a_pagar,
                  COUNT(*) qtd
                FROM transactions GROUP BY caixa_nome") as $r) $mov[(string) $r['n']] = $r;
    $trIn = []; $trOut = [];
    foreach (q('SELECT destino_nome n, SUM(valor) v FROM transfers WHERE data<=CURDATE() GROUP BY destino_nome') as $r) $trIn[(string) $r['n']] = (float) $r['v'];
    foreach (q('SELECT origem_nome n, SUM(valor) v FROM transfers WHERE data<=CURDATE() GROUP BY origem_nome') as $r) $trOut[(string) $r['n']] = (float) $r['v'];
    return array_map(function ($c) use ($mov, $trIn, $trOut) {
        $m = $mov[$c['nome']] ?? ['rec' => 0, 'desp' => 0, 'a_receber' => 0, 'a_pagar' => 0, 'qtd' => 0];
        $si = (float) $c['saldo_inicial'];
        $entradas = (float) $m['rec'] + ($trIn[$c['nome']] ?? 0);
        $saidas = (float) $m['desp'] + ($trOut[$c['nome']] ?? 0);
        return ['id' => (int) $c['id'], 'saldo_inicial' => $si, 'entradas' => $entradas, 'saidas' => $saidas,
            'saldo_atual' => round($si + $entradas - $saidas, 2),
            'a_receber' => (float) $m['a_receber'], 'a_pagar' => (float) $m['a_pagar'], 'qtd_lanc' => (int) $m['qtd']] + $c;
    }, q('SELECT * FROM caixas ORDER BY nome'));
}

Router::get('caixas', function () {
    permitir('cad-caixas');
    // Lançamentos quitados sem caixa (ou com caixa que não existe mais) não entram no
    // saldo de nenhuma conta, mas entram no Fluxo de Caixa "Todos" — mostra a diferença.
    $sem = (float) q1("SELECT COALESCE(SUM(CASE WHEN tipo='income' THEN valor ELSE -valor END),0) v FROM transactions
                       WHERE status='quitado' AND COALESCE(data_pagamento,data)<=CURDATE()
                         AND (caixa_nome IS NULL OR caixa_nome='' OR caixa_nome NOT IN (SELECT nome FROM caixas))")['v'];
    $qtd = (int) q1("SELECT COUNT(*) n FROM transactions WHERE caixa_nome IS NULL OR caixa_nome='' OR caixa_nome NOT IN (SELECT nome FROM caixas)")['n'];
    return ['caixas' => caixas_com_saldo(), 'sem_caixa' => ['saldo' => $sem, 'qtd' => $qtd]];
});

Router::post('caixas', function () {
    permitir('cad-caixas');
    $f = caixa_fields(null);
    $c = array_keys($f);
    $id = insert_id('INSERT INTO caixas (`' . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($f));
    log_action('create', 'caixas', $id, "{$f['nome']} — saldo inicial R\$" . number_format($f['saldo_inicial'], 2, ',', '.'));
    return ['id' => $id];
});

Router::put('caixas/:id', function ($id) {
    permitir('cad-caixas');
    $id = (int) $id;
    $atual = q1('SELECT * FROM caixas WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Caixa não encontrado');
    $f = caixa_fields($id);
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($f)));
    exec_sql("UPDATE caixas SET $set WHERE id=?", [...array_values($f), $id]);
    // Lançamentos e transferências guardam o nome — renomeia junto pra não perder o saldo.
    if ($f['nome'] !== $atual['nome']) {
        exec_sql('UPDATE transactions SET caixa_nome=? WHERE caixa_nome=?', [$f['nome'], $atual['nome']]);
        exec_sql('UPDATE transfers SET origem_nome=? WHERE origem_nome=?', [$f['nome'], $atual['nome']]);
        exec_sql('UPDATE transfers SET destino_nome=? WHERE destino_nome=?', [$f['nome'], $atual['nome']]);
    }
    log_action('update', 'caixas', $id, nome_e_mudancas($f['nome'], diff_log($atual, $f, CAIXA_ROTULOS)));
    return ['ok' => true];
});

Router::delete('caixas/:id', function ($id) {
    permitir('cad-caixas');
    $id = (int) $id;
    $row = q1('SELECT nome FROM caixas WHERE id=?', [$id]);
    if (!$row) fail(404, 'Caixa não encontrado');
    $uso = (int) q1('SELECT COUNT(*) n FROM transactions WHERE caixa_nome=?', [$row['nome']])['n']
         + (int) q1('SELECT COUNT(*) n FROM transfers WHERE origem_nome=? OR destino_nome=?', [$row['nome'], $row['nome']])['n'];
    if ($uso) fail(400, "\"{$row['nome']}\" tem $uso movimentação(ões). Apagar faria o saldo sumir dos relatórios — renomeie em vez de apagar.");
    exec_sql('DELETE FROM caixas WHERE id=?', [$id]);
    log_action('delete', 'caixas', $id, $row['nome']);
    return ['ok' => true];
});

// ── FORMAS DE PAGAMENTO ───────────────────────────────────────────────────────
const FORMA_TIPOS = ['credito', 'debito', 'pix', 'boleto', 'dinheiro'];
const FORMA_ROTULOS = ['nome' => 'nome', 'tipo' => 'tipo', 'banco' => 'banco', 'agencia' => 'agência', 'parcelas' => 'parcelas',
    'dias_recebimento' => 'dias p/ recebimento', 'taxa_intermediacao' => 'taxa intermediação %', 'taxa_parcelamento' => 'taxa parcelamento %',
    'tarifa_fixa' => 'tarifa fixa R$'];

function forma_fields(?int $id): array {
    $nome = trim((string) b('nome', ''));
    if ($nome === '') fail(400, 'Campo obrigatório: Nome');
    if (q1('SELECT id FROM formas_pagamento WHERE nome=?' . ($id ? ' AND id<>?' : ''), $id ? [$nome, $id] : [$nome])) fail(400, 'Já existe uma forma de pagamento com esse nome');
    $tipo = b('tipo');
    if (!in_array($tipo, FORMA_TIPOS, true)) fail(400, 'Escolha o tipo da forma de pagamento');
    $pct = fn($k) => min(100, max(0, round(num(b($k, 0)), 4)));
    $cut = fn($k, $n) => ($v = str_or_null(b($k))) ? mb_substr($v, 0, $n) : null;
    return [
        'nome' => mb_substr($nome, 0, 100), 'tipo' => $tipo,
        'banco' => $tipo === 'dinheiro' ? null : $cut('banco', 100),
        'agencia' => $tipo === 'dinheiro' ? null : $cut('agencia', 30),
        // Parcelas só fazem sentido no crédito.
        'parcelas' => $tipo === 'credito' ? min(48, max(1, (int) num(b('parcelas', 1)))) : 1,
        'dias_recebimento' => min(365, max(0, (int) num(b('dias_recebimento', 0)))),
        'taxa_intermediacao' => $pct('taxa_intermediacao'),
        'taxa_parcelamento' => $tipo === 'credito' ? $pct('taxa_parcelamento') : 0,
        'tarifa_fixa' => max(0, round(num(b('tarifa_fixa', 0)), 2)),
    ];
}
function forma_out(array $r): array {
    foreach (['taxa_intermediacao', 'taxa_parcelamento', 'tarifa_fixa'] as $k) $r[$k] = (float) $r[$k];
    foreach (['id', 'parcelas', 'dias_recebimento'] as $k) $r[$k] = (int) $r[$k];
    if (isset($r['qtd_lanc'])) $r['qtd_lanc'] = (int) $r['qtd_lanc'];
    return $r;
}

Router::get('formas_pagamento', function () {
    permitir('cad-formas');
    return array_map('forma_out', q('SELECT f.*, (SELECT COUNT(*) FROM transactions t WHERE t.forma_pagamento_nome=f.nome) qtd_lanc FROM formas_pagamento f ORDER BY f.nome'));
});

Router::post('formas_pagamento', function () {
    permitir('cad-formas');
    $f = forma_fields(null);
    $c = array_keys($f);
    $id = insert_id('INSERT INTO formas_pagamento (`' . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($f));
    log_action('create', 'formas_pagamento', $id, "{$f['nome']} ({$f['tipo']})");
    return ['id' => $id];
});

Router::put('formas_pagamento/:id', function ($id) {
    permitir('cad-formas');
    $id = (int) $id;
    $atual = q1('SELECT * FROM formas_pagamento WHERE id=?', [$id]);
    if (!$atual) fail(404, 'Forma de pagamento não encontrada');
    $f = forma_fields($id);
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($f)));
    exec_sql("UPDATE formas_pagamento SET $set WHERE id=?", [...array_values($f), $id]);
    if ($f['nome'] !== $atual['nome']) exec_sql('UPDATE transactions SET forma_pagamento_nome=? WHERE forma_pagamento_nome=?', [$f['nome'], $atual['nome']]);
    log_action('update', 'formas_pagamento', $id, nome_e_mudancas($f['nome'], diff_log($atual, $f, FORMA_ROTULOS)));
    return ['ok' => true];
});

Router::delete('formas_pagamento/:id', function ($id) {
    permitir('cad-formas');
    $id = (int) $id;
    $row = q1('SELECT nome FROM formas_pagamento WHERE id=?', [$id]);
    if (!$row) fail(404, 'Forma de pagamento não encontrada');
    $uso = (int) q1('SELECT COUNT(*) n FROM transactions WHERE forma_pagamento_nome=?', [$row['nome']])['n'];
    if ($uso) fail(400, "\"{$row['nome']}\" está em $uso lançamento(s). Apagar tiraria a taxa desses lançamentos dos relatórios — renomeie ou ajuste em vez de apagar.");
    exec_sql('DELETE FROM formas_pagamento WHERE id=?', [$id]);
    log_action('delete', 'formas_pagamento', $id, $row['nome']);
    return ['ok' => true];
});

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
