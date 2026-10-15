<?php
/**
 * schema.php — cria/migra as tabelas automaticamente (igual o database.js fazia).
 * Roda só quando SCHEMA_VERSION muda (controle em storage/schema_version), então
 * não pesa nas requisições normais. O mesmo schema está em database.sql pra
 * quem preferir importar manualmente pelo phpMyAdmin.
 */

const SCHEMA_VERSION = 5;

function schema_tables(): array {
    $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(100) NOT NULL UNIQUE,
            nome       VARCHAR(150) NULL,
            password   VARCHAR(255) NOT NULL,
            role       ENUM('admin','user') DEFAULT 'user',
            foto_path  VARCHAR(255) NULL,
            foto_nome  VARCHAR(255) NULL,
            permissoes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $e",
        'clientes' => "CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, cnpj_cpf VARCHAR(30),
            estado VARCHAR(50), cidade VARCHAR(100), endereco VARCHAR(255), telefone VARCHAR(30),
            email VARCHAR(150), observacoes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $e",
        // Fornecedor tem os mesmos dados de parceiro (razão social, responsável,
        // WhatsApp, contrato, situação). cnpj_cpf aceita CNPJ ou CPF.
        'fornecedores' => "CREATE TABLE IF NOT EXISTS fornecedores (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            nome          VARCHAR(255) NOT NULL,
            razao_social  VARCHAR(255) NULL,
            cnpj_cpf      VARCHAR(30),
            estado        VARCHAR(50),
            cidade        VARCHAR(100),
            endereco      VARCHAR(255),
            telefone      VARCHAR(30),
            whatsapp      VARCHAR(30) NULL,
            email         VARCHAR(150),
            responsavel   VARCHAR(150) NULL,
            contrato_path VARCHAR(255) NULL,
            contrato_nome VARCHAR(255) NULL,
            observacoes   TEXT,
            ativo         TINYINT(1) NOT NULL DEFAULT 1,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $e",
        'parceiros' => "CREATE TABLE IF NOT EXISTS parceiros (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            nome          VARCHAR(255) NOT NULL,
            razao_social  VARCHAR(255) NOT NULL,
            cnpj          VARCHAR(30) NOT NULL,
            email         VARCHAR(150),
            endereco      VARCHAR(255),
            comissao      DECIMAL(15,2) DEFAULT 0,
            contato       VARCHAR(150) NOT NULL,
            whatsapp      VARCHAR(30),
            responsavel   VARCHAR(150) NOT NULL,
            foto_path     VARCHAR(255),
            foto_nome     VARCHAR(255),
            contrato_path VARCHAR(255),
            contrato_nome VARCHAR(255),
            observacoes   TEXT NULL,
            ativo         TINYINT(1) NOT NULL DEFAULT 1,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $e",
        // Categorias de lançamento (Aluguel, Marketing, Consultas...). tipo diz
        // em qual lado do lançamento ela aparece: receita, despesa ou ambos.
        'categorias' => "CREATE TABLE IF NOT EXISTS categorias (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            nome       VARCHAR(150) NOT NULL,
            tipo       ENUM('income','expense','ambos') NOT NULL DEFAULT 'ambos',
            cor        VARCHAR(7) DEFAULT '#1F5FBF',
            descricao  VARCHAR(255) NULL,
            ativo      TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_categoria (nome, tipo)
        ) $e",
        'centros_custo' => "CREATE TABLE IF NOT EXISTS centros_custo (
            id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(150) NOT NULL
        ) $e",
        'caixas' => "CREATE TABLE IF NOT EXISTS caixas (
            id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, saldo_inicial DECIMAL(15,2) DEFAULT 0.00
        ) $e",
        'formas_pagamento' => "CREATE TABLE IF NOT EXISTS formas_pagamento (
            id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, banco VARCHAR(100) NULL, agencia VARCHAR(30) NULL,
            parcelas INT DEFAULT 1, dias_recebimento INT DEFAULT 0, taxa_intermediacao DECIMAL(8,4) DEFAULT 0,
            taxa_parcelamento DECIMAL(8,4) DEFAULT 0, tarifa_fixa DECIMAL(10,2) DEFAULT 0
        ) $e",
        'contas_receita' => "CREATE TABLE IF NOT EXISTS contas_receita (
            id INT AUTO_INCREMENT PRIMARY KEY, grupo VARCHAR(150) NOT NULL, conta VARCHAR(150)
        ) $e",
        'contas_despesa' => "CREATE TABLE IF NOT EXISTS contas_despesa (
            id INT AUTO_INCREMENT PRIMARY KEY, grupo VARCHAR(150) NOT NULL, conta VARCHAR(150)
        ) $e",
        'transactions' => "CREATE TABLE IF NOT EXISTS transactions (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            tipo                 ENUM('income','expense') NOT NULL,
            data                 DATE NOT NULL,
            descricao            VARCHAR(255),
            valor                DECIMAL(15,2) NOT NULL,
            conta_id             INT,
            conta_nome           VARCHAR(150),
            grupo                VARCHAR(150),
            categoria_id         INT NULL,
            categoria_nome       VARCHAR(150) NULL,
            cliente_fornecedor   VARCHAR(255),
            forma_pagamento_id   INT,
            forma_pagamento_nome VARCHAR(100),
            caixa_id             INT,
            caixa_nome           VARCHAR(100),
            centro_custo_id      INT,
            centro_custo_nome    VARCHAR(150),
            status               VARCHAR(30) DEFAULT 'quitado',
            data_vencimento      DATE,
            data_pagamento       DATE,
            taxa_mdr             DECIMAL(15,2) DEFAULT 0,
            valor_liquido        DECIMAL(15,2),
            observacoes          TEXT,
            user_id              INT,
            created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tx_data (data),
            INDEX idx_tx_categoria (categoria_id)
        ) $e",
        'transfers' => "CREATE TABLE IF NOT EXISTS transfers (
            id INT AUTO_INCREMENT PRIMARY KEY, data DATE NOT NULL, descricao VARCHAR(255), valor DECIMAL(15,2) NOT NULL,
            origem_id INT, origem_nome VARCHAR(100), destino_id INT, destino_nome VARCHAR(100),
            observacoes TEXT, user_id INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $e",
        'documentos' => "CREATE TABLE IF NOT EXISTS documentos (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            tipo          ENUM('contrato_parceiro','nota_fiscal','contrato_fornecedor') NOT NULL,
            parceiro_id   INT NULL,
            fornecedor_id INT NULL,
            descricao     VARCHAR(255) NULL,
            arquivo_path  VARCHAR(255) NOT NULL,
            arquivo_nome  VARCHAR(255) NOT NULL,
            user_id       INT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_doc_tipo (tipo),
            INDEX idx_doc_parceiro (parceiro_id)
        ) $e",
        'logs' => "CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, username VARCHAR(100), action VARCHAR(40) NOT NULL,
            entity VARCHAR(40) NOT NULL, entity_id INT NULL, details VARCHAR(500), ip VARCHAR(64),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_logs_created (created_at), INDEX idx_logs_user (user_id), INDEX idx_logs_action (action)
        ) $e",
        'rate_limits' => "CREATE TABLE IF NOT EXISTS rate_limits (
            k VARCHAR(190) PRIMARY KEY, start_at INT NOT NULL, hits INT NOT NULL DEFAULT 0
        ) $e",
    ];
}

/** Colunas adicionadas depois — pra quem já tem o banco criado pela versão Node. */
function schema_columns(): array {
    return [
        'users'            => ['nome' => 'VARCHAR(150) NULL AFTER username', 'foto_path' => 'VARCHAR(255) NULL', 'foto_nome' => 'VARCHAR(255) NULL', 'permissoes' => 'TEXT NULL'],
        'formas_pagamento' => ['banco' => 'VARCHAR(100) NULL', 'agencia' => 'VARCHAR(30) NULL'],
        'transactions'     => ['categoria_id' => 'INT NULL AFTER grupo', 'categoria_nome' => 'VARCHAR(150) NULL AFTER categoria_id'],
        'parceiros'        => ['observacoes' => 'TEXT NULL', 'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1'],
        'fornecedores'     => ['razao_social' => 'VARCHAR(255) NULL AFTER nome', 'whatsapp' => 'VARCHAR(30) NULL', 'responsavel' => 'VARCHAR(150) NULL',
                               'contrato_path' => 'VARCHAR(255) NULL', 'contrato_nome' => 'VARCHAR(255) NULL', 'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1'],
    ];
}

function run_migrations(): void {
    $pdo = db();
    foreach (schema_tables() as $sql) $pdo->exec($sql);

    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $existentes = q('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=?', [$dbName]);
    $cols = [];
    foreach ($existentes as $r) $cols[$r['TABLE_NAME']][$r['COLUMN_NAME']] = true;
    foreach (schema_columns() as $tabela => $novas) {
        foreach ($novas as $col => $def) {
            if (empty($cols[$tabela][$col])) $pdo->exec("ALTER TABLE `$tabela` ADD COLUMN `$col` $def");
        }
    }
    seed_dados();
    // Por enquanto todo mundo é admin (v5): promove quem já existia.
    if (TODOS_ADMIN) exec_sql("UPDATE users SET role='admin' WHERE role<>'admin'");
}

function seed_dados(): void {
    $count = fn(string $t) => (int) q1("SELECT COUNT(*) AS n FROM `$t`")['n'];

    if (!$count('centros_custo')) {
        foreach (['DESPESAS OPERACIONAIS', 'DESPESAS DE IMPOSTOS', 'DESPESAS ADMINISTRATIVAS', 'RECEITA OPERACIONAL', 'RECEITA VENDAS', 'RECEITA COMUM', 'ADMINISTRATIVO'] as $n)
            exec_sql('INSERT INTO centros_custo (nome) VALUES (?)', [$n]);
    }
    if (!$count('caixas')) exec_sql("INSERT INTO caixas (nome, saldo_inicial) VALUES ('Itaú', 0), ('NU BANK', 0)");
    if (!$count('formas_pagamento')) {
        exec_sql("INSERT INTO formas_pagamento (nome,parcelas,dias_recebimento,taxa_intermediacao,taxa_parcelamento,tarifa_fixa) VALUES
            ('CARTÃO CRÉDITO',1,30,0,1.9,0),('CARTÃO DÉBITO',1,1,0,0,0),('BOLETO BANCÁRIO',1,1,0,0,0),('DINHEIRO',1,0,0,0,0),
            ('PIX',1,0,0,0,0),('Débito',1,1,0,0,0),('Crédito à vista',1,31,0,0,0),('Crédito',2,31,0,0,0)");
    }
    if (!$count('contas_receita')) {
        exec_sql("INSERT INTO contas_receita (grupo, conta) VALUES ('PIX','Itaú'),('Cartão de débito','Itaú'),
            ('Cartão de Crédito Rede Itaú','Cartão Rede (Pix-Débito-Crédito)'),('Cartão de crédito','Itaú')");
    }
    if (!$count('contas_despesa')) {
        exec_sql("INSERT INTO contas_despesa (grupo, conta) VALUES ('CONSULTÓRIA','CONSULTÓRIO OAF'),('Prestadores de Serviço','Prestadores Direto'),
            ('Prestadores de Serviço','Prestadores Indiretos'),('Consultória','Edifício Kasato Maru - 1°Andar'),
            ('Plano de Sáude','Convênio Intermédica Smart 300'),('Administrativo','Estágiario'),
            ('Administrativo','Equipe de limpeza'),('Repasse dos Médicos(as)','Despesa Variavel')");
    }
    if (!$count('categorias')) {
        $padrao = [
            ['Consultas', 'income', '#147A4A'], ['Procedimentos', 'income', '#0E6E7A'], ['Outras Receitas', 'income', '#6B5CA5'],
            ['Aluguel', 'expense', '#B33F3F'], ['Folha / Prestadores', 'expense', '#B98B2E'], ['Impostos', 'expense', '#B0577D'],
            ['Marketing', 'expense', '#1F5FBF'], ['Materiais', 'expense', '#5E6E63'], ['Outras Despesas', 'expense', '#8D97AC'],
        ];
        foreach ($padrao as [$n, $t, $c]) exec_sql('INSERT INTO categorias (nome, tipo, cor) VALUES (?,?,?)', [$n, $t, $c]);
    }
    if (!$count('clientes')) exec_sql("INSERT INTO clientes (nome, cnpj_cpf, estado, cidade) VALUES ('Rede - Banco Itaú','','São Paulo','Sorocaba')");
    if (!$count('fornecedores')) exec_sql("INSERT INTO fornecedores (nome, cnpj_cpf) VALUES ('OAF - ODIRLEI AMARO FERREIRA','63.721.226/0001-00')");
}

/** Chamado no início de toda requisição da API: só migra se a versão mudou. */
function ensure_schema(): void {
    $lock = STORAGE_DIR . '/schema_version';
    if (is_file($lock) && (int) trim((string) file_get_contents($lock)) === SCHEMA_VERSION) return;
    run_migrations();
    @file_put_contents($lock, (string) SCHEMA_VERSION);
}

// CLI: php app/schema.php --sql   → imprime o SQL (usado pra gerar database.sql)
//      php app/schema.php --migrate → roda as migrações agora
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once __DIR__ . '/bootstrap.php';
    if (in_array('--sql', $argv, true)) {
        echo "-- DRE System — schema MySQL (gerado de app/schema.php)\n-- As tabelas também são criadas automaticamente no primeiro acesso.\n\n";
        echo "SET NAMES utf8mb4;\n\n";
        foreach (schema_tables() as $sql) echo preg_replace('/\n {12}/', "\n  ", $sql) . ";\n\n";
    } elseif (in_array('--migrate', $argv, true)) {
        run_migrations();
        @file_put_contents(STORAGE_DIR . '/schema_version', (string) SCHEMA_VERSION);
        echo "Migrações aplicadas.\n";
    }
}
