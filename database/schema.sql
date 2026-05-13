-- ============================================================
--  MindCash — Schema do Banco de Dados
--  Compatível com MySQL 8+ / MariaDB 10.6+
-- ============================================================

CREATE DATABASE IF NOT EXISTS mindcash
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mindcash;

-- ── Usuários ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    uuid          CHAR(36)        NOT NULL UNIQUE,           -- identificador público
    nome          VARCHAR(120)    NOT NULL DEFAULT 'Anônimo',
    email         VARCHAR(255)    UNIQUE,                    -- NULL para anônimos
    senha_hash    VARCHAR(255),                              -- NULL para OAuth
    avatar_url    VARCHAR(512)    DEFAULT NULL,
    google_id     VARCHAR(128)    UNIQUE DEFAULT NULL,
    nivel         ENUM('anonimo','membro','adm') NOT NULL DEFAULT 'anonimo',
    ativo         TINYINT(1)      NOT NULL DEFAULT 1,
    criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_login  DATETIME        DEFAULT NULL,
    INDEX idx_email  (email),
    INDEX idx_nivel  (nivel)
) ENGINE=InnoDB;

-- ── Sessões persistentes ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessoes (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED    NOT NULL,
    token_hash    CHAR(64)        NOT NULL UNIQUE,           -- SHA-256
    ip            VARCHAR(45)     DEFAULT NULL,
    user_agent    VARCHAR(512)    DEFAULT NULL,
    expira_em     DATETIME        NOT NULL,
    criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token_hash),
    INDEX idx_expira (expira_em)
) ENGINE=InnoDB;

-- ── Tokens CSRF ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS csrf_tokens (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED    NOT NULL,
    token         CHAR(64)        NOT NULL UNIQUE,
    expira_em     DATETIME        NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Mensagens da Comunidade ──────────────────────────────────
CREATE TABLE IF NOT EXISTS mensagens (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED    NOT NULL,
    conteudo      TEXT            NOT NULL,
    deletado      TINYINT(1)      NOT NULL DEFAULT 0,
    criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_criado (criado_em),
    FULLTEXT INDEX ft_conteudo (conteudo)
) ENGINE=InnoDB;

-- ── Reações às mensagens ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS reacoes (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mensagem_id   BIGINT UNSIGNED NOT NULL,
    usuario_id    INT UNSIGNED    NOT NULL,
    tipo          ENUM('like','heart','fire') NOT NULL DEFAULT 'like',
    criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reacao (mensagem_id, usuario_id, tipo),
    FOREIGN KEY (mensagem_id) REFERENCES mensagens(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Tópicos de Mentoria ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS topicos_mentoria (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    titulo        VARCHAR(255)    NOT NULL,
    descricao     TEXT,
    adm_id        INT UNSIGNED    NOT NULL,
    publicado     TINYINT(1)      NOT NULL DEFAULT 0,
    criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (adm_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ── Perfil estendido do usuário ──────────────────────────────
CREATE TABLE IF NOT EXISTS perfis (
    usuario_id    INT UNSIGNED    PRIMARY KEY,
    bio           VARCHAR(500)    DEFAULT NULL,
    meta_mensal   DECIMAL(12,2)   DEFAULT 0.00,
    moeda         CHAR(3)         DEFAULT 'BRL',
    tema          ENUM('dark','light') DEFAULT 'dark',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Log de atividades ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS atividades (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED    NOT NULL,
    acao          VARCHAR(120)    NOT NULL,
    detalhes      JSON            DEFAULT NULL,
    ip            VARCHAR(45)     DEFAULT NULL,
    criado_em     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_acao (usuario_id, criado_em)
) ENGINE=InnoDB;

-- ── Dados de seed (admin padrão) ────────────────────────────
-- Senha padrão: MindCash@ADM2025  (bcrypt — troque imediatamente)
INSERT INTO usuarios (uuid, nome, email, senha_hash, nivel)
VALUES (
    UUID(),
    'Administrador',
    'adm@mindcash.local',
    '$2y$12$exampleHashMustBeReplacedByRealBcryptHashHereXXXXXXXXXX',
    'adm'
) ON DUPLICATE KEY UPDATE id = id;
