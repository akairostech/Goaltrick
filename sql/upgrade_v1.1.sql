-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.0 a v1.1
--  (Naciones, ligas por país, selecciones nacionales y Mundial)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: al abrir la web después de
--  actualizar los archivos, se te redirigirá a upgrade.php, que hace todo
--  esto automáticamente y además genera las ligas de los nuevos países,
--  las selecciones y el calendario del Mundial.
--
--  Si prefieres aplicar los cambios de tablas a mano (phpMyAdmin), ejecuta
--  este archivo UNA sola vez y después abre igualmente upgrade.php para
--  que se generen las ligas y el Mundial.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE teams MODIFY league_unit_id INT UNSIGNED NULL;
ALTER TABLE teams ADD COLUMN is_national TINYINT(1) NOT NULL DEFAULT 0 AFTER is_bot;

ALTER TABLE players ADD COLUMN caps SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER goals_career;
ALTER TABLE players ADD COLUMN goals_national SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER caps;
ALTER TABLE players ADD KEY idx_players_nation (country_id, role, tsi);

ALTER TABLE matches ADD COLUMN cup_id INT UNSIGNED NULL AFTER league_unit_id;
ALTER TABLE matches ADD COLUMN cup_stage VARCHAR(4) NULL AFTER cup_id;
ALTER TABLE matches ADD COLUMN home_pens TINYINT UNSIGNED NULL AFTER away_goals;
ALTER TABLE matches ADD COLUMN away_pens TINYINT UNSIGNED NULL AFTER home_pens;
ALTER TABLE matches ADD KEY idx_matches_cup (cup_id, round);

CREATE TABLE IF NOT EXISTS worldcups (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season            INT UNSIGNED NOT NULL,
    status            VARCHAR(10) NOT NULL DEFAULT 'groups',
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
    group_letter  CHAR(1) NOT NULL,
    pot           TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (cup_id, team_id),
    CONSTRAINT fk_wct_cup FOREIGN KEY (cup_id) REFERENCES worldcups(id) ON DELETE CASCADE,
    CONSTRAINT fk_wct_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Futbolandia pasa a ser Panamá y se añade Costa Rica
UPDATE countries SET name = 'Panamá', code = 'PA' WHERE id = 1;
INSERT INTO countries (id, name, code) VALUES (16, 'Costa Rica', 'CR')
ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code);
