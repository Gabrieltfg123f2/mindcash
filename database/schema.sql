-- ============================================
--  MindCash — Schema MySQL (PDO Ready)
--  Engine: InnoDB | Charset: utf8mb4
-- ============================================

CREATE DATABASE IF NOT EXISTS mindcash
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mindcash;

-- ──────────────────────────────────────────
--  TABELA: usuarios
-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(100)     NOT NULL,
    email         VARCHAR(180)     NOT NULL UNIQUE,
    senha         VARCHAR(255)     NOT NULL,           -- bcrypt hash
    nivel         ENUM('membro','adm') NOT NULL DEFAULT 'membro',
    foto          VARCHAR(300)     DEFAULT NULL,       -- caminho relativo
    bio           TEXT             DEFAULT NULL,
    ativo         TINYINT(1)       NOT NULL DEFAULT 1,
    criado_em     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email),
    INDEX idx_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
--  TABELA: sessoes (tokens de sessão)
-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessoes (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED  NOT NULL,
    token       VARCHAR(64)   NOT NULL UNIQUE,         -- SHA-256 hex
    ip          VARCHAR(45)   DEFAULT NULL,
    user_agent  VARCHAR(300)  DEFAULT NULL,
    expira_em   DATETIME      NOT NULL,
    criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
--  TABELA: mensagens_comunidade
-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mensagens_comunidade (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED  NOT NULL,
    conteudo    TEXT          NOT NULL,                -- sanitizado no servidor
    criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
--  TABELA: historico_acoes
-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS historico_acoes (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED  NOT NULL,
    acao        VARCHAR(150)  NOT NULL,
    detalhes    JSON          DEFAULT NULL,
    ip          VARCHAR(45)   DEFAULT NULL,
    criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_acao (usuario_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
--  TABELA: ferramentas_dados (monitoramento)
-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ferramentas_dados (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED  NOT NULL,
    tipo        ENUM('meta','alerta','nota') NOT NULL DEFAULT 'nota',
    titulo      VARCHAR(200)  NOT NULL,
    valor       DECIMAL(15,2) DEFAULT NULL,
    meta_valor  DECIMAL(15,2) DEFAULT NULL,
    cor         VARCHAR(7)    DEFAULT '#6C63FF',
    ativo       TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_tipo (usuario_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
--  TABELA: mentorias
-- ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mentorias (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    titulo      VARCHAR(200)  NOT NULL,
    descricao   TEXT          DEFAULT NULL,
    conteudo    LONGTEXT      DEFAULT NULL,            -- HTML seguro
    autor_id    INT UNSIGNED  NOT NULL,
    publicado   TINYINT(1)    NOT NULL DEFAULT 0,
    criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_publicado (publicado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────
--  DADOS INICIAIS — Admin padrão
--  Senha: Admin@2025  (bcrypt $2y$12$...)
-- ──────────────────────────────────────────
INSERT IGNORE INTO usuarios (nome, email, senha, nivel) VALUES
(
    'Administrador',
    'admin@mindcash.app',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'adm'
),
(
    'Usuário Demo',
    'demo@mindcash.app',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'membro'
);
-- Senha dos dois usuários acima: "password" (para teste)
-- Em produção, gere novos hashes com password_hash()