-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.1 a v1.2
--  (imágenes de perfil de los jugadores)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace solo.
--  Si lo aplicas a mano, ejecútalo UNA sola vez.
-- =====================================================================
ALTER TABLE players ADD COLUMN avatar TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_name;
UPDATE players SET avatar = 1 + FLOOR(RAND() * 50) WHERE avatar = 0;
UPDATE game_state SET v = '3' WHERE k = 'db_version';
