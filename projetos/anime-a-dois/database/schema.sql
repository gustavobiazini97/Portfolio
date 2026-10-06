-- Anime a Dois — estrutura da base de dados (MySQL / MariaDB)
-- Não corras isto à mão: o database/migrate.php aplica-o na base de dados do config.php.
-- (A base de dados em si é criada antes: no WAMP/phpMyAdmin ou no painel do alojamento.)
-- Só usa CREATE TABLE IF NOT EXISTS, por isso pode correr-se várias vezes sem perder dados.

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

-- Fotos de perfil: uma por utilizador, guardada na base de dados
-- (uma pasta de uploads seria apagada a cada deploy, porque o rsync espelha o repositório)
CREATE TABLE IF NOT EXISTS fotos (
  user_id       INT UNSIGNED PRIMARY KEY,
  imagem        MEDIUMBLOB   NOT NULL,             -- já cortada ao quadrado e reduzida (320px)
  tipo          VARCHAR(20)  NOT NULL,             -- image/webp ou image/jpeg
  atualizada_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- entra no URL, para o browser não mostrar a antiga
  CONSTRAINT fk_foto_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Comentários sobre um episódio (os dois veem os de ambos)
CREATE TABLE IF NOT EXISTS comentarios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  episodio_id INT UNSIGNED NOT NULL,
  texto       VARCHAR(500) NOT NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comentarios_episodio (episodio_id, criado_em),
  CONSTRAINT fk_comentario_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_comentario_episodio FOREIGN KEY (episodio_id) REFERENCES episodios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Valores internos da app (ex.: as chaves VAPID das notificações, geradas no próprio servidor)
CREATE TABLE IF NOT EXISTS config_app (
  chave VARCHAR(50)  PRIMARY KEY,
  valor TEXT         NOT NULL
) ENGINE=InnoDB;

-- O que cada pessoa quer receber, e por onde (sem linha = valores por defeito)
CREATE TABLE IF NOT EXISTS preferencias (
  user_id    INT UNSIGNED PRIMARY KEY,
  email      VARCHAR(190) NULL,                    -- para onde vão os emails (opcional)
  ep_push    TINYINT(1)   NOT NULL DEFAULT 1,      -- episódios marcados → telemóvel
  ep_email   TINYINT(1)   NOT NULL DEFAULT 0,      -- episódios marcados → email
  com_push   TINYINT(1)   NOT NULL DEFAULT 1,      -- comentários → telemóvel
  com_email  TINYINT(1)   NOT NULL DEFAULT 0,      -- comentários → email
  CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sessões da API (app Flutter): um token por telemóvel com sessão iniciada.
-- Só se guarda o sha256 do token; o token em si fica apenas no telemóvel.
CREATE TABLE IF NOT EXISTS tokens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL UNIQUE,        -- sha256 do token enviado no Authorization
  dispositivo VARCHAR(80)  NULL,                   -- nome do telemóvel (opcional, para o perfil)
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em   DATETIME     NOT NULL,               -- renovado sempre que o token é usado
  KEY idx_tokens_user (user_id),
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Telemóveis/browsers com notificações ativas (uma pessoa pode ter vários)
CREATE TABLE IF NOT EXISTS subscricoes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  endpoint   TEXT         NOT NULL,                -- URL do serviço de push (FCM, Mozilla, ...)
  chave_hash CHAR(64)     NOT NULL UNIQUE,         -- sha256 do endpoint: evita duplicados
  p256dh     VARCHAR(255) NOT NULL,
  auth       VARCHAR(255) NOT NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
