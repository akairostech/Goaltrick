<?php
declare(strict_types=1);

/**
 * Naciones y selecciones nacionales.
 *
 * Cada país tiene una fila en `teams` con is_national = 1 (sin serie de liga ni jugadores propios).
 * Su convocatoria se calcula en cada momento: los jugadores de esa nacionalidad, de cualquier club
 * del mundo, con mayor TSI en cada posición y que estén disponibles (no lesionados).
 */
final class NationService
{
    /** Convocados por posición natural (18 jugadores) */
    public const QUOTA = ['GK' => 2, 'CD' => 3, 'WB' => 3, 'IM' => 4, 'W' => 3, 'FW' => 3];

    public static function flag(string $code): string
    {
        $code = strtoupper($code);
        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }
        // Inglaterra no tiene bandera de país propia en Unicode: usamos la del Reino Unido
        return mb_chr(0x1F1E6 + ord($code[0]) - 65) . mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }

    /**
     * Bandera como imagen (assets/img/flags/<código>.svg): los emojis de banderas no se ven en Windows.
     * $country = true: Inglaterra (GB) usa la cruz de San Jorge; false (idiomas) usa la del Reino Unido.
     */
    public static function flagImg(?string $code, string $title = '', bool $country = true, string $class = 'flag-img'): string
    {
        $code = strtolower((string)$code);
        if (!preg_match('/^[a-z]{2}$/', $code)) {
            return '';
        }
        $file = $country && $code === 'gb' ? 'eng' : $code;
        if (!is_file(APP_ROOT . '/assets/img/flags/' . $file . '.svg')) {
            return '<span class="flag">' . self::flag($code) . '</span>';
        }
        return '<img class="' . e($class) . '" src="' . e(asset('img/flags/' . $file . '.svg')) . '" alt="' . e(strtoupper($code)) . '"'
            . ($title !== '' ? ' title="' . e($title) . '"' : '') . '>';
    }

    /** Convierte un emoji de bandera (p. ej. el @flag de un idioma) en la imagen correspondiente */
    public static function emojiFlagImg(string $emoji, string $title = ''): string
    {
        $cps = array_values(array_filter(array_map('mb_ord', mb_str_split($emoji)), fn($c) => $c >= 0x1F1E6 && $c <= 0x1F1FF));
        if (count($cps) !== 2) {
            return e($emoji);
        }
        $code = chr($cps[0] - 0x1F1E6 + 65) . chr($cps[1] - 0x1F1E6 + 65);
        return self::flagImg($code, $title, false, 'flag-img flag-em');
    }

    public static function nationalTeam(int $countryId): ?array
    {
        return DB::row('SELECT * FROM teams WHERE is_national = 1 AND country_id = ?', [$countryId]);
    }

    /** Crea la selección de cada país que aún no la tenga */
    public static function createNationalTeams(): void
    {
        foreach (DB::all('SELECT * FROM countries ORDER BY id') as $c) {
            if (self::nationalTeam((int)$c['id'])) {
                continue;
            }
            DB::insert('teams', [
                'user_id'        => null,
                'league_unit_id' => null,
                'country_id'     => $c['id'],
                'name'           => $c['name'],
                'short_name'     => $c['code'],
                'is_bot'         => 1,
                'is_national'    => 1,
                'cash'           => 0,
                'fans'           => 50000,
                'fan_mood'       => 6,
                'team_spirit'    => 6,
                'confidence'     => 5,
                'trainer_level'  => 5,
                'physios'        => 5,
                'doctors'        => 5,
                'stadium_name'   => 'Estadio Nacional de ' . $c['name'],
                'seats_terraces' => 20000,
                'seats_basic'    => 25000,
                'seats_roof'     => 12000,
                'seats_vip'      => 3000,
                'founded_at'     => now(),
            ]);
        }
    }

    /**
     * Convocatoria: los mejores TSI de cada posición entre los jugadores de esa nacionalidad.
     * Si $onlyAvailable, se excluyen los lesionados. Si faltan jugadores de una posición se
     * completa con los mejores TSI restantes.
     */
    public static function squad(int $countryId, bool $onlyAvailable = true): array
    {
        $cond = 'p.country_id = ? AND p.retired = 0 AND p.team_id IS NOT NULL AND t.is_national = 0'
            . ($onlyAvailable ? ' AND p.injury_weeks <= 0' : '');
        $squad = [];
        $ids = [];
        foreach (self::QUOTA as $role => $n) {
            $rows = DB::all(
                "SELECT p.*, t.name AS club_name FROM players p JOIN teams t ON t.id = p.team_id
                 WHERE $cond AND p.role = ? ORDER BY p.tsi DESC LIMIT " . (int)$n,
                [$countryId, $role]
            );
            foreach ($rows as $r) {
                $r['squad_role'] = $role;
                $squad[] = $r;
                $ids[] = (int)$r['id'];
            }
        }
        $missing = array_sum(self::QUOTA) - count($squad);
        if ($missing > 0) {
            $not = $ids ? ' AND p.id NOT IN (' . implode(',', $ids) . ')' : '';
            $rows = DB::all(
                "SELECT p.*, t.name AS club_name FROM players p JOIN teams t ON t.id = p.team_id
                 WHERE $cond $not ORDER BY p.tsi DESC LIMIT " . (int)$missing,
                [$countryId]
            );
            foreach ($rows as $r) {
                $r['squad_role'] = $r['role'];
                $squad[] = $r;
            }
        }
        return $squad;
    }

    /** Fuerza de la selección (suma del TSI de los convocados), usada para los bombos del Mundial */
    public static function strength(int $countryId): int
    {
        return (int)array_sum(array_column(self::squad($countryId), 'tsi'));
    }

    /** Selecciones a las que pertenece un jugador (para mostrarlo en su ficha) */
    public static function isCalledUp(array $player): bool
    {
        foreach (self::squad((int)$player['country_id']) as $p) {
            if ((int)$p['id'] === (int)$player['id']) {
                return true;
            }
        }
        return false;
    }
}
