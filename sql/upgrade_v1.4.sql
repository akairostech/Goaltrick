-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.3 a v1.4
--  (Copa, perfiles del personal, entradas dinámicas y 32 naciones)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace todo solo
--  (además crea las ligas de las naciones nuevas, da un perfil a todo el
--  personal existente y sortea la Copa de cada país).
--  Si lo aplicas a mano: ejecuta este archivo UNA vez, después sql/seed.sql
--  y abre igualmente upgrade.php.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE teams ADD COLUMN stadium_notice_at DATETIME NULL AFTER negative_weeks;
ALTER TABLE worldcup_teams MODIFY group_letter VARCHAR(2) NOT NULL;

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
