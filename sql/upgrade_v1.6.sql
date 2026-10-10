-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.5 a v1.6
--  (uniformes, escudos e historial de transferencias)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace todo solo.
--  Si lo aplicas a mano: ejecuta este archivo UNA vez y abre igualmente upgrade.php.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE teams ADD COLUMN kit_id SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER stadium_notice_at;
ALTER TABLE teams ADD COLUMN kit_custom VARCHAR(120) NULL AFTER kit_id;
ALTER TABLE teams ADD COLUMN crest_id SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER kit_custom;
ALTER TABLE teams ADD COLUMN crest_custom VARCHAR(120) NULL AFTER crest_id;
ALTER TABLE transfer_listings ADD COLUMN tsi_at_sale INT UNSIGNED NULL AFTER closed_at;
ALTER TABLE transfer_listings ADD COLUMN age_at_sale TINYINT UNSIGNED NULL AFTER tsi_at_sale;

UPDATE transfer_listings l JOIN players p ON p.id = l.player_id
   SET l.tsi_at_sale = p.tsi, l.age_at_sale = p.age_years
 WHERE l.status = 'sold' AND l.tsi_at_sale IS NULL;
