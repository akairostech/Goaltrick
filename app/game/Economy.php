<?php
declare(strict_types=1);

/**
 * Economía: movimientos de caja, actualización económica semanal, personal y estadio.
 */
final class Economy
{
    public const CATEGORIES = [
        'tickets'       => 'Entradas',
        'sponsors'      => 'Patrocinador principal',
        'ads'           => 'Publicidad',
        'transfers_in'  => 'Venta de jugadores',
        'prizes'        => 'Premios',
        'wages'         => 'Salarios de jugadores',
        'staff'         => 'Personal',
        'stadium'       => 'Mantenimiento del estadio',
        'construction'  => 'Obras del estadio',
        'transfers_out' => 'Compra de jugadores',
        'youth'         => 'Cantera',
        'other'         => 'Otros',
    ];

    public static function transaction(int $teamId, int $amount, string $category, string $desc = '', ?int $season = null, ?int $week = null): void
    {
        if ($amount === 0) {
            return;
        }
        DB::insert('finances', [
            'team_id'     => $teamId,
            'season'      => $season ?? GameState::season(),
            'week'        => $week ?? GameState::week(),
            'category'    => $category,
            'amount'      => $amount,
            'description' => mb_substr($desc, 0, 200),
            'created_at'  => now(),
        ]);
        DB::exec('UPDATE teams SET cash = cash + ? WHERE id = ?', [$amount, $teamId]);
    }

    public static function trainerWage(int $level): int
    {
        return (int)(800 * pow($level, 1.6));
    }

    public static function staffCount(array $t): int
    {
        return (int)$t['assistants'] + (int)$t['physios'] + (int)$t['doctors'] + (int)$t['spokespersons'] + (int)$t['psychologists'];
    }

    public static function weeklyStaffCost(array $t): int
    {
        return self::staffCount($t) * Config::int('economy', 'staff_wage', 1800) + self::trainerWage((int)$t['trainer_level']);
    }

    public static function maintenance(array $t): int
    {
        $m = 0.0;
        foreach (['terraces', 'basic', 'roof', 'vip'] as $s) {
            $m += (int)$t['seats_' . $s] * Config::float('economy', 'maint_' . $s, 1);
        }
        return (int)round($m);
    }

    /** Publicidad estática y merchandising: depende de los socios y de su ánimo */
    public static function ads(array $t): int
    {
        return (int)round((int)$t['fans'] * Config::float('sponsors', 'ads_per_fan', 4) * (0.5 + (float)$t['fan_mood'] / 10));
    }

    /** Actualización económica semanal de todos los equipos */
    public static function weeklyUpdate(int $season, int $week): void
    {
        // Ofertas de patrocinio no respondidas a tiempo: se firma automáticamente la primera
        SponsorService::autoSignExpired();
        $teams = DB::all('SELECT t.*, lu.level FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id');
        foreach ($teams as $t) {
            DB::transaction(function () use ($t, $season, $week) {
                $id = (int)$t['id'];
                self::transaction($id, self::ads($t), 'ads', tx('Publicidad estática y merchandising'), $season, $week);
                SponsorService::payWeekly($t, $season, $week);
                $wages = (int)DB::value('SELECT COALESCE(SUM(wage),0) FROM players WHERE team_id = ? AND retired = 0', [$id]);
                self::transaction($id, -$wages, 'wages', tx('Salarios de la plantilla'), $season, $week);
                self::transaction($id, -self::weeklyStaffCost($t), 'staff', tx('Entrenador y personal'), $season, $week);
                self::transaction($id, -self::maintenance($t), 'stadium', tx('Mantenimiento del estadio'), $season, $week);
                if ((int)$t['youth_level'] > 0) {
                    self::transaction($id, -(int)$t['youth_level'] * Config::int('economy', 'youth_cost', 5000), 'youth', tx('Inversión en cantera'), $season, $week);
                }
                $cash = (int)DB::value('SELECT cash FROM teams WHERE id = ?', [$id]);
                $neg = $cash < 0 ? min(255, (int)$t['negative_weeks'] + 1) : 0;
                DB::exec('UPDATE teams SET negative_weeks = ? WHERE id = ?', [$neg, $id]);
                if ($neg > 0 && !(int)$t['is_bot']) {
                    DB::insert('team_events', ['team_id' => $id, 'created_at' => now(), 'type' => 'finance',
                        'message' => tx('⚠️ Tu club está en números rojos ({n} semana(s)). Vende jugadores o reduce gastos.', ['n' => $neg])]);
                }
                // Los bots en bancarrota reciben ayuda de la federación para no romper la liga
                if ((int)$t['is_bot'] && $cash < -200000) {
                    self::transaction($id, 250000, 'other', tx('Ayuda de la federación'), $season, $week);
                }
            });
        }
    }

    /** Termina las obras del estadio que ya han acabado */
    public static function finishConstructions(): void
    {
        $rows = DB::all('SELECT * FROM teams WHERE build_finishes_at IS NOT NULL AND build_finishes_at <= ?', [now()]);
        foreach ($rows as $t) {
            DB::exec(
                'UPDATE teams SET seats_terraces = GREATEST(0, seats_terraces + build_terraces), seats_basic = GREATEST(0, seats_basic + build_basic),
                   seats_roof = GREATEST(0, seats_roof + build_roof), seats_vip = GREATEST(0, seats_vip + build_vip),
                   build_terraces = 0, build_basic = 0, build_roof = 0, build_vip = 0, build_finishes_at = NULL WHERE id = ?',
                [$t['id']]
            );
            if (!(int)$t['is_bot']) {
                DB::insert('team_events', ['team_id' => $t['id'], 'created_at' => now(), 'type' => 'stadium', 'message' => tx('🏟 Las obras de ampliación del estadio han finalizado.')]);
            }
        }
    }

    /** Calcula el coste de una obra. $changes = ['terraces' => +1000, 'vip' => -50, ...] */
    public static function constructionCost(array $changes): int
    {
        $cost = 0.0;
        $any = false;
        foreach ($changes as $type => $n) {
            if ($n > 0) {
                $cost += $n * Config::float('economy', 'build_' . $type, 50);
                $any = true;
            } elseif ($n < 0) {
                $cost += abs($n) * Config::float('economy', 'demolish_cost', 6);
                $any = true;
            }
        }
        return $any ? (int)round($cost + Config::int('economy', 'build_fixed_cost', 10000)) : 0;
    }
}
