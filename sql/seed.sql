-- =====================================================================
--  GoalTrick - Datos iniciales
--  Cada país tiene su propia liga y su selección nacional, que juega
--  el Mundial. El país con id = 1 (Panamá) es el principal: su liga usa
--  [game] divisions y el resto [game] other_country_divisions.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO countries (id, name, code) VALUES
 (1,  'Panamá',      'PA'),
 (2,  'Argentina',   'AR'),
 (3,  'Brasil',      'BR'),
 (4,  'Colombia',    'CO'),
 (5,  'España',      'ES'),
 (6,  'México',      'MX'),
 (7,  'Uruguay',     'UY'),
 (8,  'Chile',       'CL'),
 (9,  'Perú',        'PE'),
 (10, 'Italia',      'IT'),
 (11, 'Portugal',    'PT'),
 (12, 'Francia',     'FR'),
 (13, 'Alemania',    'DE'),
 (14, 'Inglaterra',  'GB'),
 (15, 'Países Bajos','NL'),
 (16, 'Costa Rica',  'CR'),
 (17, 'Ecuador',     'EC'),
 (18, 'Paraguay',    'PY'),
 (19, 'Venezuela',   'VE'),
 (20, 'Bolivia',     'BO'),
 (21, 'Honduras',    'HN'),
 (22, 'Guatemala',   'GT'),
 (23, 'Estados Unidos', 'US'),
 (24, 'Canadá',      'CA'),
 (25, 'Bélgica',     'BE'),
 (26, 'Suiza',       'CH'),
 (27, 'Croacia',     'HR'),
 (28, 'Dinamarca',   'DK'),
 (29, 'Japón',       'JP'),
 (30, 'Corea del Sur', 'KR'),
 (31, 'Marruecos',   'MA'),
 (32, 'Nigeria',     'NG')
ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code);

INSERT INTO game_state (k, v) VALUES
 ('season', '1'),
 ('week', '1'),
 ('installed_at', NOW())
ON DUPLICATE KEY UPDATE k = k;
