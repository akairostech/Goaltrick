<?php
declare(strict_types=1);

/**
 * Instalación: crea la base de datos, las tablas, el mundo y el administrador.
 */
final class Installer
{
    public const LOCK = APP_ROOT . '/install.lock';
    /** Versión del esquema de la base de datos que espera este código */
    public const DB_VERSION = 10;

    public static function isInstalled(): bool
    {
        return is_file(self::LOCK);
    }

    /** Divide un archivo .sql en sentencias */
    public static function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*(\r?\n|$)/', $sql);
        return array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
    }

    public static function createDatabase(): void
    {
        $name = (string)Config::get('database', 'name', 'goaltrick');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException(t('El nombre de la base de datos sólo puede contener letras, números y _'));
        }
        $pdo = DB::connect(false);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function runSqlFile(string $file): void
    {
        $pdo = DB::pdo();
        foreach (self::splitSql((string)file_get_contents($file)) as $stmt) {
            $pdo->exec($stmt);
        }
    }

    public static function install(string $username, string $email, string $password, string $teamName, ?callable $progress = null): void
    {
        $say = $progress ?? fn(string $m) => null;
        $say(t('Creando la base de datos...'));
        self::createDatabase();
        $say(t('Creando tablas (sql/schema.sql)...'));
        self::runSqlFile(APP_ROOT . '/sql/schema.sql');
        self::runSqlFile(APP_ROOT . '/sql/seed.sql');
        self::runSqlFile(APP_ROOT . '/sql/companies.sql');

        DB::exec('UPDATE countries SET name = ? WHERE id = 1', [(string)Config::get('game', 'country_name', 'Panamá')]);
        GameState::set('db_version', self::DB_VERSION);
        if (!(int)DB::value('SELECT COUNT(*) FROM league_units')) {
            $say(t('Generando ligas, equipos y jugadores de todas las naciones (puede tardar un poco)...'));
            @set_time_limit(0);
            LeagueService::createWorld($say);
            $say(t('Creando el calendario de la temporada 1...'));
            LeagueService::createSeason(1, LeagueService::firstSeasonStart());
        }
        $say(t('Creando la cuenta de administrador...'));
        $errors = Auth::validateRegistration($username, $email, $password, $password, $teamName);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }
        Auth::register($username, $email, $password, $teamName, true);
        file_put_contents(self::LOCK, 'Instalado el ' . date('Y-m-d H:i:s') . "\nBorra este archivo para volver a ejecutar el instalador.\n");
        $say(t('¡Instalación completada!'));
    }

    /* ------------------------------------------------------------------
     *  Actualizaciones de una instalación existente
     * ------------------------------------------------------------------ */

    public static function dbVersion(): int
    {
        try {
            $v = DB::value("SELECT v FROM game_state WHERE k = 'db_version'");
            return $v === null ? 1 : (int)$v;
        } catch (PDOException $e) {
            // Sólo se considera versión 1 si falta la tabla; un fallo de conexión no debe lanzar actualizaciones
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return 1;
            }
            throw $e;
        }
    }

    public static function needsUpgrade(): bool
    {
        return self::isInstalled() && self::dbVersion() < self::DB_VERSION;
    }

    private static function columnExists(string $table, string $column): bool
    {
        return (bool)DB::value(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private static function indexExists(string $table, string $index): bool
    {
        return (bool)DB::value(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );
    }

    /** Actualiza la base de datos a la versión actual sin perder datos. Se puede ejecutar varias veces. */
    public static function upgrade(?callable $progress = null): void
    {
        $say = $progress ?? fn(string $m) => null;
        @set_time_limit(0);
        if (self::dbVersion() < 2) {
            $say(t('Versión 1.1: añadiendo columnas nuevas...'));
            $pdo = DB::pdo();
            $pdo->exec('ALTER TABLE teams MODIFY league_unit_id INT UNSIGNED NULL');
            $alters = [
                ['teams', 'is_national', 'ALTER TABLE teams ADD COLUMN is_national TINYINT(1) NOT NULL DEFAULT 0 AFTER is_bot'],
                ['players', 'caps', 'ALTER TABLE players ADD COLUMN caps SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER goals_career'],
                ['players', 'goals_national', 'ALTER TABLE players ADD COLUMN goals_national SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER caps'],
                ['matches', 'cup_id', 'ALTER TABLE matches ADD COLUMN cup_id INT UNSIGNED NULL AFTER league_unit_id'],
                ['matches', 'cup_stage', 'ALTER TABLE matches ADD COLUMN cup_stage VARCHAR(4) NULL AFTER cup_id'],
                ['matches', 'home_pens', 'ALTER TABLE matches ADD COLUMN home_pens TINYINT UNSIGNED NULL AFTER away_goals'],
                ['matches', 'away_pens', 'ALTER TABLE matches ADD COLUMN away_pens TINYINT UNSIGNED NULL AFTER home_pens'],
            ];
            foreach ($alters as [$table, $col, $sql]) {
                if (!self::columnExists($table, $col)) {
                    $pdo->exec($sql);
                }
            }
            if (!self::indexExists('matches', 'idx_matches_cup')) {
                $pdo->exec('ALTER TABLE matches ADD KEY idx_matches_cup (cup_id, round)');
            }
            if (!self::indexExists('match_players', 'idx_mp_team')) {
                $pdo->exec('ALTER TABLE match_players ADD KEY idx_mp_team (team_id, match_id)');
            }
            if (!self::indexExists('players', 'idx_players_nation')) {
                $pdo->exec('ALTER TABLE players ADD KEY idx_players_nation (country_id, role, tsi)');
            }
            if (!self::indexExists('matches', 'idx_matches_played')) {
                $pdo->exec('ALTER TABLE matches ADD KEY idx_matches_played (played_at)');
            }
            $say(t('Creando las tablas del Mundial...'));
            self::runSqlFile(APP_ROOT . '/sql/schema.sql');   // CREATE TABLE IF NOT EXISTS: sólo crea las nuevas
            $say(t('Renombrando Futbolandia a Panamá y añadiendo Costa Rica...'));
            self::runSqlFile(APP_ROOT . '/sql/seed.sql');
            $name = (string)Config::get('game', 'country_name', 'Panamá');
            if ($name === '' || $name === 'Futbolandia') {
                $name = 'Panamá';
            }
            DB::exec("UPDATE countries SET name = ?, code = 'PA' WHERE id = 1", [$name]);

            self::addNewNationLeagues($say);
            $season = GameState::season();
            $seasonStart = strtotime(GameState::get('season_start') ?? date('Y-m-d', LeagueService::firstSeasonStart()));
            $say(t('Programando el Mundial...'));
            WorldCupService::scheduleForSeason($season, $seasonStart, GameState::week() + 1);
            GameState::set('db_version', 2);
        }
        if (self::dbVersion() < 3) {
            $say(t('Versión 1.2: añadiendo las imágenes de perfil de los jugadores...'));
            if (!self::columnExists('players', 'avatar')) {
                DB::pdo()->exec('ALTER TABLE players ADD COLUMN avatar TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_name');
            }
            DB::exec('UPDATE players SET avatar = 1 + FLOOR(RAND() * 50) WHERE avatar = 0');
            GameState::set('db_version', 3);
        }
        if (self::dbVersion() < 4) {
            $say(t('Versión 1.3: 400 caras de jugador, empresas y contratos de patrocinio...'));
            DB::pdo()->exec('ALTER TABLE players MODIFY avatar SMALLINT UNSIGNED NOT NULL DEFAULT 0');
            DB::exec('UPDATE players SET avatar = 1 + FLOOR(RAND() * ' . AVATAR_COUNT . ')');
            self::runSqlFile(APP_ROOT . '/sql/schema.sql');      // crea companies y sponsor_contracts
            self::runSqlFile(APP_ROOT . '/sql/companies.sql');
            $say(t('Enviando las ofertas de patrocinio de esta temporada a todos los clubes...'));
            SponsorService::generateSeasonOffers(GameState::season());
            GameState::set('db_version', 4);
        }
        if (self::dbVersion() < 5) {
            $say(t('Versión 1.4: personal con perfil, Copa, entradas dinámicas y 32 naciones...'));
            $pdo = DB::pdo();
            if (!self::columnExists('teams', 'stadium_notice_at')) {
                $pdo->exec('ALTER TABLE teams ADD COLUMN stadium_notice_at DATETIME NULL AFTER negative_weeks');
            }
            $pdo->exec('ALTER TABLE worldcup_teams MODIFY group_letter VARCHAR(2) NOT NULL');
            self::runSqlFile(APP_ROOT . '/sql/schema.sql');   // crea staff, league_cups y cup_entries
            self::runSqlFile(APP_ROOT . '/sql/seed.sql');     // añade las naciones nuevas
            $say(t('Creando las ligas de las nuevas naciones (puede tardar uno o dos minutos)...'));
            self::addNewNationLeagues($say);
            $season = GameState::season();
            $say(t('Dando un perfil (nombre, edad, nacionalidad y carácter) al personal de todos los clubes...'));
            StaffService::backfill();
            $say(t('Enviando ofertas de patrocinio a los clubes nuevos...'));
            foreach (DB::all('SELECT t.id FROM teams t WHERE t.is_national = 0 AND t.league_unit_id IS NOT NULL
                              AND NOT EXISTS (SELECT 1 FROM sponsor_contracts sc WHERE sc.team_id = t.id)') as $t) {
                SponsorService::generateOffers((int)$t['id'], $season);
            }
            $say(t('Sorteando la Copa de cada país...'));
            CupService::createForSeason($season);
            GameState::set('db_version', 5);
        }
        if (self::dbVersion() < 6) {
            $say(t('Versión 1.5: idiomas, alineaciones guardadas y estadio ilustrado...'));
            $pdo = DB::pdo();
            if (!self::columnExists('users', 'language')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN language VARCHAR(8) NULL AFTER is_banned');
            }
            if (!self::columnExists('team_lineups', 'for_match_id')) {
                $pdo->exec('ALTER TABLE team_lineups ADD COLUMN for_match_id INT UNSIGNED NULL AFTER setpieces_id');
            }
            // Los textos traducibles se guardan con su plantilla: hace falta algo más de espacio
            $pdo->exec('ALTER TABLE game_state MODIFY v VARCHAR(1000) NOT NULL');
            $pdo->exec('ALTER TABLE announcements MODIFY title VARCHAR(500) NOT NULL');
            $pdo->exec('ALTER TABLE team_events MODIFY message VARCHAR(1500) NOT NULL');
            $pdo->exec('ALTER TABLE player_events MODIFY message VARCHAR(1000) NOT NULL');
            $pdo->exec('ALTER TABLE finances MODIFY description VARCHAR(600) NULL');
            $pdo->exec('ALTER TABLE messages MODIFY subject VARCHAR(500) NOT NULL');
            self::runSqlFile(APP_ROOT . '/sql/schema.sql');   // crea lineup_presets
            $say(t('Volviendo a sortear el Mundial con las 32 selecciones...'));
            WorldCupService::rebuild(GameState::season(), true);
            $wc = WorldCupService::forSeason(GameState::season());
            if ($wc && (int)DB::value('SELECT COUNT(*) FROM worldcup_teams WHERE cup_id = ?', [$wc['id']]) < 32) {
                $say(t('El Mundial de esta temporada ya no cabe en el calendario: el de la próxima temporada tendrá todas las selecciones.'));
            }
            GameState::set('db_version', 6);
        }
        if (self::dbVersion() < 7) {
            $say(t('Versión 1.6: uniformes, escudos e historial de transferencias...'));
            $pdo = DB::pdo();
            $alters = [
                ['teams', 'kit_id', 'ALTER TABLE teams ADD COLUMN kit_id SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER stadium_notice_at'],
                ['teams', 'kit_custom', 'ALTER TABLE teams ADD COLUMN kit_custom VARCHAR(120) NULL AFTER kit_id'],
                ['teams', 'crest_id', 'ALTER TABLE teams ADD COLUMN crest_id SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER kit_custom'],
                ['teams', 'crest_custom', 'ALTER TABLE teams ADD COLUMN crest_custom VARCHAR(120) NULL AFTER crest_id'],
                ['transfer_listings', 'tsi_at_sale', 'ALTER TABLE transfer_listings ADD COLUMN tsi_at_sale INT UNSIGNED NULL AFTER closed_at'],
                ['transfer_listings', 'age_at_sale', 'ALTER TABLE transfer_listings ADD COLUMN age_at_sale TINYINT UNSIGNED NULL AFTER tsi_at_sale'],
            ];
            foreach ($alters as [$table, $col, $sql]) {
                if (!self::columnExists($table, $col)) {
                    $pdo->exec($sql);
                }
            }
            // Traspasos antiguos: se toma el TSI y la edad actuales del jugador como mejor aproximación
            DB::exec("UPDATE transfer_listings l JOIN players p ON p.id = l.player_id SET l.tsi_at_sale = p.tsi, l.age_at_sale = p.age_years
                      WHERE l.status = 'sold' AND l.tsi_at_sale IS NULL");
            GameState::set('db_version', 7);
        }
        if (self::dbVersion() < 8) {
            $say(t('Versión 1.7: alineaciones para cada partido...'));
            self::runSqlFile(APP_ROOT . '/sql/schema.sql');   // crea match_lineups
            // La alineación ya enviada para el próximo partido pasa a la tabla nueva
            DB::exec("INSERT IGNORE INTO match_lineups (team_id, match_id, positions, orders, tactic, attitude, captain_id, setpieces_id, updated_at)
                      SELECT l.team_id, l.for_match_id, l.positions, l.orders, l.tactic, l.attitude, l.captain_id, l.setpieces_id, l.updated_at
                      FROM team_lineups l JOIN matches m ON m.id = l.for_match_id WHERE m.status = 'scheduled'");
            GameState::set('db_version', 8);
        }
        if (self::dbVersion() < 9) {
            $say(t('Versión 1.8: ayuda y soporte, sanciones e historial de accesos...'));
            $pdo = DB::pdo();
            $alters = [
                ['users', 'ban_reason', 'ALTER TABLE users ADD COLUMN ban_reason VARCHAR(500) NULL AFTER is_banned'],
                ['users', 'banned_until', 'ALTER TABLE users ADD COLUMN banned_until DATETIME NULL AFTER ban_reason'],
                ['users', 'banned_at', 'ALTER TABLE users ADD COLUMN banned_at DATETIME NULL AFTER banned_until'],
            ];
            foreach ($alters as [$table, $col, $sql]) {
                if (!self::columnExists($table, $col)) {
                    $pdo->exec($sql);
                }
            }
            self::runSqlFile(APP_ROOT . '/sql/schema.sql');   // crea user_sanctions, user_logins, support_tickets y support_replies
            GameState::set('db_version', 9);
        }
        if (self::dbVersion() < 10) {
            $say(t('Versión 1.10: perfil público del mánager...'));
            $pdo = DB::pdo();
            $alters = [
                ['users', 'avatar', 'ALTER TABLE users ADD COLUMN avatar SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `language`'],
                ['users', 'country_id', 'ALTER TABLE users ADD COLUMN country_id INT UNSIGNED NULL AFTER avatar'],
                ['users', 'city', 'ALTER TABLE users ADD COLUMN city VARCHAR(60) NULL AFTER country_id'],
                ['users', 'birth_year', 'ALTER TABLE users ADD COLUMN birth_year SMALLINT UNSIGNED NULL AFTER city'],
                ['users', 'motto', 'ALTER TABLE users ADD COLUMN motto VARCHAR(140) NULL AFTER birth_year'],
                ['users', 'bio', 'ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER motto'],
                ['users', 'fav_club', 'ALTER TABLE users ADD COLUMN fav_club VARCHAR(60) NULL AFTER bio'],
                ['users', 'profile_views', 'ALTER TABLE users ADD COLUMN profile_views INT UNSIGNED NOT NULL DEFAULT 0 AFTER fav_club'],
            ];
            foreach ($alters as [$table, $col, $sql]) {
                if (!self::columnExists($table, $col)) {
                    $pdo->exec($sql);
                }
            }
            // El país del mánager parte del país de su club
            DB::exec('UPDATE users u JOIN teams t ON t.user_id = u.id SET u.country_id = t.country_id WHERE u.country_id IS NULL AND t.is_national = 0');
            GameState::set('db_version', 10);
        }
        $say(t('Base de datos actualizada a la versión {v}.', ['v' => self::DB_VERSION]));
    }

    /**
     * Crea las ligas de los países que aún no las tienen, su calendario de la temporada actual
     * y juega las jornadas que ya se disputaron en el resto del mundo.
     */
    private static function addNewNationLeagues(callable $say): void
    {
        $before = array_map('intval', array_column(DB::all('SELECT id FROM league_units'), 'id'));
        LeagueService::createWorld($say);
        $new = array_values(array_diff(array_map('intval', array_column(DB::all('SELECT id FROM league_units'), 'id')), $before));
        if (!$new) {
            return;
        }
        $season = GameState::season();
        $seasonStart = strtotime(GameState::get('season_start') ?? date('Y-m-d', LeagueService::firstSeasonStart()));
        $say(t('Creando el calendario de las nuevas ligas...'));
        DB::transaction(fn() => LeagueService::createFixtures($season, $seasonStart, $new));
        $done = array_map('intval', array_column(DB::all(
            "SELECT round FROM scheduled_events WHERE type = 'league_round' AND season = ? AND status = 'done'", [$season]
        ), 'round'));
        if ($done) {
            $say(t('Poniendo al día las jornadas ya disputadas en las nuevas ligas...'));
            $in = implode(',', $new);
            $rounds = implode(',', $done);
            foreach (DB::all("SELECT id FROM matches WHERE type = 'league' AND season = ? AND status = 'scheduled'
                              AND league_unit_id IN ($in) AND round IN ($rounds) ORDER BY round", [$season]) as $m) {
                MatchEngine::play((int)$m['id']);
            }
        }
    }

    /* ------------------------------------------------------------------
     *  Reinicio completo de la partida (panel de administración)
     * ------------------------------------------------------------------ */

    /** Tablas con datos de la partida (no se tocan users, countries ni la sesión) */
    private const GAME_TABLES = [
        'match_lineups', 'lineup_presets', 'cup_entries', 'league_cups', 'staff', 'sponsor_contracts', 'worldcup_teams', 'worldcups', 'transfer_bids', 'transfer_listings', 'friendly_requests', 'match_players', 'matches',
        'finances', 'player_events', 'players', 'team_events', 'season_history', 'team_lineups', 'teams', 'league_units',
        'scheduled_events', 'announcements', 'game_state',
    ];

    /**
     * Borra la partida y genera un mundo nuevo (temporada 1).
     * Si $keepUsers, cada usuario conserva su cuenta y recibe un equipo nuevo con el mismo nombre en su mismo país;
     * si no, se borran todas las cuentas salvo la del administrador que reinicia.
     */
    public static function resetGame(int $adminId, bool $keepUsers, bool $keepMessages, ?callable $progress = null): void
    {
        $say = $progress ?? fn(string $m) => null;
        @set_time_limit(0);
        // Guardar el equipo de cada usuario para devolvérselo
        $owners = DB::all('SELECT u.id, t.name, t.country_id FROM users u LEFT JOIN teams t ON t.user_id = u.id ORDER BY (u.id = ?) DESC, u.id', [$adminId]);
        $pdo = DB::pdo();
        $say(t('Borrando la partida actual...'));
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (self::GAME_TABLES as $t) {
                $pdo->exec("DELETE FROM $t");
            }
            if (!$keepMessages) {
                $pdo->exec('DELETE FROM messages');
            }
            if (!$keepUsers) {
                DB::exec('DELETE FROM remember_tokens WHERE user_id <> ?', [$adminId]);
                DB::exec('DELETE FROM messages WHERE from_user_id <> ? OR to_user_id <> ?', [$adminId, $adminId]);
                DB::exec('DELETE FROM users WHERE id <> ?', [$adminId]);
            }
            foreach (self::GAME_TABLES as $t) {
                if ($t !== 'game_state') {
                    $pdo->exec("ALTER TABLE $t AUTO_INCREMENT = 1");
                }
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        GameState::clearCache();
        TeamService::resetCache();
        self::runSqlFile(APP_ROOT . '/sql/seed.sql');
        GameState::set('db_version', self::DB_VERSION);
        GameState::set('installed_at', now());
        $say(t('Generando ligas, equipos y jugadores de todas las naciones...'));
        LeagueService::createWorld($say);
        $say(t('Creando el calendario de la temporada 1...'));
        LeagueService::createSeason(1, LeagueService::firstSeasonStart());
        $say(t('Asignando equipos nuevos a los mánagers...'));
        foreach ($owners as $o) {
            if (!$keepUsers && (int)$o['id'] !== $adminId) {
                continue;
            }
            $name = $o['name'] ?: 'Club ' . $o['id'];
            $teamId = TeamService::findFreeTeam($o['country_id'] ? (int)$o['country_id'] : null) ?? TeamService::findFreeTeam();
            if (!$teamId) {
                break;
            }
            if (DB::value('SELECT COUNT(*) FROM teams WHERE name = ?', [$name])) {
                $name = mb_substr($name, 0, 34) . ' ' . mt_rand(10, 99);
            }
            DB::transaction(fn() => TeamService::assignToUser($teamId, (int)$o['id'], $name));
        }
        DB::insert('announcements', ['user_id' => $adminId, 'title' => tx('🔄 ¡Nueva partida!'),
            'body' => tx('El administrador ha reiniciado el juego. Empieza la temporada 1: ¡mucha suerte con tu nuevo equipo!'), 'created_at' => now()]);
        $say(t('¡Partida reiniciada!'));
    }
}
