-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.4 a v1.5
--  (idiomas, alineaciones guardadas)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace todo solo
--  (además vuelve a sortear el Mundial con las 32 selecciones).
--  Si lo aplicas a mano: ejecuta este archivo UNA vez y abre igualmente upgrade.php.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN language VARCHAR(8) NULL AFTER is_banned;
ALTER TABLE team_lineups ADD COLUMN for_match_id INT UNSIGNED NULL AFTER setpieces_id;
ALTER TABLE game_state MODIFY v VARCHAR(1000) NOT NULL;
ALTER TABLE announcements MODIFY title VARCHAR(500) NOT NULL;
ALTER TABLE team_events MODIFY message VARCHAR(1500) NOT NULL;
ALTER TABLE player_events MODIFY message VARCHAR(1000) NOT NULL;
ALTER TABLE finances MODIFY description VARCHAR(600) NULL;
ALTER TABLE messages MODIFY subject VARCHAR(500) NOT NULL;

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
