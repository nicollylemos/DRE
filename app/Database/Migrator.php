<?php
namespace App\Database;

use App\Core\Database;
use App\Core\Permissions;

/**
 * Cria/migra as tabelas sozinho. Só roda quando VERSAO muda (controle em
 * storage/schema_version), então não pesa nas requisições normais.
 * O mesmo schema está em database.sql pra quem preferir importar no phpMyAdmin.
 *
 *   php app/Database/Migrator.php --migrate   → aplica agora
 *   php app/Database/Migrator.php --sql       → imprime o SQL (gera o database.sql)
 */
final class Migrator
{
    public const VERSAO = 9;

    public static function tabelas(): array
    {
        $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            // Níveis de acesso: permissoes = JSON {"tela": 0|1|2} (sem acesso / ver / editar).
            'perfis' => "CREATE TABLE IF NOT EXISTS perfis (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                nome        VARCHAR(80) NOT NULL UNIQUE,
                descricao   VARCHAR(255) NULL,
                is_admin    TINYINT(1) NOT NULL DEFAULT 0,
                sistema     TINYINT(1) NOT NULL DEFAULT 0,
                permissoes  TEXT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $e",
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                username          VARCHAR(100) NOT NULL UNIQUE,
                nome              VARCHAR(150) NULL,
                email             VARCHAR(150) NULL,
                password          VARCHAR(255) NOT NULL,
                role              ENUM('admin','user') DEFAULT 'user',
                perfil_id         INT NULL,
                ativo             TINYINT(1) NOT NULL DEFAULT 1,
                deve_trocar_senha TINYINT(1) NOT NULL DEFAULT 0,
                tentativas_falhas INT NOT NULL DEFAULT 0,
                bloqueado_ate     DATETIME NULL,
                ultimo_login      DATETIME NULL,
                ultimo_ip         VARCHAR(64) NULL,
                senha_alterada_em DATETIME NULL,
                foto_path         VARCHAR(255) NULL,
                foto_nome         VARCHAR(255) NULL,
                permissoes        TEXT NULL,
                termos_versao     VARCHAR(20) NULL,
                termos_aceitos_em DATETIME NULL,
                created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_users_perfil (perfil_id)
            ) $e",
            'clientes' => "CREATE TABLE IF NOT EXISTS clientes (
                id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, razao_social VARCHAR(255) NULL, cnpj_cpf VARCHAR(30),
                estado VARCHAR(50), cidade VARCHAR(100), endereco VARCHAR(255), telefone VARCHAR(30),
                email VARCHAR(150), observacoes TEXT, importacao_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $e",
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
                importacao_id INT NULL,
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
                id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL,
                tipo VARCHAR(20) NOT NULL DEFAULT 'banco', banco VARCHAR(100) NULL, agencia VARCHAR(30) NULL, conta VARCHAR(30) NULL,
                saldo_inicial DECIMAL(15,2) DEFAULT 0.00, observacoes VARCHAR(255) NULL
            ) $e",
            'formas_pagamento' => "CREATE TABLE IF NOT EXISTS formas_pagamento (
                id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, tipo VARCHAR(20) NULL, banco VARCHAR(100) NULL, agencia VARCHAR(30) NULL,
                parcelas INT DEFAULT 1, dias_recebimento INT DEFAULT 0, taxa_intermediacao DECIMAL(8,4) DEFAULT 0,
                taxa_parcelamento DECIMAL(8,4) DEFAULT 0, tarifa_fixa DECIMAL(10,2) DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1
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
                importacao_id        INT NULL,
                user_id              INT,
                created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tx_data (data),
                INDEX idx_tx_venc (data_vencimento),
                INDEX idx_tx_categoria (categoria_id),
                INDEX idx_tx_importacao (importacao_id)
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
            // Notas fiscais (PDF e/ou XML) — arquivos ficam em storage/notas (fora do acesso direto pelo navegador).
            // tipo: entrada = compra/serviço tomado (vira despesa) · saida = venda/serviço prestado (vira receita).
            'notas_fiscais' => "CREATE TABLE IF NOT EXISTS notas_fiscais (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                tipo              ENUM('entrada','saida') NOT NULL DEFAULT 'entrada',
                modelo            VARCHAR(10) NULL,
                numero            VARCHAR(20) NULL,
                serie             VARCHAR(10) NULL,
                chave             VARCHAR(60) NULL,
                data_emissao      DATE NULL,
                valor             DECIMAL(15,2) NOT NULL DEFAULT 0,
                emitente_nome     VARCHAR(255) NULL,
                emitente_doc      VARCHAR(20) NULL,
                destinatario_nome VARCHAR(255) NULL,
                destinatario_doc  VARCHAR(20) NULL,
                descricao         VARCHAR(255) NULL,
                observacoes       TEXT NULL,
                pdf_path          VARCHAR(255) NULL,
                pdf_nome          VARCHAR(255) NULL,
                xml_path          VARCHAR(255) NULL,
                xml_nome          VARCHAR(255) NULL,
                transaction_id    INT NULL,
                user_id           INT NULL,
                created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at        DATETIME NULL,
                INDEX idx_nf_tx (transaction_id),
                INDEX idx_nf_chave (chave),
                INDEX idx_nf_emissao (data_emissao)
            ) $e",
            // Histórico de importações de planilha (permite desfazer).
            'importacoes' => "CREATE TABLE IF NOT EXISTS importacoes (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                tipo         VARCHAR(30) NOT NULL,
                arquivo_nome VARCHAR(255) NOT NULL,
                total_linhas INT NOT NULL DEFAULT 0,
                importados   INT NOT NULL DEFAULT 0,
                ignorados    INT NOT NULL DEFAULT 0,
                valor_total  DECIMAL(15,2) NOT NULL DEFAULT 0,
                user_id      INT NULL,
                username     VARCHAR(100) NULL,
                desfeita_em  DATETIME NULL,
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $e",
            'logs' => "CREATE TABLE IF NOT EXISTS logs (
                id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, username VARCHAR(100), action VARCHAR(40) NOT NULL,
                entity VARCHAR(40) NOT NULL, entity_id INT NULL, details VARCHAR(500), ip VARCHAR(64),
                dados MEDIUMTEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_logs_created (created_at), INDEX idx_logs_user (user_id), INDEX idx_logs_action (action)
            ) $e",
            'rate_limits' => "CREATE TABLE IF NOT EXISTS rate_limits (
                k VARCHAR(190) PRIMARY KEY, start_at INT NOT NULL, hits INT NOT NULL DEFAULT 0
            ) $e",
        ];
    }

    /** Colunas adicionadas depois — pra quem já tem o banco das versões anteriores. */
    public static function colunas(): array
    {
        return [
            'users'            => ['nome' => 'VARCHAR(150) NULL AFTER username', 'email' => 'VARCHAR(150) NULL AFTER nome',
                                   'perfil_id' => 'INT NULL AFTER role', 'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER perfil_id',
                                   'deve_trocar_senha' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo',
                                   'tentativas_falhas' => 'INT NOT NULL DEFAULT 0 AFTER deve_trocar_senha',
                                   'bloqueado_ate' => 'DATETIME NULL AFTER tentativas_falhas', 'ultimo_login' => 'DATETIME NULL AFTER bloqueado_ate',
                                   'ultimo_ip' => 'VARCHAR(64) NULL AFTER ultimo_login', 'senha_alterada_em' => 'DATETIME NULL AFTER ultimo_ip',
                                   'foto_path' => 'VARCHAR(255) NULL', 'foto_nome' => 'VARCHAR(255) NULL', 'permissoes' => 'TEXT NULL',
                                   'termos_versao' => 'VARCHAR(20) NULL', 'termos_aceitos_em' => 'DATETIME NULL'],
            'formas_pagamento' => ['banco' => 'VARCHAR(100) NULL', 'agencia' => 'VARCHAR(30) NULL', 'tipo' => 'VARCHAR(20) NULL AFTER nome',
                                   'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1'],
            'logs'             => ['dados' => 'MEDIUMTEXT NULL AFTER ip'],
            'clientes'         => ['razao_social' => 'VARCHAR(255) NULL AFTER nome', 'importacao_id' => 'INT NULL'],
            'caixas'           => ['tipo' => "VARCHAR(20) NOT NULL DEFAULT 'banco' AFTER nome", 'banco' => 'VARCHAR(100) NULL AFTER tipo',
                                   'agencia' => 'VARCHAR(30) NULL AFTER banco', 'conta' => 'VARCHAR(30) NULL AFTER agencia', 'observacoes' => 'VARCHAR(255) NULL'],
            'transactions'     => ['categoria_id' => 'INT NULL AFTER grupo', 'categoria_nome' => 'VARCHAR(150) NULL AFTER categoria_id',
                                   'importacao_id' => 'INT NULL AFTER observacoes'],
            'parceiros'        => ['observacoes' => 'TEXT NULL', 'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1'],
            'fornecedores'     => ['razao_social' => 'VARCHAR(255) NULL AFTER nome', 'whatsapp' => 'VARCHAR(30) NULL', 'responsavel' => 'VARCHAR(150) NULL',
                                   'contrato_path' => 'VARCHAR(255) NULL', 'contrato_nome' => 'VARCHAR(255) NULL', 'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1',
                                   'importacao_id' => 'INT NULL'],
        ];
    }

    public static function migrar(): void
    {
        $pdo = Database::pdo();
        foreach (self::tabelas() as $sql) $pdo->exec($sql);

        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $cols = [];
        foreach (Database::all('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=?', [$db]) as $r)
            $cols[$r['TABLE_NAME']][$r['COLUMN_NAME']] = true;
        foreach (self::colunas() as $tabela => $novas)
            foreach ($novas as $col => $def)
                if (empty($cols[$tabela][$col])) $pdo->exec("ALTER TABLE `$tabela` ADD COLUMN `$col` $def");

        $idxImp = Database::value("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='transactions' AND INDEX_NAME='idx_tx_importacao'", [$db]);
        if (!(int) $idxImp) $pdo->exec('ALTER TABLE transactions ADD INDEX idx_tx_importacao (importacao_id)');

        self::seed();

        // v6: formas antigas sem tipo — deduz pelo nome (dá pra corrigir na tela depois).
        Database::run("UPDATE formas_pagamento SET tipo = CASE
            WHEN UPPER(nome) LIKE '%PIX%' THEN 'pix'
            WHEN UPPER(nome) LIKE '%BOLETO%' THEN 'boleto'
            WHEN UPPER(nome) LIKE '%DINHEIRO%' THEN 'dinheiro'
            WHEN UPPER(nome) LIKE '%DÉBITO%' OR UPPER(nome) LIKE '%DEBITO%' THEN 'debito'
            WHEN UPPER(nome) LIKE '%CRÉDITO%' OR UPPER(nome) LIKE '%CREDITO%' THEN 'credito'
            WHEN UPPER(nome) LIKE '%TRANSF%' OR UPPER(nome) LIKE '%TED%' OR UPPER(nome) LIKE '%DOC %' THEN 'transferencia'
            ELSE NULL END WHERE tipo IS NULL OR tipo = ''");

        // v8: formas que faltavam (cartão de débito e transferência) — só cria se ainda não houver uma desse tipo.
        $faltam = ['debito' => ['CARTÃO DÉBITO', 1], 'transferencia' => ['TRANSFERÊNCIA BANCÁRIA (TED/DOC)', 0]];
        foreach ($faltam as $tipo => [$nome, $dias]) {
            if ((int) Database::value('SELECT COUNT(*) FROM formas_pagamento WHERE tipo=?', [$tipo])) continue;
            if ((int) Database::value('SELECT COUNT(*) FROM formas_pagamento WHERE nome=?', [$nome])) continue;
            Database::run('INSERT INTO formas_pagamento (nome, tipo, parcelas, dias_recebimento, taxa_intermediacao, taxa_parcelamento, tarifa_fixa, ativo) VALUES (?,?,1,?,0,0,0,1)', [$nome, $tipo, $dias]);
        }

        // v9: recuperação de senha por e-mail foi retirada — some com a tabela dos links.
        $pdo->exec('DROP TABLE IF EXISTS password_resets');

        // v8: taxa de cartão só existe em receita (auditoria, item 5) — limpa sobras antigas em despesas.
        Database::run("UPDATE transactions SET taxa_mdr=0, valor_liquido=valor WHERE tipo='expense' AND taxa_mdr<>0");

        // v8: tela nova "Notas fiscais" — níveis antigos recebem o mesmo acesso que têm em Lançamentos.
        foreach (Database::all('SELECT id, permissoes FROM perfis') as $p) {
            $perm = json_decode((string) $p['permissoes'], true);
            if (!is_array($perm) || array_key_exists('notas-fiscais', $perm)) continue;
            $perm['notas-fiscais'] = (int) ($perm['lancamentos'] ?? 0);
            Database::run('UPDATE perfis SET permissoes=? WHERE id=?', [json_encode(Permissions::normalizar($perm)), $p['id']]);
        }

        // v7: usuários antigos (role admin/user) ganham o nível de acesso equivalente.
        $admin = (int) Database::value("SELECT id FROM perfis WHERE is_admin=1 ORDER BY sistema DESC, id LIMIT 1");
        $usuario = (int) Database::value("SELECT id FROM perfis WHERE nome='Usuário' LIMIT 1");
        if ($admin) Database::run("UPDATE users SET perfil_id=? WHERE perfil_id IS NULL AND role='admin'", [$admin]);
        if ($usuario) Database::run("UPDATE users SET perfil_id=? WHERE perfil_id IS NULL", [$usuario]);
    }

    /** Níveis de acesso padrão. Administrador é do sistema (não pode ser apagado). */
    public static function perfisPadrao(): array
    {
        $P = fn(array $m) => json_encode(Permissions::normalizar($m));
        return [
            ['Administrador', 'Acesso total, inclusive usuários e níveis de acesso.', 1, 1, $P(Permissions::todas())],
            ['Usuário', 'Dia a dia: lançamentos e cadastros de clientes, fornecedores e parceiros.', 0, 0, $P([
                'dashboard' => 1, 'calendario' => 1, 'lancamentos' => 2, 'notas-fiscais' => 2, 'fluxo-caixa' => 1,
                'cad-clientes' => 2, 'cad-fornecedores' => 2, 'cad-parceiros' => 2, 'logs' => 1,
            ])],
            ['Financeiro', 'Todas as telas financeiras e cadastros, sem gerenciar usuários.', 0, 0, $P(Permissions::todas())],
            ['Consulta', 'Só visualiza: não cria, não altera e não apaga nada.', 0, 0, $P([
                'dashboard' => 1, 'dre' => 1, 'calendario' => 1, 'lancamentos' => 1, 'notas-fiscais' => 1, 'fluxo-caixa' => 1, 'importacao' => 1,
                'cad-clientes' => 1, 'cad-fornecedores' => 1, 'cad-parceiros' => 1, 'cad-categorias' => 1,
            ])],
        ];
    }

    private static function seed(): void
    {
        $count = fn(string $t) => (int) Database::value("SELECT COUNT(*) FROM `$t`");

        if (!$count('perfis')) {
            foreach (self::perfisPadrao() as [$nome, $desc, $adm, $sis, $perm])
                Database::run('INSERT INTO perfis (nome, descricao, is_admin, sistema, permissoes) VALUES (?,?,?,?,?)', [$nome, $desc, $adm, $sis, $perm]);
        }
        if (!$count('centros_custo')) {
            foreach (['DESPESAS OPERACIONAIS', 'DESPESAS DE IMPOSTOS', 'DESPESAS ADMINISTRATIVAS', 'RECEITA OPERACIONAL', 'RECEITA VENDAS', 'RECEITA COMUM', 'ADMINISTRATIVO'] as $n)
                Database::run('INSERT INTO centros_custo (nome) VALUES (?)', [$n]);
        }
        if (!$count('caixas')) Database::run("INSERT INTO caixas (nome, tipo, banco, saldo_inicial) VALUES ('Itaú', 'banco', 'Itaú', 0), ('NU BANK', 'banco', 'Nubank', 0)");
        if (!$count('formas_pagamento')) {
            Database::run("INSERT INTO formas_pagamento (nome,tipo,parcelas,dias_recebimento,taxa_intermediacao,taxa_parcelamento,tarifa_fixa) VALUES
                ('CARTÃO CRÉDITO','credito',1,30,0,1.9,0),('CARTÃO DÉBITO','debito',1,1,0,0,0),('BOLETO BANCÁRIO','boleto',1,1,0,0,0),('DINHEIRO','dinheiro',1,0,0,0,0),
                ('PIX','pix',1,0,0,0,0),('TRANSFERÊNCIA BANCÁRIA (TED/DOC)','transferencia',1,0,0,0,0)");
        }
        if (!$count('contas_receita')) {
            Database::run("INSERT INTO contas_receita (grupo, conta) VALUES ('PIX','Itaú'),('Cartão de débito','Itaú'),
                ('Cartão de Crédito Rede Itaú','Cartão Rede (Pix-Débito-Crédito)'),('Cartão de crédito','Itaú')");
        }
        if (!$count('contas_despesa')) {
            Database::run("INSERT INTO contas_despesa (grupo, conta) VALUES ('CONSULTÓRIA','CONSULTÓRIO OAF'),('Prestadores de Serviço','Prestadores Direto'),
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
            foreach ($padrao as [$n, $t, $c]) Database::run('INSERT INTO categorias (nome, tipo, cor) VALUES (?,?,?)', [$n, $t, $c]);
        }
    }

    /** Chamado no início de toda requisição: só migra se a versão mudou. */
    public static function garantir(): void
    {
        $lock = STORAGE_DIR . '/schema_version';
        if (is_file($lock) && (int) trim((string) file_get_contents($lock)) === self::VERSAO) return;
        self::migrar();
        @file_put_contents($lock, (string) self::VERSAO);
    }
}

// Linha de comando
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once dirname(__DIR__) . '/bootstrap.php';
    if (in_array('--sql', $argv, true)) {
        echo "-- DRE System — schema MySQL (gerado por app/Database/Migrator.php)\n-- As tabelas também são criadas automaticamente no primeiro acesso.\n\n";
        echo "SET NAMES utf8mb4;\n\n";
        foreach (Migrator::tabelas() as $sql) echo preg_replace('/\n {16}/', "\n  ", $sql) . ";\n\n";
        echo "-- Níveis de acesso padrão\n";
        foreach (Migrator::perfisPadrao() as [$nome, $desc, $adm, $sis, $perm])
            printf("INSERT INTO perfis (nome, descricao, is_admin, sistema, permissoes) VALUES ('%s', '%s', %d, %d, '%s');\n", $nome, addslashes($desc), $adm, $sis, $perm);
    } elseif (in_array('--migrate', $argv, true)) {
        Migrator::migrar();
        @file_put_contents(STORAGE_DIR . '/schema_version', (string) Migrator::VERSAO);
        echo "Migrações aplicadas (versão " . Migrator::VERSAO . ").\n";
    } else {
        echo "Uso: php app/Database/Migrator.php --migrate | --sql\n";
    }
}
