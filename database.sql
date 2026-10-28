-- DRE System — schema MySQL (gerado por app/Database/Migrator.php)
-- As tabelas também são criadas automaticamente no primeiro acesso.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS perfis (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(80) NOT NULL UNIQUE,
  descricao   VARCHAR(255) NULL,
  is_admin    TINYINT(1) NOT NULL DEFAULT 0,
  sistema     TINYINT(1) NOT NULL DEFAULT 0,
  permissoes  TEXT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, razao_social VARCHAR(255) NULL, cnpj_cpf VARCHAR(30),
  estado VARCHAR(50), cidade VARCHAR(100), endereco VARCHAR(255), telefone VARCHAR(30),
  email VARCHAR(150), observacoes TEXT, importacao_id INT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fornecedores (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parceiros (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(150) NOT NULL,
  tipo       ENUM('income','expense','ambos') NOT NULL DEFAULT 'ambos',
  cor        VARCHAR(7) DEFAULT '#1F5FBF',
  descricao  VARCHAR(255) NULL,
  ativo      TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categoria (nome, tipo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS centros_custo (
  id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(150) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caixas (
  id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'banco', banco VARCHAR(100) NULL, agencia VARCHAR(30) NULL, conta VARCHAR(30) NULL,
  saldo_inicial DECIMAL(15,2) DEFAULT 0.00, observacoes VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formas_pagamento (
  id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, tipo VARCHAR(20) NULL, banco VARCHAR(100) NULL, agencia VARCHAR(30) NULL,
  parcelas INT DEFAULT 1, dias_recebimento INT DEFAULT 0, taxa_intermediacao DECIMAL(8,4) DEFAULT 0,
  taxa_parcelamento DECIMAL(8,4) DEFAULT 0, tarifa_fixa DECIMAL(10,2) DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_receita (
  id INT AUTO_INCREMENT PRIMARY KEY, grupo VARCHAR(150) NOT NULL, conta VARCHAR(150)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_despesa (
  id INT AUTO_INCREMENT PRIMARY KEY, grupo VARCHAR(150) NOT NULL, conta VARCHAR(150)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transfers (
  id INT AUTO_INCREMENT PRIMARY KEY, data DATE NOT NULL, descricao VARCHAR(255), valor DECIMAL(15,2) NOT NULL,
  origem_id INT, origem_nome VARCHAR(100), destino_id INT, destino_nome VARCHAR(100),
  observacoes TEXT, user_id INT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notas_fiscais (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em  DATETIME NOT NULL,
  usado_em   DATETIME NULL,
  ip         VARCHAR(64) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reset_token (token_hash),
  INDEX idx_reset_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importacoes (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, username VARCHAR(100), action VARCHAR(40) NOT NULL,
  entity VARCHAR(40) NOT NULL, entity_id INT NULL, details VARCHAR(500), ip VARCHAR(64),
  dados MEDIUMTEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_created (created_at), INDEX idx_logs_user (user_id), INDEX idx_logs_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  k VARCHAR(190) PRIMARY KEY, start_at INT NOT NULL, hits INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Níveis de acesso padrão
INSERT INTO perfis (nome, descricao, is_admin, sistema, permissoes) VALUES ('Administrador', 'Acesso total, inclusive usuários e níveis de acesso.', 1, 1, '{"dashboard":1,"dre":1,"calendario":1,"lancamentos":2,"notas-fiscais":2,"fluxo-caixa":1,"importacao":2,"cad-clientes":2,"cad-fornecedores":2,"cad-parceiros":2,"cad-categorias":2,"cad-caixas":2,"cad-formas":2,"logs":1}');
INSERT INTO perfis (nome, descricao, is_admin, sistema, permissoes) VALUES ('Usuário', 'Dia a dia: lançamentos e cadastros de clientes, fornecedores e parceiros.', 0, 0, '{"dashboard":1,"dre":0,"calendario":1,"lancamentos":2,"notas-fiscais":2,"fluxo-caixa":1,"importacao":0,"cad-clientes":2,"cad-fornecedores":2,"cad-parceiros":2,"cad-categorias":0,"cad-caixas":0,"cad-formas":0,"logs":1}');
INSERT INTO perfis (nome, descricao, is_admin, sistema, permissoes) VALUES ('Financeiro', 'Todas as telas financeiras e cadastros, sem gerenciar usuários.', 0, 0, '{"dashboard":1,"dre":1,"calendario":1,"lancamentos":2,"notas-fiscais":2,"fluxo-caixa":1,"importacao":2,"cad-clientes":2,"cad-fornecedores":2,"cad-parceiros":2,"cad-categorias":2,"cad-caixas":2,"cad-formas":2,"logs":1}');
INSERT INTO perfis (nome, descricao, is_admin, sistema, permissoes) VALUES ('Consulta', 'Só visualiza: não cria, não altera e não apaga nada.', 0, 0, '{"dashboard":1,"dre":1,"calendario":1,"lancamentos":1,"notas-fiscais":1,"fluxo-caixa":1,"importacao":1,"cad-clientes":1,"cad-fornecedores":1,"cad-parceiros":1,"cad-categorias":1,"cad-caixas":0,"cad-formas":0,"logs":0}');
