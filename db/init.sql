CREATE DATABASE IF NOT EXISTS gerenciador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gerenciador;

CREATE TABLE IF NOT EXISTS usuarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome            VARCHAR(80)  NOT NULL,
  sobrenome       VARCHAR(80)  NOT NULL,
  data_nascimento DATE         NOT NULL,
  login           VARCHAR(50)  NOT NULL,
  senha_hash      VARCHAR(255) NOT NULL,
  is_admin        TINYINT(1)   NOT NULL DEFAULT 0,
  foto            VARCHAR(255) NULL,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_login (login)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tarefas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id     INT UNSIGNED NOT NULL,
  titulo         VARCHAR(150) NOT NULL,
  descricao      TEXT         NULL,
  data_criacao   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_conclusao DATE         NOT NULL,
  status         ENUM('em_aberto','concluida','em_atraso') NOT NULL DEFAULT 'em_aberto',
  PRIMARY KEY (id),
  KEY idx_tarefas_usuario_status (usuario_id, status),
  CONSTRAINT fk_tarefas_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;
