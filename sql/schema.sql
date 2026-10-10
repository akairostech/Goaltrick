-- =====================================================================
--  GoalTrick v1.4 - Esquema de base de datos (MySQL 5.7+ / 8.x / MariaDB 10.3+)
--  Puedes importar este archivo desde phpMyAdmin o con:
--     mysql -u root -p goaltrick < sql/schema.sql
--  (El instalador web install.php también lo ejecuta automáticamente)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Usuarios y seguridad
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username          VARCHAR(30)  NOT NULL,
    email             VARCHAR(190) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    is_admin          TINYINT(1)   NOT NULL DEFAULT 0,
    is_banned         TINYINT(1)   NOT NULL DEFAULT 0,
    ban_reason        VARCHAR(500) NULL,            -- motivo de la sanción (lo ve el usuario al intentar entrar)
    banned_until      DATETIME     NULL,            -- NULL = expulsión permanente (si is_banned = 1)
    banned_at         DATETIME     NULL,
    language          VARCHAR(8)   NULL,          -- idioma elegido (lang/<código>.php)
    created_at        DATETIME     NOT NULL,
    last_login_at     DATETIME     NULL,
    last_activity_at  DATETIME     NULL,
    reset_token_hash  VARCHAR(64)  NULL,
    reset_expires_at  DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de sanciones de cada usuario (avisos, suspensiones, expulsiones y readmisiones)
CREATE TABLE IF NOT EXISTS user_sanctions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NULL,
    action      VARCHAR(12)  NOT NULL,            -- warn | suspend | expel | unban
    reason      VARCHAR(500) NOT NULL,
    until_at    DATETIME     NULL,
    ticket_id   INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sanction_user (user_id, created_at),
    CONSTRAINT fk_sanction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accesos de cada usuario (IP y navegador) para detectar multicuentas
CREATE TABLE IF NOT EXISTS user_logins (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ul_user (user_id, created_at),
    KEY idx_ul_ip (ip),
    CONSTRAINT fk_ul_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ayuda y soporte: tickets de los usuarios y sus respuestas
CREATE TABLE IF NOT EXISTS support_tickets (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED NOT NULL,
    category          VARCHAR(16)  NOT NULL,       -- bug | report | account | transfer | suggestion | other
    subject           VARCHAR(150) NOT NULL,
    status            VARCHAR(10)  NOT NULL DEFAULT 'open',   -- open | answered | pending | closed
    reported_user_id  INT UNSIGNED NULL,            -- usuario denunciado
    link              VARCHAR(255) NULL,            -- enlace opcional (partido, equipo, jugador...)
    assigned_to       INT UNSIGNED NULL,            -- administrador que lo lleva
    created_at        DATETIME     NOT NULL,
    updated_at        DATETIME     NOT NULL,
    user_read_at      DATETIME     NULL,
    staff_read_at     DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_ticket_user (user_id, updated_at),
    KEY idx_ticket_status (status, updated_at),
    CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_replies (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    is_staff    TINYINT(1)   NOT NULL DEFAULT 0,
    is_internal TINYINT(1)   NOT NULL DEFAULT 0,   -- nota interna: sólo la ven los administradores
    body        TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reply_ticket (ticket_id, id),
    CONSTRAINT fk_reply_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    selector    CHAR(24)     NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_user (user_id),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip           VARCHAR(45)  NOT NULL,
    username     VARCHAR(190) NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Estado global del juego (clave/valor)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_state (
    k VARCHAR(64)  NOT NULL,
    v VARCHAR(1000) NOT NULL,
    PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calendario de eventos (jornadas, entrenamiento, economía, fin de temporada)
CREATE TABLE IF NOT EXISTS scheduled_events (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type        VARCHAR(20)  NOT NULL,  -- league_round | friendly_round | training | economy | season_end
    run_at      DATETIME     NOT NULL,
    season      INT UNSIGNED NOT NULL,
    week        TINYINT UNSIGNED NOT NULL,
    round       TINYINT UNSIGNED NULL,
    status      VARCHAR(10)  NOT NULL DEFAULT 'pending', -- pending | done
    executed_at DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_events_status (status, run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Mundo: países, series de liga
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS countries (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name  VARCHAR(60)  NOT NULL,
    code  CHAR(2)      NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS league_units (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_id  INT UNSIGNED NOT NULL,
    level       TINYINT UNSIGNED NOT NULL,  -- 1 = primera división
    number      SMALLINT UNSIGNED NOT NULL, -- número de serie dentro del nivel
    name        VARCHAR(20)  NOT NULL,      -- p.ej. "II.3"
    parent_id   INT UNSIGNED NULL,          -- serie superior (ascensos/descensos)
    PRIMARY KEY (id),
    UNIQUE KEY uq_unit (country_id, level, number),
    KEY idx_unit_parent (parent_id),
    CONSTRAINT fk_unit_country FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Equipos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS teams (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NULL,
    league_unit_id      INT UNSIGNED NULL,           -- NULL en las selecciones nacionales
    country_id          INT UNSIGNED NOT NULL,
    name                VARCHAR(40)  NOT NULL,
    short_name          VARCHAR(12)  NOT NULL,
    is_bot              TINYINT(1)   NOT NULL DEFAULT 1,
    is_national         TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = selección nacional
    cash                BIGINT       NOT NULL DEFAULT 0,
    fans                INT UNSIGNED NOT NULL DEFAULT 1000,
    fan_mood            DECIMAL(4,2) NOT NULL DEFAULT 5.00,  -- 0..10
    team_spirit         DECIMAL(4,2) NOT NULL DEFAULT 5.00,  -- 0..10
    confidence          DECIMAL(4,2) NOT NULL DEFAULT 5.00,  -- 0..10
    -- Personal
    trainer_level       TINYINT UNSIGNED NOT NULL DEFAULT 3,  -- 1..5
    assistants          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    physios             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    doctors             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    spokespersons       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    psychologists       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    -- Entrenamiento
    training_type       VARCHAR(20)  NOT NULL DEFAULT 'playmaking',
    training_intensity  TINYINT UNSIGNED NOT NULL DEFAULT 100,
    stamina_share       TINYINT UNSIGNED NOT NULL DEFAULT 15,
    -- Cantera
    youth_level         TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 0..3
    youth_points        DECIMAL(6,2) NOT NULL DEFAULT 0,
    youth_pulled_week   INT UNSIGNED NULL,
    -- Estadio
    stadium_name        VARCHAR(60)  NOT NULL,
    seats_terraces      INT UNSIGNED NOT NULL DEFAULT 8000,
    seats_basic         INT UNSIGNED NOT NULL DEFAULT 3000,
    seats_roof          INT UNSIGNED NOT NULL DEFAULT 800,
    seats_vip           INT UNSIGNED NOT NULL DEFAULT 200,
    build_terraces      INT NOT NULL DEFAULT 0,
    build_basic         INT NOT NULL DEFAULT 0,
    build_roof          INT NOT NULL DEFAULT 0,
    build_vip           INT NOT NULL DEFAULT 0,
    build_finishes_at   DATETIME NULL,
    -- Otros
    founded_at          DATETIME NOT NULL,
    negative_weeks      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    stadium_notice_at   DATETIME NULL,               -- último aviso sobre el aforo del estadio
    kit_id              SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- uniforme elegido (1..1000; 0 = el de por defecto)
    kit_custom          VARCHAR(120) NULL,                     -- uniforme subido por el club (assets/uploads/...)
    crest_id            SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- escudo elegido (1..1000; 0 = el de por defecto)
    crest_custom        VARCHAR(120) NULL,                     -- escudo subido por el club
    PRIMARY KEY (id),
    UNIQUE KEY uq_team_user (user_id),
    KEY idx_team_unit (league_unit_id),
    CONSTRAINT fk_team_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_team_unit FOREIGN KEY (league_unit_id) REFERENCES league_units(id),
    CONSTRAINT fk_team_country FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alineación guardada para el próximo partido
CREATE TABLE IF NOT EXISTS team_lineups (
    team_id        INT UNSIGNED NOT NULL,
    positions      TEXT NOT NULL,          -- JSON {"GK":id,"RB":id,...}
    orders         TEXT NULL,              -- JSON {"RW":"offensive",...}
    tactic         VARCHAR(20) NOT NULL DEFAULT 'normal',
    attitude       VARCHAR(10) NOT NULL DEFAULT 'normal', -- pic | normal | mots
    captain_id     INT UNSIGNED NULL,
    setpieces_id   INT UNSIGNED NULL,
    for_match_id   INT UNSIGNED NULL,      -- partido para el que se envió (si no, se usa la predeterminada)
    updated_at     DATETIME NOT NULL,
    PRIMARY KEY (team_id),
    CONSTRAINT fk_lineup_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alineación enviada para cada partido concreto (se borra cuando el partido se juega)
CREATE TABLE IF NOT EXISTS match_lineups (
    team_id        INT UNSIGNED NOT NULL,
    match_id       INT UNSIGNED NOT NULL,
    positions      TEXT NOT NULL,
    orders         TEXT NULL,
    tactic         VARCHAR(20) NOT NULL DEFAULT 'normal',
    attitude       VARCHAR(10) NOT NULL DEFAULT 'normal',
    captain_id     INT UNSIGNED NULL,
    setpieces_id   INT UNSIGNED NULL,
    updated_at     DATETIME NOT NULL,
    PRIMARY KEY (team_id, match_id),
    KEY idx_ml_match (match_id),
    CONSTRAINT fk_ml_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alineaciones guardadas: slot 0 = predeterminada, 1..3 = alineaciones libres del mánager
CREATE TABLE IF NOT EXISTS lineup_presets (
    team_id        INT UNSIGNED NOT NULL,
    slot           TINYINT UNSIGNED NOT NULL,
    name           VARCHAR(40)  NOT NULL,
    positions      TEXT NOT NULL,
    orders         TEXT NULL,
    tactic         VARCHAR(20) NOT NULL DEFAULT 'normal',
    attitude       VARCHAR(10) NOT NULL DEFAULT 'normal',
    captain_id     INT UNSIGNED NULL,
    setpieces_id   INT UNSIGNED NULL,
    updated_at     DATETIME NOT NULL,
    PRIMARY KEY (team_id, slot),
    CONSTRAINT fk_preset_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Noticias del club (subidas de habilidad, lesiones, fichajes...)
CREATE TABLE IF NOT EXISTS team_events (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id     INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL,
    type        VARCHAR(20) NOT NULL,
    message     VARCHAR(1500) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tevents_team (team_id, created_at),
    CONSTRAINT fk_tevents_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de temporadas por equipo
CREATE TABLE IF NOT EXISTS season_history (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id         INT UNSIGNED NOT NULL,
    season          INT UNSIGNED NOT NULL,
    league_unit_id  INT UNSIGNED NOT NULL,
    position        TINYINT UNSIGNED NOT NULL,
    points          TINYINT UNSIGNED NOT NULL,
    goals_for       SMALLINT UNSIGNED NOT NULL,
    goals_against   SMALLINT UNSIGNED NOT NULL,
    result          VARCHAR(12) NOT NULL DEFAULT 'stay', -- promoted | relegated | champion | stay
    PRIMARY KEY (id),
    KEY idx_shist_team (team_id),
    CONSTRAINT fk_shist_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Jugadores
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS players (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id         INT UNSIGNED NULL,
    mother_team_id  INT UNSIGNED NULL,
    country_id      INT UNSIGNED NOT NULL,
    first_name      VARCHAR(40) NOT NULL,
    last_name       VARCHAR(40) NOT NULL,
    avatar          SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- imagen assets/img/avatars/NNN.svg (1..400)
    age_years       TINYINT UNSIGNED NOT NULL,
    age_days        TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0..111 (un año = 112 días)
    -- Habilidades 0..20 con decimales (la parte decimal es el progreso)
    keeper          DECIMAL(5,2) NOT NULL DEFAULT 0,
    defending       DECIMAL(5,2) NOT NULL DEFAULT 0,
    playmaking      DECIMAL(5,2) NOT NULL DEFAULT 0,
    winger          DECIMAL(5,2) NOT NULL DEFAULT 0,
    passing         DECIMAL(5,2) NOT NULL DEFAULT 0,
    scoring         DECIMAL(5,2) NOT NULL DEFAULT 0,
    set_pieces      DECIMAL(5,2) NOT NULL DEFAULT 0,
    stamina         DECIMAL(5,2) NOT NULL DEFAULT 5,
    form            DECIMAL(4,2) NOT NULL DEFAULT 5,       -- 1..8
    experience      DECIMAL(5,2) NOT NULL DEFAULT 1,
    leadership      TINYINT UNSIGNED NOT NULL DEFAULT 3,
    loyalty         DECIMAL(4,2) NOT NULL DEFAULT 5,
    specialty       VARCHAR(16) NULL,  -- technical | quick | powerful | unpredictable | head | resilient
    role            VARCHAR(4)  NOT NULL DEFAULT 'IM', -- posición natural: GK, CD, WB, IM, W, FW
    injury_weeks    DECIMAL(4,1) NOT NULL DEFAULT 0,
    yellow_cards    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    suspended       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    tsi             INT UNSIGNED NOT NULL DEFAULT 0,
    wage            INT UNSIGNED NOT NULL DEFAULT 250,
    goals_season    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    goals_career    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    caps            SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- partidos con la selección
    goals_national  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    matches_career  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    joined_at       DATETIME NOT NULL,
    created_at      DATETIME NOT NULL,
    retired         TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_players_team (team_id),
    KEY idx_players_nation (country_id, role, tsi),
    CONSTRAINT fk_players_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_players_country FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_events (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id   INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL,
    message     VARCHAR(1000) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_pevents_player (player_id),
    CONSTRAINT fk_pevents_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Partidos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matches (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season           INT UNSIGNED NOT NULL,
    week             TINYINT UNSIGNED NOT NULL,
    round            TINYINT UNSIGNED NULL,
    type             VARCHAR(10) NOT NULL DEFAULT 'league', -- league | friendly | worldcup
    league_unit_id   INT UNSIGNED NULL,
    cup_id           INT UNSIGNED NULL,          -- id del Mundial (tabla worldcups)
    cup_stage        VARCHAR(4)  NULL,           -- Mundial: A..H (grupo, F1 = grupo F), R16, QF, SF, 3P, F · Copa: R1, R128... F
    home_team_id     INT UNSIGNED NOT NULL,
    away_team_id     INT UNSIGNED NOT NULL,
    scheduled_at     DATETIME NOT NULL,
    status           VARCHAR(10) NOT NULL DEFAULT 'scheduled', -- scheduled | played
    home_goals       TINYINT UNSIGNED NULL,
    away_goals       TINYINT UNSIGNED NULL,
    home_pens        TINYINT UNSIGNED NULL,      -- tanda de penaltis (eliminatorias)
    away_pens        TINYINT UNSIGNED NULL,
    attendance       INT UNSIGNED NULL,
    home_possession  TINYINT UNSIGNED NULL,
    home_tactic      VARCHAR(20) NULL,
    away_tactic      VARCHAR(20) NULL,
    ratings          TEXT NULL,   -- JSON con las valoraciones por sector
    report           MEDIUMTEXT NULL, -- JSON con los eventos minuto a minuto
    played_at        DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_matches_status (status, scheduled_at),
    KEY idx_matches_unit (league_unit_id, season, round),
    KEY idx_matches_home (home_team_id, scheduled_at),
    KEY idx_matches_away (away_team_id, scheduled_at),
    KEY idx_matches_played (played_at),
    KEY idx_matches_cup (cup_id, round),
    CONSTRAINT fk_matches_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_players (
    match_id   INT UNSIGNED NOT NULL,
    player_id  INT UNSIGNED NOT NULL,
    team_id    INT UNSIGNED NOT NULL,
    position   VARCHAR(4)  NOT NULL,
    minutes    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    stars      DECIMAL(3,1) NOT NULL DEFAULT 0,
    goals      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    yellow     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    red        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    injured    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (match_id, player_id),
    KEY idx_mp_player (player_id),
    KEY idx_mp_team (team_id, match_id),
    CONSTRAINT fk_mp_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS friendly_requests (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_team_id  INT UNSIGNED NOT NULL,
    to_team_id    INT UNSIGNED NOT NULL,
    status        VARCHAR(10) NOT NULL DEFAULT 'pending', -- pending | accepted | rejected | expired
    created_at    DATETIME NOT NULL,
    match_id      INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_freq_to (to_team_id, status),
    CONSTRAINT fk_freq_from FOREIGN KEY (from_team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_freq_to FOREIGN KEY (to_team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Mercado de fichajes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transfer_listings (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id          INT UNSIGNED NOT NULL,
    seller_team_id     INT UNSIGNED NOT NULL,
    asking_price       BIGINT NOT NULL,
    highest_bid        BIGINT NULL,
    highest_bidder_id  INT UNSIGNED NULL,
    deadline           DATETIME NOT NULL,
    status             VARCHAR(10) NOT NULL DEFAULT 'active', -- active | sold | unsold | cancelled
    created_at         DATETIME NOT NULL,
    closed_at          DATETIME NULL,
    tsi_at_sale        INT UNSIGNED NULL,       -- TSI del jugador en el momento del traspaso
    age_at_sale        TINYINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_tl_status (status, deadline),
    KEY idx_tl_player (player_id),
    CONSTRAINT fk_tl_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_tl_seller FOREIGN KEY (seller_team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transfer_bids (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id  INT UNSIGNED NOT NULL,
    team_id     INT UNSIGNED NOT NULL,
    amount      BIGINT NOT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tb_listing (listing_id, amount),
    KEY idx_tb_team (team_id),
    CONSTRAINT fk_tb_listing FOREIGN KEY (listing_id) REFERENCES transfer_listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_tb_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Finanzas (libro de movimientos)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finances (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id     INT UNSIGNED NOT NULL,
    season      INT UNSIGNED NOT NULL,
    week        TINYINT UNSIGNED NOT NULL,
    category    VARCHAR(20) NOT NULL,   -- tickets | sponsors | transfers_in | transfers_out | wages | staff | stadium | construction | youth | prizes | other
    amount      BIGINT NOT NULL,        -- positivo = ingreso, negativo = gasto
    description VARCHAR(600) NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fin_team (team_id, season, week),
    CONSTRAINT fk_fin_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Comunidad: mensajes privados y anuncios
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_user_id       INT UNSIGNED NULL,
    to_user_id         INT UNSIGNED NOT NULL,
    subject            VARCHAR(500) NOT NULL,
    body               TEXT NOT NULL,
    created_at         DATETIME NOT NULL,
    read_at            DATETIME NULL,
    deleted_by_sender  TINYINT(1) NOT NULL DEFAULT 0,
    deleted_by_recipient TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_msg_to (to_user_id, read_at),
    KEY idx_msg_from (from_user_id),
    CONSTRAINT fk_msg_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_msg_to FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    title       VARCHAR(500) NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Mundial de fútbol (selecciones nacionales)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worldcups (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season            INT UNSIGNED NOT NULL,
    status            VARCHAR(10) NOT NULL DEFAULT 'groups', -- groups | knockout | finished
    champion_team_id  INT UNSIGNED NULL,
    runnerup_team_id  INT UNSIGNED NULL,
    third_team_id     INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wc_season (season)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worldcup_teams (
    cup_id        INT UNSIGNED NOT NULL,
    team_id       INT UNSIGNED NOT NULL,
    group_letter  VARCHAR(2) NOT NULL,
    pot           TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (cup_id, team_id),
    CONSTRAINT fk_wct_cup FOREIGN KEY (cup_id) REFERENCES worldcups(id) ON DELETE CASCADE,
    CONSTRAINT fk_wct_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Patrocinio: empresas y contratos (3 ofertas por temporada)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS companies (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name      VARCHAR(60)  NOT NULL,
    sector    VARCHAR(40)  NOT NULL,
    prestige  TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- 1 local ... 5 multinacional
    color     VARCHAR(7)   NOT NULL DEFAULT '#1d4ed8',
    slogan    VARCHAR(120) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_name (name),
    KEY idx_company_prestige (prestige)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_contracts (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id        INT UNSIGNED NOT NULL,
    company_id     INT UNSIGNED NOT NULL,
    type           VARCHAR(12) NOT NULL,              -- stable | performance | long
    season_from    INT UNSIGNED NOT NULL,
    season_to      INT UNSIGNED NOT NULL,
    weekly_amount  INT UNSIGNED NOT NULL,
    signing_bonus  INT UNSIGNED NOT NULL DEFAULT 0,
    win_bonus      INT UNSIGNED NOT NULL DEFAULT 0,  -- por victoria en liga
    title_bonus    INT UNSIGNED NOT NULL DEFAULT 0,  -- por ser campeón de su serie
    status         VARCHAR(10) NOT NULL DEFAULT 'offer', -- offer | active | ended | rejected
    expires_at     DATETIME NULL,                     -- fecha límite para firmar una oferta
    total_paid     BIGINT NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL,
    signed_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sc_team (team_id, status),
    KEY idx_sc_status (status, expires_at),
    CONSTRAINT fk_sc_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_sc_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cuerpo técnico (entrenador y personal con perfil)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id     INT UNSIGNED NOT NULL,
    role        VARCHAR(14) NOT NULL,          -- trainer | assistant | physio | doctor | spokesperson | psychologist
    first_name  VARCHAR(40) NOT NULL,
    last_name   VARCHAR(40) NOT NULL,
    age         TINYINT UNSIGNED NOT NULL,
    country_id  INT UNSIGNED NOT NULL,
    personality VARCHAR(14) NOT NULL,          -- carácter (ver StaffService::PERSONALITIES)
    level       TINYINT UNSIGNED NOT NULL DEFAULT 3,  -- calidad 1..5 (en el entrenador, su nivel)
    avatar      SMALLINT UNSIGNED NOT NULL DEFAULT 1,  -- assets/img/staff/NNN.svg
    hired_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_staff_team (team_id, role),
    CONSTRAINT fk_staff_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_country FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Copa de liga (eliminación directa, todos los equipos de un país)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS league_cups (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season            INT UNSIGNED NOT NULL,
    country_id        INT UNSIGNED NOT NULL,
    status            VARCHAR(10) NOT NULL DEFAULT 'playing', -- playing | finished
    current_round     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    winner_team_id    INT UNSIGNED NULL,
    runnerup_team_id  INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cup_season_country (season, country_id),
    CONSTRAINT fk_lc_country FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cup_entries (
    cup_id            INT UNSIGNED NOT NULL,
    team_id           INT UNSIGNED NOT NULL,
    seed              SMALLINT UNSIGNED NOT NULL,
    eliminated_round  TINYINT UNSIGNED NULL,   -- NULL = sigue vivo
    PRIMARY KEY (cup_id, team_id),
    KEY idx_ce_alive (cup_id, eliminated_round),
    KEY idx_ce_team (team_id),
    CONSTRAINT fk_ce_cup FOREIGN KEY (cup_id) REFERENCES league_cups(id) ON DELETE CASCADE,
    CONSTRAINT fk_ce_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
