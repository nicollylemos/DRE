-- DRE System — schema MySQL (gerado de app/schema.php)
-- As tabelas também são criadas automaticamente no primeiro acesso.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(100) NOT NULL UNIQUE,
  nome       VARCHAR(150) NULL,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin','user') DEFAULT 'user',
  foto_path  VARCHAR(255) NULL,
  foto_nome  VARCHAR(255) NULL,
  permissoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, cnpj_cpf VARCHAR(30),
  estado VARCHAR(50), cidade VARCHAR(100), endereco VARCHAR(255), telefone VARCHAR(30),
  email VARCHAR(150), observacoes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
  id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, saldo_inicial DECIMAL(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formas_pagamento (
  id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, banco VARCHAR(100) NULL, agencia VARCHAR(30) NULL,
  parcelas INT DEFAULT 1, dias_recebimento INT DEFAULT 0, taxa_intermediacao DECIMAL(8,4) DEFAULT 0,
  taxa_parcelamento DECIMAL(8,4) DEFAULT 0, tarifa_fixa DECIMAL(10,2) DEFAULT 0
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
  user_id              INT,
  created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tx_data (data),
  INDEX idx_tx_categoria (categoria_id)
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

CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, username VARCHAR(100), action VARCHAR(40) NOT NULL,
  entity VARCHAR(40) NOT NULL, entity_id INT NULL, details VARCHAR(500), ip VARCHAR(64),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_created (created_at), INDEX idx_logs_user (user_id), INDEX idx_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  k VARCHAR(190) PRIMARY KEY, start_at INT NOT NULL, hits INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

