-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.6 a v1.7
--  (alineación enviada para cada partido)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace todo solo.
--  Si lo aplicas a mano: ejecuta este archivo UNA vez y abre igualmente upgrade.php.
-- =====================================================================
SET NAMES utf8mb4;

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

INSERT IGNORE INTO match_lineups (team_id, match_id, positions, orders, tactic, attitude, captain_id, setpieces_id, updated_at)
SELECT l.team_id, l.for_match_id, l.positions, l.orders, l.tactic, l.attitude, l.captain_id, l.setpieces_id, l.updated_at
FROM team_lineups l JOIN matches m ON m.id = l.for_match_id WHERE m.status = 'scheduled';
