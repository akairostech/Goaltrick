-- =====================================================================
--  GoalTrick - Actualización de la base de datos de v1.9 a v1.10
--  (perfil público del mánager)
--
--  NORMALMENTE NO NECESITAS ESTE ARCHIVO: upgrade.php lo hace todo solo.
--  Si lo aplicas a mano: ejecuta este archivo UNA vez y abre igualmente upgrade.php.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN avatar SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `language`;
ALTER TABLE users ADD COLUMN country_id INT UNSIGNED NULL AFTER avatar;
ALTER TABLE users ADD COLUMN city VARCHAR(60) NULL AFTER country_id;
ALTER TABLE users ADD COLUMN birth_year SMALLINT UNSIGNED NULL AFTER city;
ALTER TABLE users ADD COLUMN motto VARCHAR(140) NULL AFTER birth_year;
ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER motto;
ALTER TABLE users ADD COLUMN fav_club VARCHAR(60) NULL AFTER bio;
ALTER TABLE users ADD COLUMN profile_views INT UNSIGNED NOT NULL DEFAULT 0 AFTER fav_club;

UPDATE users u JOIN teams t ON t.user_id = u.id SET u.country_id = t.country_id WHERE u.country_id IS NULL AND t.is_national = 0;
