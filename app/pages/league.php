<?php
require_login();
$my = current_team();
$navActive = 'league';
$unitId = (int)(get('unit') ?: ($my['league_unit_id'] ?? 0));
$unit = DB::row('SELECT * FROM league_units WHERE id = ?', [$unitId]) ?? DB::row('SELECT * FROM league_units ORDER BY level, number LIMIT 1');
$unitId = (int)$unit['id'];
$season = (int)(get('season') ?: GameState::season());
$pageTitle = 'Serie ' . $unit['name'];
$countryRow = DB::row('SELECT * FROM countries WHERE id = ?', [$unit['country_id']]);
$country = $countryRow['name'];
$parent = $unit['parent_id'] ? DB::row('SELECT * FROM league_units WHERE id = ?', [$unit['parent_id']]) : null;
$children = DB::all('SELECT * FROM league_units WHERE parent_id = ? ORDER BY number', [$unitId]);
$siblings = DB::all('SELECT * FROM league_units WHERE level = ? AND country_id = ? ORDER BY number', [$unit['level'], $unit['country_id']]);
$maxLevel = (int)DB::value('SELECT MAX(level) FROM league_units WHERE country_id = ?', [$unit['country_id']]);
$table = LeagueService::standings($unitId, $season);

$lastPlayed = (int)DB::value("SELECT MAX(round) FROM matches WHERE league_unit_id = ? AND season = ? AND status = 'played'", [$unitId, $season]);
$round = (int)(get('round') ?: max(1, $lastPlayed));
$games = DB::all('SELECT m.*, h.name AS hn, a.name AS an FROM matches m JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
    WHERE m.league_unit_id = ? AND m.season = ? AND m.round = ? ORDER BY m.id', [$unitId, $season, $round]);
$scorers = DB::all("SELECT p.id, p.first_name, p.last_name, t.name AS tn, SUM(mp.goals) g FROM match_players mp
    JOIN matches m ON m.id = mp.match_id JOIN players p ON p.id = mp.player_id JOIN teams t ON t.id = mp.team_id
    WHERE m.league_unit_id = ? AND m.season = ? AND m.type = 'league' GROUP BY p.id, p.first_name, p.last_name, t.name HAVING g > 0 ORDER BY g DESC LIMIT 10", [$unitId, $season]);
$seasons = array_column(DB::all('SELECT DISTINCT season FROM matches WHERE league_unit_id = ? ORDER BY season DESC', [$unitId]), 'season');
$n = count($table);
?>
<div class="page-title">
    <h1><?= flag($countryRow['code']) ?> Serie <?= e($unit['name']) ?> <span class="muted small"><a href="<?= e(url('nation', ['id' => $countryRow['id']])) ?>"><?= e($country) ?></a> · División <?= LeagueService::roman((int)$unit['level']) ?></span></h1>
    <form class="inline-form" method="get">
        <input type="hidden" name="page" value="league"><input type="hidden" name="unit" value="<?= $unitId ?>">
        <select name="season" onchange="this.form.submit()">
            <?php foreach ($seasons ?: [$season] as $s): ?><option value="<?= (int)$s ?>"<?= (int)$s === $season ? ' selected' : '' ?>>Temporada <?= (int)$s ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<div class="box">
    <div class="box-body small">
        <?php if ($parent): ?>⬆️ Serie superior: <a href="<?= e(url('league', ['unit' => $parent['id']])) ?>"><?= e($parent['name']) ?></a> &nbsp; <?php endif; ?>
        <?php if ($children): ?>⬇️ Series inferiores:
            <?php foreach ($children as $c): ?><a href="<?= e(url('league', ['unit' => $c['id']])) ?>"><?= e($c['name']) ?></a> <?php endforeach; ?>
        <?php endif; ?>
        <br>Otras series de la división:
        <?php foreach ($siblings as $s): ?>
            <?= (int)$s['id'] === $unitId ? '<strong>' . e($s['name']) . '</strong>' : '<a href="' . e(url('league', ['unit' => $s['id']])) . '">' . e($s['name']) . '</a>' ?>
        <?php endforeach; ?>
        <br>🏆 <a href="<?= e(url('cup', ['country' => $countryRow['id'], 'season' => $season])) ?>">Copa de <?= e($country) ?></a>
    </div>
</div>

<div class="box">
    <div class="box-head">Clasificación · Temporada <?= $season ?></div>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>#</th><th>Equipo</th><th class="num">PJ</th><th class="num">G</th><th class="num">E</th><th class="num">P</th><th class="num">GF</th><th class="num">GC</th><th class="num">DG</th><th class="num">Pts</th><th>Racha</th></tr></thead>
        <tbody>
        <?php foreach ($table as $r):
            $cls = [];
            if ($my && $r['team_id'] === (int)$my['id']) { $cls[] = 'me'; }
            if ($r['pos'] === 1 && (int)$unit['level'] > 1) { $cls[] = 'promo'; }
            if ($r['pos'] > $n - 2 && (int)$unit['level'] < $maxLevel) { $cls[] = 'releg'; }
        ?>
            <tr class="<?= implode(' ', $cls) ?>">
                <td><?= $r['pos'] ?></td>
                <td><?= team_link($r['team_id'], $r['name']) ?> <?= $r['is_bot'] ? '' : '<span class="tag tag-user" title="Equipo de un mánager">M</span>' ?></td>
                <td class="num"><?= $r['pj'] ?></td><td class="num"><?= $r['g'] ?></td><td class="num"><?= $r['e'] ?></td><td class="num"><?= $r['p'] ?></td>
                <td class="num"><?= $r['gf'] ?></td><td class="num"><?= $r['gc'] ?></td><td class="num"><?= $r['gf'] - $r['gc'] ?></td>
                <td class="num"><strong><?= $r['pts'] ?></strong></td>
                <td class="nowrap"><?php foreach ($r['form'] as $f): ?><span class="form-badge form-<?= $f ?>"><?= $f ?></span><?php endforeach; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="box-body small muted">
        <?php if ((int)$unit['level'] > 1): ?>El campeón puede ascender (asciende si está entre los 2 mejores campeones de las series hermanas). <?php endif; ?>
        <?php if ((int)$unit['level'] < $maxLevel): ?>Los dos últimos descienden.<?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head">Jornada <?= $round ?>
            <span>
                <?php if ($round > 1): ?><a href="<?= e(url('league', ['unit' => $unitId, 'season' => $season, 'round' => $round - 1])) ?>">« Anterior</a><?php endif; ?>
                <?php if ($round < LeagueService::LEAGUE_ROUNDS): ?> <a href="<?= e(url('league', ['unit' => $unitId, 'season' => $season, 'round' => $round + 1])) ?>">Siguiente »</a><?php endif; ?>
            </span>
        </div>
        <table class="table compact">
            <?php foreach ($games as $g): ?>
                <tr>
                    <td class="right"><?= e($g['hn']) ?></td>
                    <td class="center nowrap"><a href="<?= e(url('match', ['id' => $g['id']])) ?>"><?= $g['status'] === 'played' ? '<strong>' . (int)$g['home_goals'] . ' - ' . (int)$g['away_goals'] . '</strong>' : e(date('d/m H:i', strtotime($g['scheduled_at']))) ?></a></td>
                    <td><?= e($g['an']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div class="box">
        <div class="box-head">Máximos goleadores</div>
        <table class="table compact">
            <?php foreach ($scorers as $i => $s): ?>
                <tr><td><?= $i + 1 ?></td><td><?= player_link($s) ?></td><td class="muted small"><?= e($s['tn']) ?></td><td class="num"><strong><?= (int)$s['g'] ?></strong></td></tr>
            <?php endforeach; ?>
            <?php if (!$scorers): ?><tr><td class="muted">Todavía no hay goles.</td></tr><?php endif; ?>
        </table>
    </div>
</div>
