-- Anime a Dois — estrutura da base de dados (MySQL / MariaDB)
-- Correr uma vez: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS anime_a_dois
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE anime_a_dois;

-- Contas (no máximo duas; o limite é aplicado no Model User)
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(40)  NOT NULL,             -- nome que aparece na app (personalizado)
  username      VARCHAR(30)  NOT NULL UNIQUE,      -- usado para entrar
  password_hash VARCHAR(255) NOT NULL,             -- password_hash() do PHP, nunca a palavra-passe
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Séries (Naruto, Shippuden, Boruto)
CREATE TABLE IF NOT EXISTS series (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(60)  NOT NULL UNIQUE,    -- usado no URL e no filtro
  nome            VARCHAR(80)  NOT NULL,
  anos            VARCHAR(20)  NULL,
  total_episodios SMALLINT UNSIGNED NOT NULL,
  ordem           TINYINT UNSIGNED NOT NULL DEFAULT 0  -- ordem no filtro
) ENGINE=InnoDB;

-- Um registo por episódio; os fillers vêm marcados do Naruto Fillers
CREATE TABLE IF NOT EXISTS episodios (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  serie_id INT UNSIGNED NOT NULL,
  numero   SMALLINT UNSIGNED NOT NULL,
  titulo   VARCHAR(200) NULL,                      -- só os fillers têm título nos dados atuais
  filler   TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_serie_numero (serie_id, numero),
  CONSTRAINT fk_episodio_serie FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Episódios vistos: existe a linha = está visto; visto_em dá o "último visto"
CREATE TABLE IF NOT EXISTS vistos (
  user_id     INT UNSIGNED NOT NULL,
  episodio_id INT UNSIGNED NOT NULL,
  visto_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, episodio_id),
  KEY idx_vistos_user_data (user_id, visto_em),   -- acelera o "último visto" de cada um
  CONSTRAINT fk_visto_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_visto_episodio FOREIGN KEY (episodio_id) REFERENCES episodios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
