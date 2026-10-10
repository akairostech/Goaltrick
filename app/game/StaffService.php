<?php
declare(strict_types=1);

/**
 * Cuerpo técnico con perfil: cada miembro del personal es una persona con nombre, edad,
 * nacionalidad, carácter e imagen. Las cantidades de la tabla teams (assistants, physios...)
 * y el nivel del entrenador se mantienen sincronizados para el resto del juego.
 */
final class StaffService
{
    /** rol => [nombre, plural, columna en teams] */
    public const ROLES = [
        'trainer'      => ['Entrenador', 'Entrenador', 'trainer_level'],
        'assistant'    => ['Ayudante del entrenador', 'Ayudantes del entrenador', 'assistants'],
        'physio'       => ['Fisioterapeuta', 'Fisioterapeutas', 'physios'],
        'doctor'       => ['Médico', 'Médicos', 'doctors'],
        'spokesperson' => ['Portavoz', 'Portavoces', 'spokespersons'],
        'psychologist' => ['Psicólogo', 'Psicólogos', 'psychologists'],
    ];

    /** carácter => [nombre, efecto (si es el entrenador)] */
    public const PERSONALITIES = [
        'motivador'     => ['Motivador', 'Mantiene alto el espíritu del vestuario.'],
        'exigente'      => ['Exigente', 'Los jugadores entrenan un 5% más, pero el espíritu baja un poco.'],
        'metodico'      => ['Metódico', 'Sesiones muy ordenadas: el entrenamiento rinde un 3% más.'],
        'carismatico'   => ['Carismático', 'Sube la confianza del equipo y gusta a la afición.'],
        'paternal'      => ['Paternal', 'Los jugadores de 21 años o menos entrenan un 8% más.'],
        'tactico'       => ['Táctico', 'Prepara muy bien los partidos: +2% en el medio campo.'],
        'tranquilo'     => ['Tranquilo', 'Transmite calma: espíritu y confianza algo más estables y altos.'],
        'temperamental' => ['Temperamental', 'Entrenamientos intensos (+4%), pero el espíritu sube y baja sin avisar.'],
    ];

    /** Caras disponibles para el personal (assets/img/staff/NNN.svg) */
    public const AVATAR_COUNT = 5000;

    public static function avatarUrl(array $s): string
    {
        $n = max(1, min(self::AVATAR_COUNT, (int)($s['avatar'] ?? 1)));
        return asset(sprintf('img/staff/%03d.svg', $n));
    }

    public static function name(array $s): string
    {
        return $s['first_name'] . ' ' . $s['last_name'];
    }

    public static function link(array $s): string
    {
        return '<a href="' . e(url('staff_member', ['id' => $s['id']])) . '">' . e(self::name($s)) . '</a>';
    }

    /** Perfil aleatorio (sin guardar). Con $seed el resultado es siempre el mismo (candidatos estables durante la semana). */
    public static function generate(string $role, int $countryId, ?int $level = null, ?int $seed = null): array
    {
        if ($seed !== null) {
            mt_srand($seed);
        }
        $nat = mt_rand(1, 100) <= 70 ? $countryId : PlayerFactory::randomNationality($countryId);
        [$first, $last] = PlayerFactory::randomName($nat);
        $keys = array_keys(self::PERSONALITIES);
        $p = [
            'role'        => $role,
            'first_name'  => $first,
            'last_name'   => $last,
            'age'         => $role === 'trainer' ? mt_rand(36, 68) : mt_rand(25, 62),
            'country_id'  => $nat,
            'personality' => $keys[mt_rand(0, count($keys) - 1)],
            'level'       => $level ?? mt_rand(2, 4),
            'avatar'      => mt_rand(1, self::AVATAR_COUNT),
        ];
        if ($seed !== null) {
            mt_srand(); // volver a una semilla aleatoria
        }
        return $p;
    }

    public static function hire(int $teamId, array $profile): int
    {
        $id = DB::insert('staff', $profile + ['team_id' => $teamId, 'hired_at' => now()]);
        self::syncCounts($teamId);
        return $id;
    }

    public static function teamStaff(int $teamId): array
    {
        return DB::all("SELECT s.*, c.name AS country, c.code FROM staff s JOIN countries c ON c.id = s.country_id
            WHERE s.team_id = ? ORDER BY FIELD(s.role, 'trainer', 'assistant', 'physio', 'doctor', 'spokesperson', 'psychologist'), s.id", [$teamId]);
    }

    public static function trainer(int $teamId): ?array
    {
        return DB::row("SELECT s.*, c.name AS country, c.code FROM staff s JOIN countries c ON c.id = s.country_id WHERE s.team_id = ? AND s.role = 'trainer' ORDER BY s.id DESC LIMIT 1", [$teamId]);
    }

    /** Carácter del entrenador (para sus efectos en el juego) */
    public static function trainerPersonality(int $teamId): ?string
    {
        $v = DB::value("SELECT personality FROM staff WHERE team_id = ? AND role = 'trainer' ORDER BY id DESC LIMIT 1", [$teamId]);
        return $v ? (string)$v : null;
    }

    /** Copia en teams las cantidades de personal y el nivel del entrenador */
    public static function syncCounts(int $teamId): void
    {
        $counts = array_column(DB::all('SELECT role, COUNT(*) n FROM staff WHERE team_id = ? GROUP BY role', [$teamId]), 'n', 'role');
        $trainerLevel = (int)(DB::value("SELECT level FROM staff WHERE team_id = ? AND role = 'trainer' ORDER BY id DESC LIMIT 1", [$teamId]) ?? 3);
        DB::exec(
            'UPDATE teams SET trainer_level = ?, assistants = ?, physios = ?, doctors = ?, spokespersons = ?, psychologists = ? WHERE id = ?',
            [max(1, min(5, $trainerLevel)), (int)($counts['assistant'] ?? 0), (int)($counts['physio'] ?? 0), (int)($counts['doctor'] ?? 0),
             (int)($counts['spokesperson'] ?? 0), (int)($counts['psychologist'] ?? 0), $teamId]
        );
    }

    /**
     * Ajusta las personas del cuerpo técnico a las cantidades guardadas en teams
     * (se usa al actualizar desde versiones antiguas y tras editar un equipo en la administración).
     */
    public static function reconcile(int $teamId): void
    {
        $t = DB::row('SELECT id, country_id, trainer_level, assistants, physios, doctors, spokespersons, psychologists FROM teams WHERE id = ?', [$teamId]);
        if (!$t) {
            return;
        }
        $have = [];
        foreach (DB::all('SELECT id, role, level FROM staff WHERE team_id = ? ORDER BY id', [$teamId]) as $s) {
            $have[$s['role']][] = $s;
        }
        foreach (self::ROLES as $role => [, , $col]) {
            $want = $role === 'trainer' ? 1 : (int)$t[$col];
            $list = $have[$role] ?? [];
            for ($i = count($list); $i < $want; $i++) {
                DB::insert('staff', self::generate($role, (int)$t['country_id'], $role === 'trainer' ? (int)$t['trainer_level'] : null)
                    + ['team_id' => $teamId, 'hired_at' => now()]);
            }
            for ($i = count($list); $i > $want; $i--) {
                DB::exec('DELETE FROM staff WHERE id = ?', [$list[$i - 1]['id']]);
            }
            if ($role === 'trainer' && $list && (int)$list[0]['level'] !== (int)$t['trainer_level']) {
                DB::exec('UPDATE staff SET level = ? WHERE id = ?', [$t['trainer_level'], $list[0]['id']]);
            }
        }
    }

    /** Crea los perfiles de todos los clubes que todavía no los tienen */
    public static function backfill(): void
    {
        $teams = DB::all("SELECT t.id FROM teams t WHERE t.is_national = 0 AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.team_id = t.id)");
        foreach (array_chunk($teams, 100) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $t) {
                    self::reconcile((int)$t['id']);
                }
            });
        }
    }

    /** Cuerpo técnico inicial de un club nuevo */
    public static function resetTeam(int $teamId): void
    {
        DB::exec('DELETE FROM staff WHERE team_id = ?', [$teamId]);
        self::reconcile($teamId);
    }

    /** Fin de temporada: todos cumplen un año */
    public static function seasonEnd(): void
    {
        DB::exec('UPDATE staff SET age = LEAST(age + 1, 80)');
    }
}
