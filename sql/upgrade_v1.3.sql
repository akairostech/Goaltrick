-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.2 a v1.3
--  (400 avatares y sistema de patrocinio)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace solo (y además
--  envía las ofertas de patrocinio a todos los clubes).
--  Si lo aplicas a mano: ejecuta este archivo UNA vez, después sql/companies.sql
--  y abre igualmente upgrade.php.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE players MODIFY avatar SMALLINT UNSIGNED NOT NULL DEFAULT 0;
UPDATE players SET avatar = 1 + FLOOR(RAND() * 400);

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

