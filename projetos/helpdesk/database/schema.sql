-- Esquema da base de dados do Helpdesk TI (MySQL 8 / MariaDB 10.4+).
-- Corre com: php database/migrate.php  (cria tudo o que faltar; não apaga dados)
-- Todas as datas são escritas pelo PHP no fuso do config (nunca NOW() do MySQL).

-- Contas: funcionários, técnicos de TI e administradores
CREATE TABLE IF NOT EXISTS utilizadores (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    papel           ENUM('utilizador','tecnico','admin') NOT NULL DEFAULT 'utilizador',
    departamento    VARCHAR(80) NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    mudar_password  TINYINT(1) NOT NULL DEFAULT 1,      -- palavra-passe temporária: obriga a trocar no 1.º login
    totp_segredo    VARCHAR(64) NULL,                    -- segredo do 2FA (Base32)
    totp_ativo      TINYINT(1) NOT NULL DEFAULT 0,
    totp_ultimo     BIGINT NULL,                         -- último passo TOTP usado (contra reutilização)
    notif_email     TINYINT(1) NOT NULL DEFAULT 1,       -- quer receber as notificações também por email
    ultimo_login    DATETIME NULL,
    criado_em       DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting e bloqueio progressivo do login (chaves em hash)
CREATE TABLE IF NOT EXISTS limites (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave       CHAR(64) NOT NULL,
    criado_em   DATETIME NOT NULL,
    INDEX idx_limites (chave, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links de recuperação de palavra-passe (guardados em hash, uso único, 30 minutos)
CREATE TABLE IF NOT EXISTS tokens_reset (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizador_id   INT UNSIGNED NOT NULL,
    token_hash      CHAR(64) NOT NULL UNIQUE,
    expira_em       DATETIME NOT NULL,
    usado_em        DATETIME NULL,
    criado_em       DATETIME NOT NULL,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias dos pedidos (Hardware, Impressoras, Email…), com técnico atribuído por defeito
CREATE TABLE IF NOT EXISTS categorias (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(80) NOT NULL,
    descricao           VARCHAR(255) NULL,
    tecnico_padrao_id   INT UNSIGNED NULL,
    ativa               TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (tecnico_padrao_id) REFERENCES utilizadores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prazos do SLA por prioridade, em minutos de horário de trabalho
CREATE TABLE IF NOT EXISTS sla_politicas (
    prioridade      TINYINT UNSIGNED PRIMARY KEY,        -- 1 = Crítica … 4 = Baixa
    resposta_min    INT UNSIGNED NOT NULL,               -- até à 1.ª resposta do TI
    resolucao_min   INT UNSIGNED NOT NULL                -- até ficar resolvido
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Os pedidos (tickets)
CREATE TABLE IF NOT EXISTS tickets (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo                  VARCHAR(150) NOT NULL,
    descricao               TEXT NOT NULL,
    categoria_id            INT UNSIGNED NULL,
    requerente_id           INT UNSIGNED NOT NULL,       -- quem abriu
    tecnico_id              INT UNSIGNED NULL,           -- quem está a tratar
    impacto                 TINYINT UNSIGNED NOT NULL,   -- 1..3
    urgencia                TINYINT UNSIGNED NOT NULL,   -- 1..3
    prioridade              TINYINT UNSIGNED NOT NULL,   -- 1..4 (da matriz, ou alterada pelo técnico)
    estado                  ENUM('novo','em_curso','aguarda_utilizador','aguarda_terceiros','resolvido','fechado') NOT NULL DEFAULT 'novo',
    prazo_resposta          DATETIME NOT NULL,
    prazo_resolucao         DATETIME NOT NULL,
    sla_pausado_em          DATETIME NULL,               -- preenchido enquanto está "à espera"
    sla_pausa_min           INT UNSIGNED NOT NULL DEFAULT 0,  -- minutos úteis já em pausa (empurram o prazo)
    alerta_resposta         TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0 nada, 1 aviso 80%, 2 ultrapassado
    alerta_resolucao        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    primeira_resposta_em    DATETIME NULL,
    resolvido_em            DATETIME NULL,
    fechado_em              DATETIME NULL,
    avaliacao               TINYINT UNSIGNED NULL,       -- 1..5, dada pelo funcionário ao confirmar
    avaliacao_comentario    VARCHAR(500) NULL,
    criado_em               DATETIME NOT NULL,
    atualizado_em           DATETIME NOT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (requerente_id) REFERENCES utilizadores(id),
    FOREIGN KEY (tecnico_id) REFERENCES utilizadores(id) ON DELETE SET NULL,
    INDEX idx_tickets_fila (estado, prioridade, prazo_resolucao),
    INDEX idx_tickets_requerente (requerente_id, estado),
    INDEX idx_tickets_tecnico (tecnico_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversa do pedido: respostas públicas e notas internas (só o TI vê)
CREATE TABLE IF NOT EXISTS comentarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL,
    autor_id    INT UNSIGNED NOT NULL,
    corpo       TEXT NOT NULL,
    interno     TINYINT(1) NOT NULL DEFAULT 0,
    criado_em   DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES utilizadores(id),
    INDEX idx_comentarios_ticket (ticket_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anexos (o ficheiro fica em storage/anexos com nome aleatório)
CREATE TABLE IF NOT EXISTS anexos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id       INT UNSIGNED NOT NULL,
    comentario_id   INT UNSIGNED NULL,
    nome_original   VARCHAR(150) NOT NULL,
    nome_disco      VARCHAR(40) NOT NULL UNIQUE,
    mime            VARCHAR(100) NOT NULL,
    tamanho         INT UNSIGNED NOT NULL,
    enviado_por     INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (comentario_id) REFERENCES comentarios(id) ON DELETE SET NULL,
    FOREIGN KEY (enviado_por) REFERENCES utilizadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de cada pedido (quem mudou o quê e quando)
CREATE TABLE IF NOT EXISTS historico (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id       INT UNSIGNED NOT NULL,
    utilizador_id   INT UNSIGNED NULL,                   -- NULL = sistema (fecho automático, SLA)
    acao            VARCHAR(40) NOT NULL,
    de              VARCHAR(150) NULL,
    para            VARCHAR(150) NULL,
    criado_em       DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE SET NULL,
    INDEX idx_historico_ticket (ticket_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notificações dentro da app (o sino)
CREATE TABLE IF NOT EXISTS notificacoes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizador_id   INT UNSIGNED NOT NULL,
    ticket_id       INT UNSIGNED NULL,
    mensagem        VARCHAR(255) NOT NULL,
    lida_em         DATETIME NULL,
    criado_em       DATETIME NOT NULL,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_notificacoes (utilizador_id, lida_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila de emails a enviar em segundo plano
CREATE TABLE IF NOT EXISTS fila_emails (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    para        VARCHAR(190) NOT NULL,
    assunto     VARCHAR(200) NOT NULL,
    corpo       TEXT NOT NULL,
    tentativas  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    erro        VARCHAR(255) NULL,
    enviado_em  DATETIME NULL,
    criado_em   DATETIME NOT NULL,
    INDEX idx_fila (enviado_em, tentativas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FAQ / base de conhecimento
CREATE TABLE IF NOT EXISTS faq_artigos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(150) NOT NULL,
    resumo          VARCHAR(255) NOT NULL,               -- uma linha, mostrada nas sugestões
    conteudo        TEXT NOT NULL,
    palavras_chave  VARCHAR(255) NULL,                   -- sinónimos que as pessoas escrevem ("net", "wifi"…)
    categoria_id    INT UNSIGNED NULL,
    publicado       TINYINT(1) NOT NULL DEFAULT 1,
    autor_id        INT UNSIGNED NULL,
    visualizacoes   INT UNSIGNED NOT NULL DEFAULT 0,
    util_sim        INT UNSIGNED NOT NULL DEFAULT 0,
    util_nao        INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em       DATETIME NOT NULL,
    atualizado_em   DATETIME NOT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (autor_id) REFERENCES utilizadores(id) ON DELETE SET NULL,
    FULLTEXT INDEX ft_faq (titulo, resumo, conteudo, palavras_chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Resolveu-me o problema, já não preciso de abrir pedido" (pedidos evitados pela FAQ)
CREATE TABLE IF NOT EXISTS faq_desvios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artigo_id       INT UNSIGNED NOT NULL,
    utilizador_id   INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL,
    FOREIGN KEY (artigo_id) REFERENCES faq_artigos(id) ON DELETE CASCADE,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoria: logins falhados, acessos negados, mudanças de permissões…
CREATE TABLE IF NOT EXISTS auditoria (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizador_id   INT UNSIGNED NULL,
    acao            VARCHAR(40) NOT NULL,
    detalhe         VARCHAR(255) NULL,
    ip              VARCHAR(45) NOT NULL,
    criado_em       DATETIME NOT NULL,
    INDEX idx_auditoria (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
