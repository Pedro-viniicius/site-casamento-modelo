-- Schema do site de casamento
-- MySQL 8 / MariaDB 10.6+ · utf8mb4 · InnoDB
-- Importe no phpMyAdmin (aba Importar) ANTES dos seeds.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS config_site (
  chave        VARCHAR(64)  NOT NULL PRIMARY KEY,
  valor        TEXT         NOT NULL,
  atualizado_em DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presentes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(80)  NOT NULL UNIQUE,
  tipo          ENUM('item','cota') NOT NULL DEFAULT 'item',
  nome          VARCHAR(160) NOT NULL,
  descricao     VARCHAR(500) NULL,
  categoria     VARCHAR(40)  NOT NULL,
  faixa         ENUM('ate-100','100-250','acima-250') NOT NULL,
  preco_ref     DECIMAL(10,2) NULL COMMENT 'valor de referência dos noivos (casar.com) ou valor sugerido da cota',
  imagem_url    VARCHAR(500) NULL,
  qtd_desejada  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  prioridade    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = "mais desejado" pelos noivos',
  busca_etiqueta VARCHAR(200) NULL COMMENT 'termos usados pelo importador do Etiqueta',
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  ordem         INT          NOT NULL DEFAULT 0,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_presentes_ativo (ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ofertas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  presente_id    INT UNSIGNED NOT NULL,
  loja           VARCHAR(40)  NOT NULL COMMENT 'amazon, ml, shopee, magalu, ... (ver inc/lojas.php)',
  titulo         VARCHAR(300) NOT NULL,
  preco          DECIMAL(10,2) NOT NULL,
  url            VARCHAR(700) NOT NULL COMMENT 'URL canônica e limpa',
  link_afiliado  VARCHAR(700) NULL COMMENT 'se preenchido, o botão usa este link',
  imagem_url     VARCHAR(500) NULL COMMENT 'só URL do CDN da loja; nada é baixado',
  avaliacao      DECIMAL(2,1) NULL,
  num_avaliacoes INT UNSIGNED NOT NULL DEFAULT 0,
  frete_gratis   TINYINT(1)   NOT NULL DEFAULT 0,
  principal      TINYINT(1)   NOT NULL DEFAULT 0,
  origem         ENUM('etiqueta','manual') NOT NULL DEFAULT 'manual',
  chave_origem   VARCHAR(120) NULL COMMENT 'chave da oferta no Etiqueta (amazon:ASIN, ml:MLB...)',
  verificado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ofertas_presente (presente_id, principal),
  CONSTRAINT fk_ofertas_presente FOREIGN KEY (presente_id) REFERENCES presentes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presentes_marcados (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  presente_id   INT UNSIGNED NOT NULL,
  nome_opcional VARCHAR(120) NULL,
  token_hash    CHAR(64)     NOT NULL COMMENT 'sha256 do token guardado no aparelho de quem marcou',
  ip_hash       CHAR(64)     NOT NULL,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  desfeito_em   DATETIME     NULL,
  UNIQUE KEY uq_marcado_token (token_hash),
  KEY idx_marcados_presente (presente_id, desfeito_em),
  CONSTRAINT fk_marcados_presente FOREIGN KEY (presente_id) REFERENCES presentes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rsvps (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome               VARCHAR(160) NOT NULL,
  email              VARCHAR(190) NOT NULL,
  telefone           VARCHAR(30)  NULL,
  vai                TINYINT(1)   NOT NULL,
  adultos            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  criancas           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  mensagem           VARCHAR(1000) NULL,
  token_edicao_hash  CHAR(64)     NOT NULL,
  ip_hash            CHAR(64)     NOT NULL,
  criado_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rsvps_email (email),
  UNIQUE KEY uq_rsvps_token (token_edicao_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recados (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(120) NOT NULL,
  mensagem    VARCHAR(600) NOT NULL,
  aprovado    TINYINT(1)   NOT NULL DEFAULT 0,
  token_hash  CHAR(64)     NULL COMMENT 'permite a quem escreveu desfazer o envio',
  ip_hash     CHAR(64)     NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_recados_aprovado (aprovado, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS padrinhos (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome   VARCHAR(160) NOT NULL,
  lado   ENUM('noiva','noivo') NOT NULL,
  foto   VARCHAR(120) NULL COMMENT 'nome do arquivo em assets/img/fotos/padrinhos/',
  ordem  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario     VARCHAR(60)  NOT NULL UNIQUE,
  senha_hash  VARCHAR(255) NOT NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite de tentativas por IP (hash) e ação: janela fixa.
CREATE TABLE IF NOT EXISTS limites (
  chave        CHAR(64)  NOT NULL PRIMARY KEY COMMENT 'sha256(acao|ip)',
  janela_inicio DATETIME NOT NULL,
  contagem     INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
