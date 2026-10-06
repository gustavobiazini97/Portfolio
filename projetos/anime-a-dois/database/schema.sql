-- Anime a Dois — estrutura da base de dados (MySQL / MariaDB)
-- Não corras isto à mão: o database/migrate.php aplica-o na base de dados do config.php.
-- (A base de dados em si é criada antes: no WAMP/phpMyAdmin ou no painel do alojamento.)
-- Só usa CREATE TABLE IF NOT EXISTS, por isso pode correr-se várias vezes sem perder dados.

-- Contas: o par (as duas primeiras) e os amigos que entram por link de convite (ver amizades)
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(40)  NOT NULL,             -- nome que aparece na app (personalizado)
  username      VARCHAR(30)  NOT NULL UNIQUE,      -- usado para entrar
  password_hash VARCHAR(255) NOT NULL,             -- password_hash() do PHP, nunca a palavra-passe
  novidades_vistas SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- id da última novidade que já viu (database/novidades.json)
  par_id        INT UNSIGNED NULL,                 -- o par (a pessoa com quem partilha séries); null = sem par
  so_juntos     TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = os amigos só veem as séries que vê com eles (e o último episódio dessas)
  paleta        TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- cores da app que ele escolheu (índice em User::PALETAS)
  admin         TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = tem acesso ao backoffice (?c=admin)
  ultimo_acesso DATETIME     NULL,                 -- última vez que abriu a app (atualizado no máximo de 5 em 5 min)
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Séries da biblioteca (as três do Naruto vêm do seed; as outras chegam pela pesquisa no MyAnimeList).
-- As colunas novas também estão em database/migrate.php, que as junta a bases de dados antigas.
CREATE TABLE IF NOT EXISTS series (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(60)  NOT NULL UNIQUE,    -- usado no URL
  nome            VARCHAR(80)  NOT NULL,
  anos            VARCHAR(20)  NULL,
  total_episodios SMALLINT UNSIGNED NOT NULL,
  ordem           TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- desempate na fila da biblioteca
  mal_id          INT UNSIGNED NULL UNIQUE,         -- id no MyAnimeList (evita adicionar a mesma série duas vezes)
  capa            VARCHAR(255) NULL,                -- URL da capa (CDN do MyAnimeList)
  tipo            VARCHAR(20)  NULL,                -- TV, Movie, OVA, ONA...
  minutos_ep      TINYINT UNSIGNED NULL,            -- duração de um episódio (as Estatísticas usam 23 se faltar)
  em_emissao      TINYINT(1)   NOT NULL DEFAULT 0,  -- ainda a sair: os episódios novos são buscados de vez em quando
  estado          VARCHAR(10)  NOT NULL DEFAULT 'a_ver',   -- ANTIGO (estado partilhado); agora o estado vive em bibliotecas
  acento          TINYINT UNSIGNED NULL,            -- cor da série (1–8, ver [data-acento] no CSS)
  adicionada_por  INT UNSIGNED NULL,                -- quem adicionou (ou propôs)
  adicionada_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sincronizada_em DATETIME     NULL                 -- última vez que os episódios vieram do MyAnimeList
) ENGINE=InnoDB;

-- Um registo por episódio; fillers do Naruto Fillers (Naruto) ou do MyAnimeList (as outras)
CREATE TABLE IF NOT EXISTS episodios (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  serie_id INT UNSIGNED NOT NULL,
  numero   SMALLINT UNSIGNED NOT NULL,
  titulo   VARCHAR(200) NULL,
  filler   TINYINT(1)   NOT NULL DEFAULT 0,
  recap    TINYINT(1)   NOT NULL DEFAULT 0,         -- episódio de resumo (só vem do MyAnimeList)
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
  amigo_push  TINYINT(1)  NOT NULL DEFAULT 1,      -- pedidos de amizade (recebido / aceite) → telemóvel
  amigo_email TINYINT(1)  NOT NULL DEFAULT 0,      -- pedidos de amizade → email
  serie_push  TINYINT(1)  NOT NULL DEFAULT 1,      -- séries adicionadas/propostas → telemóvel
  serie_email TINYINT(1)  NOT NULL DEFAULT 0,      -- séries adicionadas/propostas → email
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

-- Biblioteca de cada pessoa: que séries tem e em que estado (cada um tem o seu).
-- (com_id: ANTIGO, já não se usa; quem vê a série com quem está em series_juntos.)
CREATE TABLE IF NOT EXISTS bibliotecas (
  user_id  INT UNSIGNED NOT NULL,
  serie_id INT UNSIGNED NOT NULL,
  estado   VARCHAR(10)  NOT NULL DEFAULT 'a_ver',     -- a_ver | pausa | acabado
  desde    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- quando entrou
  com_id   INT UNSIGNED NULL,                         -- com quem a vê (outro utilizador), ou null se a vê sozinho
  PRIMARY KEY (user_id, serie_id),
  KEY idx_bibliotecas_serie (serie_id),
  CONSTRAINT fk_bib_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_bib_serie FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Amizades: uma linha por sentido (A→B e B→A), para consultar "os amigos de X" com uma só condição
CREATE TABLE IF NOT EXISTS amizades (
  user_id   INT UNSIGNED NOT NULL,
  amigo_id  INT UNSIGNED NOT NULL,
  desde     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, amigo_id),
  CONSTRAINT fk_amizade_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_amizade_amigo FOREIGN KEY (amigo_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pedidos de amizade pendentes (quem já tem conta e foi procurado pelo utilizador exato)
CREATE TABLE IF NOT EXISTS pedidos_amizade (
  de_id     INT UNSIGNED NOT NULL,
  para_id   INT UNSIGNED NOT NULL,
  criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (de_id, para_id),
  CONSTRAINT fk_pedido_de   FOREIGN KEY (de_id)   REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pedido_para FOREIGN KEY (para_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Links de convite para criar conta: uso único e com validade; quem os usa fica amigo de quem convidou
CREATE TABLE IF NOT EXISTS convites_amigo (
  codigo    CHAR(32)     PRIMARY KEY,               -- aleatório, vai no link
  de_id     INT UNSIGNED NOT NULL,
  criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em DATETIME     NOT NULL,
  usado_por INT UNSIGNED NULL,
  CONSTRAINT fk_convite_de FOREIGN KEY (de_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Convites "Quero ver contigo": de_id convida para_id a ver esta série juntos (aceitar liga as duas bibliotecas)
CREATE TABLE IF NOT EXISTS convites_serie (
  serie_id  INT UNSIGNED NOT NULL,
  de_id     INT UNSIGNED NOT NULL,
  para_id   INT UNSIGNED NOT NULL,
  criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (serie_id, de_id, para_id),
  KEY idx_convites_para (para_id),
  CONSTRAINT fk_cs_serie FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_de    FOREIGN KEY (de_id)    REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_cs_para  FOREIGN KEY (para_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- Quem vê cada série com quem (par ou amigos). Uma linha por sentido (A→B e B→A); a mesma série pode ser
-- vista com várias pessoas, e cada ligação é de duas pessoas.
CREATE TABLE IF NOT EXISTS series_juntos (
  serie_id INT UNSIGNED NOT NULL,
  user_id  INT UNSIGNED NOT NULL,
  com_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (serie_id, user_id, com_id),
  KEY idx_juntos_user (user_id),
  CONSTRAINT fk_sj_serie FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE CASCADE,
  CONSTRAINT fk_sj_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_sj_com   FOREIGN KEY (com_id)   REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- "Manter sessão iniciada": um cookie com seletor + validador (só o hash do validador fica aqui).
-- Quando a sessão do PHP expira (o servidor apaga-a ao fim de pouco tempo), o cookie volta a iniciá-la.
CREATE TABLE IF NOT EXISTS sessoes_longas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  seletor       CHAR(24)     NOT NULL UNIQUE,      -- identifica a linha (vai no cookie)
  validador_hash CHAR(64)    NOT NULL,             -- sha256 do segredo que vai no cookie
  expira_em     DATETIME     NOT NULL,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sl_user (user_id),
  CONSTRAINT fk_sl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
