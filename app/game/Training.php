<?php
declare(strict_types=1);

/**
 * Actualización semanal de entrenamiento: habilidades, resistencia, forma,
 * lesiones, edad, espíritu del equipo y cantera.
 */
final class Training
{
    /** Qué posiciones (tipo) entrenan cada tipo de entrenamiento y con qué intensidad */
    public const ELIGIBLE = [
        'keeper'     => ['GK' => 1.0],
        'defending'  => ['CD' => 1.0, 'WB' => 1.0],
        'playmaking' => ['IM' => 1.0, 'W' => 0.5],
        'winger'     => ['W' => 1.0, 'WB' => 0.5],
        'passing'    => ['IM' => 1.0, 'W' => 1.0, 'FW' => 1.0],
        'scoring'    => ['FW' => 1.0],
        'set_pieces' => ['GK' => 1.0, 'CD' => 1.0, 'WB' => 1.0, 'IM' => 1.0, 'W' => 1.0, 'FW' => 1.0],
    ];

    /** Velocidad relativa de cada habilidad */
    private const SPEED = [
        'keeper' => 1.35, 'defending' => 0.85, 'playmaking' => 0.85, 'winger' => 1.15,
        'passing' => 1.05, 'scoring' => 0.9, 'set_pieces' => 2.4,
    ];

    public static function trainerFactor(int $level): float
    {
        return [1 => 0.75, 2 => 0.85, 3 => 0.95, 4 => 1.05, 5 => 1.15][$level] ?? 0.95;
    }

    /** Ganancia semanal (en fracción de nivel) con entrenamiento completo */
    public static function gain(string $skill, float $current, int $age, array $team): float
    {
        $base = 0.36 / (1 + pow($current / 8.0, 2));
        $ageF = max(0.25, 1 - 0.045 * ($age - 17));
        $trainer = self::trainerFactor((int)$team['trainer_level']);
        $assist = 1 + min(10, (int)$team['assistants']) * 0.025;
        $intensity = (int)$team['training_intensity'] / 100;
        $staminaShare = 1 - (int)$team['stamina_share'] / 100;
        return $base * $ageF * $trainer * $assist * $intensity * $staminaShare * self::SPEED[$skill] * Config::float('game', 'training_speed', 1.0);
    }

    public static function weeklyUpdate(): void
    {
        $since = GameState::get('last_training_at') ?? '2000-01-01 00:00:00';
        $until = now();
        $teams = DB::all('SELECT * FROM teams WHERE is_national = 0');
        foreach ($teams as $team) {
            DB::transaction(fn() => self::trainTeam($team, $since, $until));
        }
        // Jugadores sin equipo (agentes libres) también envejecen
        DB::exec('UPDATE players SET age_days = age_days + 7 WHERE team_id IS NULL AND retired = 0');
        DB::exec('UPDATE players SET age_years = age_years + 1, age_days = age_days - 112 WHERE age_days >= 112');
        PlayerFactory::refresh();
        GameState::set('last_training_at', $until);
    }

    private static function trainTeam(array $team, string $since, string $until): void
    {
        $teamId = (int)$team['id'];
        $type = $team['training_type'];
        if (!isset(self::ELIGIBLE[$type])) {
            $type = 'playmaking';
        }
        $eligible = self::ELIGIBLE[$type];
        $isUser = !(int)$team['is_bot'];
        $ts = now();

        // Minutos jugados por cada jugador en la semana, por tipo de posición
        $rows = DB::all(
            'SELECT mp.player_id, mp.position, mp.minutes FROM match_players mp JOIN matches m ON m.id = mp.match_id
             WHERE mp.team_id = ? AND m.played_at > ? AND m.played_at <= ?',
            [$teamId, $since, $until]
        );
        $trained = [];
        $played = [];
        foreach ($rows as $r) {
            $pid = (int)$r['player_id'];
            $ptype = Lineup::POSITIONS[$r['position']][0] ?? 'IM';
            $f = $eligible[$ptype] ?? 0;
            $trained[$pid] = min(1.0, ($trained[$pid] ?? 0) + $f * (int)$r['minutes'] / 90);
            $played[$pid] = true;
        }

        $players = DB::all('SELECT * FROM players WHERE team_id = ? AND retired = 0', [$teamId]);
        $setPieceTaker = Lineup::get($teamId)['setpieces_id'] ?? null;
        $trainedCount = 0;
        // Carácter del entrenador
        $pers = StaffService::trainerPersonality($teamId);
        $persF = match ($pers) { 'exigente' => 1.05, 'metodico' => 1.03, 'temperamental' => 1.04, default => 1.0 };
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            $age = (int)$p['age_years'];
            $upd = [];
            $msgs = [];

            // 1) Entrenamiento de la habilidad
            $factor = $trained[$pid] ?? 0.0;
            if ($type === 'set_pieces' && $pid === (int)$setPieceTaker) {
                $factor = min(1.0, $factor + 0.25);
            }
            // Los bots no siempre juegan a todos; damos un entrenamiento base a los que no jugaron para que no se estanquen
            if (!$isUser && $factor == 0) {
                $factor = 0.25;
            }
            if ($factor > 0) {
                $trainedCount++;
                $cur = (float)$p[$type];
                $pf = $persF * ($pers === 'paternal' && $age <= 21 ? 1.08 : 1.0);
                $new = round(min(20.0, $cur + self::gain($type, $cur, $age, $team) * $factor * $pf), 2);
                $upd[$type] = $new;
                if (floor($new) > floor($cur)) {
                    $lvl = max(0, min(20, (int)floor((float)$new)));
                    $msgs[] = tx('⬆️ {player} ha mejorado {skill} a {level} ({n}).', ['player' => player_name($p), 'skill' => tx(SKILLS[$type]), 'level' => tx(SKILL_LEVELS[$lvl]), 'n' => $lvl]);
                }
            }

            // 2) Declive por edad
            if ($age >= 29) {
                $dec = 0.004 * pow($age - 28, 1.4);
                foreach (array_keys(SKILLS) as $sk) {
                    $cur = (float)($upd[$sk] ?? $p[$sk]);
                    if ($cur <= 1) {
                        continue;
                    }
                    $new = round(max(0, $cur - $dec * (1 + $cur / 20)), 2);
                    $upd[$sk] = $new;
                    if (floor($new) < floor($cur)) {
                        $lvl = max(0, min(20, (int)floor((float)$new)));
                        $msgs[] = tx('⬇️ {player} ha empeorado {skill} a {level} ({n}).', ['player' => player_name($p), 'skill' => tx(SKILLS[$sk]), 'level' => tx(SKILL_LEVELS[$lvl]), 'n' => $lvl]);
                    }
                }
            }

            // 3) Resistencia
            $ageF = max(0.3, 1 - 0.03 * ($age - 17));
            $sta = (float)$p['stamina'] + ((int)$team['stamina_share'] / 100) * 0.55 * $ageF * ((int)$team['training_intensity'] / 100) - 0.035 - max(0, $age - 27) * 0.01;
            $upd['stamina'] = round(clamp($sta, 1, 9), 2);

            // 4) Forma
            $target = 4.5 + ((int)$team['training_intensity'] - 80) / 20 * 1.2 + gauss(0, 1.2);
            $upd['form'] = round(clamp((float)$p['form'] + ($target - (float)$p['form']) * 0.35, 1, 8), 2);

            // 5) Lesiones
            if ((float)$p['injury_weeks'] > 0) {
                $heal = (1 + min(10, (int)$team['doctors']) * 0.12) * max(0.4, 1 - max(0, $age - 27) * 0.06);
                $inj = max(0, round((float)$p['injury_weeks'] - $heal, 1));
                $upd['injury_weeks'] = $inj;
                if ($inj == 0) {
                    $msgs[] = tx('✅ {player} se ha recuperado de su lesión.', ['player' => player_name($p)]);
                }
            }

            // 6) Edad y fidelidad
            $days = (int)$p['age_days'] + 7;
            $years = $age;
            if ($days >= 112) {
                $days -= 112;
                $years++;
                $msgs[] = tx('🎂 {player} cumple {years} años.', ['player' => player_name($p), 'years' => $years]);
            }
            $upd['age_days'] = $days;
            $upd['age_years'] = $years;
            $upd['loyalty'] = round(min(20, (float)$p['loyalty'] + 0.08), 2);

            DB::update('players', $upd, 'id = ?', [$pid]);
            if ($isUser) {
                foreach ($msgs as $m) {
                    DB::insert('team_events', ['team_id' => $teamId, 'created_at' => $ts, 'type' => 'training', 'message' => $m]);
                    DB::insert('player_events', ['player_id' => $pid, 'created_at' => $ts, 'message' => $m]);
                }
            }
        }

        // Espíritu y confianza tienden a valores normales
        $spiritTarget = 5.0 + min(3, (int)$team['psychologists']) * 0.4 - ((int)$team['training_intensity'] - 90) / 40
            + match ($pers) { 'motivador' => 0.6, 'exigente' => -0.4, 'tranquilo' => 0.2, 'temperamental' => rnd(-1.2, 1.2), default => 0 };
        $spirit = (float)$team['team_spirit'] + ($spiritTarget - (float)$team['team_spirit']) * 0.25;
        $conf = (float)$team['confidence'] + (5.0 + min(3, (int)$team['psychologists']) * 0.3 + ($pers === 'carismatico' ? 0.4 : ($pers === 'tranquilo' ? 0.2 : 0)) - (float)$team['confidence']) * 0.15;
        $mood = (float)$team['fan_mood'] + (5.0 + min(3, (int)$team['spokespersons']) * 0.4 + ($pers === 'carismatico' ? 0.2 : 0) - (float)$team['fan_mood']) * 0.1;
        $youth = min(40, (float)$team['youth_points'] + (int)$team['youth_level'] * rnd(0.8, 1.4));
        DB::update('teams', [
            'team_spirit' => round(clamp($spirit, 0, 10), 2),
            'confidence' => round(clamp($conf, 0, 10), 2),
            'fan_mood' => round(clamp($mood, 0, 10), 2),
            'youth_points' => round($youth, 2),
        ], 'id = ?', [$teamId]);

        if ($isUser) {
            DB::insert('team_events', ['team_id' => $teamId, 'created_at' => $ts, 'type' => 'training',
                'message' => tx('🏋️ Entrenamiento semanal completado: {type} ({n} jugadores entrenaron).', ['type' => isset(TRAINING_TYPES[$type]) ? tx(TRAINING_TYPES[$type]) : $type, 'n' => $trainedCount])]);
        }
    }
}
