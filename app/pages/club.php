<?php
$team = require_team();
$user = current_user();
$teamId = (int)$team['id'];
$pageTitle = t('Mi club');
$navActive = 'club';
$tab = (string)get('tab', 'summary');
if (!in_array($tab, ['summary', 'kit', 'crest'], true)) {
    $tab = 'summary';
}

/* ------------------------------ Acciones ------------------------------ */
if (is_post()) {
    csrf_check();
    $action = (string)post('action');
    $what = (string)post('what');
    if (!in_array($what, ['kit', 'crest'], true)) {
        redirect(url('home'));
    }
    $back = url('home', array_filter(['tab' => $what, 'f' => get('f'), 's' => get('s'), 'c' => get('c'), 'p' => get('p')], fn($v) => $v !== '' && $v !== null));
    $label = $what === 'kit' ? t('Uniforme') : t('Escudo');
    if ($action === 'select') {
        $n = (int)post('n');
        if ($n >= 1 && $n <= ClubArt::COUNT) {
            ClubArt::deleteUpload($team[$what . '_custom'] ?? null);
            DB::update('teams', [$what . '_id' => $n, $what . '_custom' => null], 'id = ?', [$teamId]);
            flash('success', t('{what} cambiado: has elegido el diseño nº {n}.', ['what' => $label, 'n' => $n]));
        }
    } elseif ($action === 'upload') {
        [$path, $err] = ClubArt::storeUpload($_FILES['image'] ?? [], $what, $teamId);
        if ($err) {
            flash('error', $err);
        } else {
            ClubArt::deleteUpload($team[$what . '_custom'] ?? null);
            DB::update('teams', [$what . '_custom' => $path], 'id = ?', [$teamId]);
            flash('success', t('{what} personalizado guardado.', ['what' => $label]));
        }
    } elseif ($action === 'remove') {
        ClubArt::deleteUpload($team[$what . '_custom'] ?? null);
        DB::update('teams', [$what . '_custom' => null], 'id = ?', [$teamId]);
        flash('success', t('Imagen personalizada eliminada.'));
    }
    redirect($back);
}

$team = DB::row('SELECT * FROM teams WHERE id = ?', [$teamId]);
?>
<div class="page-title club-title">
    <h1><?= crest_img($team, 30) ?> <?= e($team['name']) ?></h1>
</div>
<div class="tabs">
    <a class="<?= $tab === 'summary' ? 'active' : '' ?>" href="<?= e(url('home')) ?>"><?= e(t('Resumen general')) ?></a>
    <a class="<?= $tab === 'kit' ? 'active' : '' ?>" href="<?= e(url('home', ['tab' => 'kit'])) ?>">👕 <?= e(t('Uniforme')) ?></a>
    <a class="<?= $tab === 'crest' ? 'active' : '' ?>" href="<?= e(url('home', ['tab' => 'crest'])) ?>">🛡 <?= e(t('Escudo')) ?></a>
</div>

<?php if ($tab === 'summary'):
    $season = GameState::season();
    $country = DB::row('SELECT * FROM countries WHERE id = ?', [$team['country_id']]);
    $unit = $team['league_unit_id'] ? DB::row('SELECT * FROM league_units WHERE id = ?', [$team['league_unit_id']]) : null;
    $pos = null;
    $row = null;
    if ($unit) {
        foreach (LeagueService::standings((int)$unit['id'], $season) as $i => $r) {
            if ((int)$r['team_id'] === $teamId) {
                $pos = $i + 1;
                $row = $r;
            }
        }
    }
    $squad = DB::row('SELECT COUNT(*) n, AVG(age_years) age, SUM(tsi) tsi, SUM(wage) wages, AVG(form) form FROM players WHERE team_id = ? AND retired = 0', [$teamId]);
    $trainer = StaffService::trainer($teamId);
    $staffN = (int)DB::value("SELECT COUNT(*) FROM staff WHERE team_id = ? AND role <> 'trainer'", [$teamId]);
    $capacity = (int)$team['seats_terraces'] + (int)$team['seats_basic'] + (int)$team['seats_roof'] + (int)$team['seats_vip'];
    $sponsor = SponsorService::activeContract($teamId);
    $recent = LeagueService::teamMatches($teamId, "status = 'played'", [], 'x.played_at DESC', 5);
    $history = DB::all('SELECT sh.*, lu.name FROM season_history sh JOIN league_units lu ON lu.id = sh.league_unit_id WHERE sh.team_id = ? ORDER BY season DESC LIMIT 8', [$teamId]);
    $titles = (int)DB::value('SELECT COUNT(*) FROM season_history WHERE team_id = ? AND position = 1', [$teamId])
        + (int)DB::value('SELECT COUNT(*) FROM league_cups WHERE winner_team_id = ?', [$teamId]);
    $best = DB::row('SELECT id, team_id, first_name, last_name, tsi, avatar FROM players WHERE team_id = ? AND retired = 0 ORDER BY tsi DESC LIMIT 1', [$teamId]);
    $scorer = DB::row('SELECT id, team_id, first_name, last_name, goals_season, avatar FROM players WHERE team_id = ? AND retired = 0 AND goals_season > 0 ORDER BY goals_season DESC LIMIT 1', [$teamId]);
    $last = LeagueService::lastMatch($teamId);
    $next = LeagueService::nextMatch($teamId);
    $news = DB::all('SELECT * FROM team_events WHERE team_id = ? ORDER BY id DESC LIMIT 20', [$teamId]);
    $announcements = DB::all('SELECT * FROM announcements ORDER BY id DESC LIMIT 3');
    $events = DB::all("SELECT * FROM scheduled_events WHERE status = 'pending' ORDER BY run_at LIMIT 5");
    $sponsorOffers = count(SponsorService::offers($teamId));
    $friendlyReq = (int)DB::value("SELECT COUNT(*) FROM friendly_requests WHERE to_team_id = ? AND status = 'pending'", [$teamId]);
    $injured = (int)DB::value('SELECT COUNT(*) FROM players WHERE team_id = ? AND retired = 0 AND injury_weeks > 0', [$teamId]);
    $resultNames = ['champion' => '🏆 ' . t('Campeón'), 'promoted' => '⬆️ ' . t('Ascenso'), 'relegated' => '⬇️ ' . t('Descenso'), 'stay' => ''];
?>
<?php if ($sponsorOffers): ?>
    <div class="alert alert-info">💼 <?= e(t('Tienes {n} ofertas de patrocinio esperando respuesta.', ['n' => $sponsorOffers])) ?> <a href="<?= e(url('sponsors')) ?>"><?= e(t('Elegir patrocinador')) ?></a></div>
<?php endif; ?>
<?php if ($friendlyReq): ?>
    <div class="alert alert-info"><?= e(t('Tienes {n} invitación(es) para jugar un amistoso.', ['n' => $friendlyReq])) ?> <a href="<?= e(url('friendlies')) ?>"><?= e(t('Responder')) ?></a></div>
<?php endif; ?>
<?php if ((int)$team['cash'] < 0): ?>
    <div class="alert alert-error"><?= e(t('Tu club tiene saldo negativo. Vende jugadores o reduce gastos cuanto antes.')) ?></div>
<?php endif; ?>

<div class="box club-hero">
    <div class="club-hero-crest"><?= crest_img($team, 130, 'crest crest-big') ?><a class="btn btn-small" href="<?= e(url('home', ['tab' => 'crest'])) ?>">🛡 <?= e(t('Cambiar escudo')) ?></a></div>
    <div class="club-hero-main">
        <h2><?= e($team['name']) ?></h2>
        <div class="muted"><?= flag($country['code'] ?? '') ?> <?= e(t($country['name'] ?? '')) ?>
            <?php if ($unit): ?> · <a href="<?= e(url('league', ['unit' => $unit['id']])) ?>"><?= e(t('División {name}', ['name' => $unit['name']])) ?></a><?php endif; ?>
        </div>
        <div class="club-badges">
            <span class="cb"><b class="<?= (int)$team['cash'] < 0 ? 'neg' : '' ?>"><?= e(money($team['cash'])) ?></b><?= e(t('Caja')) ?></span>
            <span class="cb"><b><?= $pos ? $pos . 'º' : '–' ?></b><?= e(t('Posición')) ?></span>
            <span class="cb"><b><?= (int)($row['pts'] ?? 0) ?></b><?= e(t('Puntos')) ?></span>
            <span class="cb"><b><?= num($team['fans']) ?></b><?= e(t('Socios')) ?></span>
            <span class="cb"><b><?= num($capacity) ?></b><?= e(t('Aforo')) ?></span>
            <span class="cb"><b><?= $titles ?></b><?= e(t('Títulos')) ?></span>
        </div>
        <div class="small muted"><?= e(t('Mánager')) ?>: <?= manager_link($user['id'], $user['username'], true) ?> · <?= e(t('Estadio')) ?>: <a href="<?= e(url('stadium')) ?>"><?= e($team['stadium_name']) ?></a></div>
    </div>
    <div class="club-hero-kit"><?= kit_img($team, 120) ?><a class="btn btn-small" href="<?= e(url('home', ['tab' => 'kit'])) ?>">👕 <?= e(t('Cambiar uniforme')) ?></a></div>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head"><?= e(t('Último partido')) ?> <?php if ($last): ?><a class="btn btn-small btn-grey" href="<?= e(url('match', ['id' => $last['id']])) ?>"><?= e(t('Ver crónica')) ?></a><?php endif; ?></div>
        <div class="box-body center">
            <?php if ($last): ?>
                <div class="muted small"><?= e(match_type_label($last)) ?> · <?= e(fdate($last['played_at'])) ?></div>
                <div class="mini-match"><?= crest_img(['id' => $last['home_team_id'], 'crest_id' => $last['h_crest_id'], 'crest_custom' => $last['h_crest_custom']], 26) ?> <?= e($last['home_name']) ?> <strong>&nbsp;<?= e(score_label($last)) ?>&nbsp;</strong> <?= e($last['away_name']) ?> <?= crest_img(['id' => $last['away_team_id'], 'crest_id' => $last['a_crest_id'], 'crest_custom' => $last['a_crest_custom']], 26) ?></div>
                <div class="muted small"><?= e(t('Espectadores: {n}', ['n' => num($last['attendance'])])) ?></div>
            <?php else: ?>
                <p class="muted"><?= e(t('Todavía no has jugado ningún partido.')) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Próximo partido')) ?> <?php if ($next): ?><span><a class="btn btn-small btn-grey" href="<?= e(url('match', ['id' => $next['id']])) ?>"><?= e(t('Previa')) ?></a> <a class="btn btn-small" href="<?= e(url('lineup', ['match' => $next['id']])) ?>"><?= e(t('Alineación')) ?></a></span><?php endif; ?></div>
        <div class="box-body center">
            <?php if ($next): ?>
                <div class="muted small"><?= e(match_type_label($next)) ?></div>
                <div class="mini-match"><?= crest_img(['id' => $next['home_team_id'], 'crest_id' => $next['h_crest_id'], 'crest_custom' => $next['h_crest_custom']], 26) ?> <?= e($next['home_name']) ?> <span class="muted">vs</span> <?= e($next['away_name']) ?> <?= crest_img(['id' => $next['away_team_id'], 'crest_id' => $next['a_crest_id'], 'crest_custom' => $next['a_crest_custom']], 26) ?></div>
                <div><?= e(fdate_long($next['scheduled_at'])) ?> · <span class="countdown" style="font-size:13px" data-deadline="<?= e(date('c', strtotime($next['scheduled_at']))) ?>"></span></div>
            <?php else: ?>
                <p class="muted"><?= e(t('No hay partidos programados.')) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Datos del club')) ?></div>
        <table class="kv">
            <tr><th><?= e(t('Fundado')) ?></th><td><?= e(fdate($team['founded_at'] ?? null, false)) ?></td></tr>
            <tr><th><?= e(t('Patrocinador')) ?></th><td><?php if ($sponsor): ?><a href="<?= e(url('sponsors')) ?>"><img src="<?= e(SponsorService::logoUrl((int)$sponsor['company_id'])) ?>" width="22" height="22" class="avatar" alt=""> <?= e($sponsor['company']) ?></a> · <?= e(t('{amount}/sem.', ['amount' => money($sponsor['weekly_amount'])])) ?><?php else: ?><a href="<?= e(url('sponsors')) ?>"><?= e(t('Sin patrocinador')) ?></a><?php endif; ?></td></tr>
            <tr><th><?= e(t('Socios')) ?></th><td><?= num($team['fans']) ?> · <?= e(t('afición {mood}', ['mood' => mood_name($team['fan_mood'], 'fans')])) ?></td></tr>
            <tr><th><?= e(t('Espíritu del equipo')) ?></th><td><?= e(mood_name($team['team_spirit'], 'spirit')) ?></td></tr>
            <tr><th><?= e(t('Confianza')) ?></th><td><?= e(mood_name($team['confidence'], 'confidence')) ?></td></tr>
            <tr><th><?= e(t('Entrenamiento')) ?></th><td><a href="<?= e(url('training')) ?>"><?= e(skill_title($team['training_type'])) ?></a> (<?= (int)$team['training_intensity'] ?>%)</td></tr>
            <tr><th><?= e(t('Entrenador')) ?></th><td><?= $trainer ? StaffService::link($trainer) . ' · ' . e(t('nivel {n}', ['n' => (int)$trainer['level']])) : '–' ?></td></tr>
            <tr><th><?= e(t('Personal')) ?></th><td><a href="<?= e(url('staff')) ?>"><?= e(t('{n} personas', ['n' => $staffN])) ?></a></td></tr>
            <tr><th><?= e(t('Estadio')) ?></th><td><a href="<?= e(url('stadium')) ?>"><?= e($team['stadium_name']) ?></a> · <?= e(t('{n} plazas', ['n' => num($capacity)])) ?></td></tr>
        </table>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Plantilla')) ?></div>
        <table class="kv">
            <tr><th><?= e(t('Jugadores')) ?></th><td><a href="<?= e(url('players')) ?>"><?= e(t('{n} ({injured} lesionados)', ['n' => (int)$squad['n'], 'injured' => $injured])) ?></a></td></tr>
            <tr><th><?= e(t('Edad media')) ?></th><td><?= e(t('{n} años', ['n' => num($squad['age'], 1)])) ?></td></tr>
            <tr><th><?= e(t('TSI total')) ?></th><td><?= num($squad['tsi']) ?></td></tr>
            <tr><th><?= e(t('Forma media')) ?></th><td><?= e(form_name($squad['form'])) ?></td></tr>
            <tr><th><?= e(t('Salarios por semana')) ?></th><td><?= e(money($squad['wages'])) ?></td></tr>
            <tr><th><?= e(t('Jugador más valioso')) ?></th><td><?= $best ? avatar_img($best, 20) . ' ' . player_link($best) . ' <span class="small muted">TSI ' . num($best['tsi']) . '</span>' : '–' ?></td></tr>
            <tr><th><?= e(t('Máximo goleador')) ?></th><td><?= $scorer ? avatar_img($scorer, 20) . ' ' . player_link($scorer) . ' <span class="small muted">' . e(t('{n} goles', ['n' => (int)$scorer['goals_season']])) . '</span>' : '–' ?></td></tr>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Temporada {n}', ['n' => $season])) ?></div>
        <?php if ($row): ?>
        <table class="kv">
            <tr><th><?= e(t('Partidos')) ?></th><td><?= (int)$row['pj'] ?> · <span class="pos"><?= (int)$row['g'] ?><?= e(t('G##ganado')) ?></span> <?= (int)$row['e'] ?><?= e(t('E##empate')) ?> <span class="neg"><?= (int)$row['p'] ?><?= e(t('P##perdido')) ?></span></td></tr>
            <tr><th><?= e(t('Goles')) ?></th><td><?= (int)$row['gf'] ?> : <?= (int)$row['gc'] ?></td></tr>
        </table>
        <?php endif; ?>
        <table class="table compact">
            <?php foreach ($recent as $m): $home = (int)$m['home_team_id'] === $teamId; $gf = $home ? (int)$m['home_goals'] : (int)$m['away_goals']; $ga = $home ? (int)$m['away_goals'] : (int)$m['home_goals']; $res = $gf <=> $ga; ?>
                <tr><td><span class="res res-<?= $res > 0 ? 'w' : ($res < 0 ? 'l' : 'd') ?>"><?= $res > 0 ? e(t('G##ganado')) : ($res < 0 ? e(t('P##perdido')) : e(t('E##empate'))) ?></span></td>
                    <td><?= e(match_type_label($m)) ?></td>
                    <td><?= e($m['home_name']) ?> – <?= e($m['away_name']) ?></td>
                    <td class="num"><a href="<?= e(url('match', ['id' => $m['id']])) ?>"><?= e(score_label($m)) ?></a></td></tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td class="muted"><?= e(t('Todavía no se ha jugado ningún partido.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Calendario del juego')) ?></div>
        <table class="table compact">
            <?php foreach ($events as $ev): ?>
                <tr><td><?= e(t(Scheduler::eventName($ev['type']))) ?><?= $ev['round'] ? ' ' . (int)$ev['round'] : '' ?></td><td class="num"><?= e(fdate_long($ev['run_at'])) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<?php foreach ($announcements as $a): ?>
<div class="box">
    <div class="box-head">📢 <?= e(tdb($a['title'])) ?> <span class="muted small"><?= e(fdate($a['created_at'])) ?></span></div>
    <div class="box-body"><?= nl2br(e(tdb($a['body']))) ?></div>
</div>
<?php endforeach; ?>

<div class="box">
    <div class="box-head"><?= e(t('Noticias del club')) ?></div>
    <?php if ($news): ?>
        <ul class="news">
            <?php foreach ($news as $n): ?>
                <li><span class="when"><?= e(fdate($n['created_at'])) ?></span><span><?= e(tdb($n['message'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="box-body muted"><?= e(t('Sin noticias.')) ?></div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Historial de temporadas')) ?></div>
        <table class="table compact">
            <thead><tr><th><?= e(t('Temp.')) ?></th><th><?= e(t('División')) ?></th><th class="num"><?= e(t('Pos.')) ?></th><th class="num"><?= e(t('Pts')) ?></th><th></th></tr></thead>
            <?php foreach ($history as $h): ?>
                <tr><td><?= (int)$h['season'] ?></td><td><?= e($h['name']) ?></td><td class="num"><?= (int)$h['position'] ?></td><td class="num"><?= (int)$h['points'] ?></td><td><?= e($resultNames[$h['result']] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="5" class="muted"><?= e(t('Es la primera temporada del club.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div><?= trophy_cabinet($teamId, true) ?></div>
</div>

<?php else:
    /* ------------------------- Uniforme / escudo ------------------------- */
    $what = $tab;
    $isKit = $what === 'kit';
    $current = team_art_n($team, $what);
    $custom = $team[$what . '_custom'];
    $styles = $isKit ? ClubArt::KIT_PATTERNS : ClubArt::CREST_SHAPES;
    $f = get('f', '') === '' ? -1 : (int)get('f');
    $c = (string)get('c', '');
    $s2 = get('s', '') === '' ? -1 : (int)get('s');
    $ids = [];
    for ($n = 1; $n <= ClubArt::COUNT; $n++) {
        $spec = $isKit ? ClubArt::kitSpec($n) : ClubArt::crestSpec($n);
        if ($f >= 0 && ($isKit ? $spec['pattern'] : $spec['shape']) !== $f) {
            continue;
        }
        if (!$isKit && $s2 >= 0 && $spec['symbol'] !== $s2) {
            continue;
        }
        if ($c !== '' && $spec['primary'] !== $c && $spec['secondary'] !== $c) {
            continue;
        }
        $ids[] = $n;
    }
    $per = 40;
    $pg = max(1, (int)get('p', 1));
    $total = count($ids);
    $pageIds = array_slice($ids, ($pg - 1) * $per, $per);
    [$rw, $rh, $min, $max] = ClubArt::UPLOAD_LIMITS[$what];
    $params = array_filter(['tab' => $what, 'f' => $f >= 0 ? $f : null, 'c' => $c ?: null, 's' => $s2 >= 0 ? $s2 : null], fn($v) => $v !== null);
?>
<div class="grid-2 art-top">
    <div class="box"><div class="box-head"><?= e($isKit ? t('Uniforme actual') : t('Escudo actual')) ?></div>
        <div class="box-body art-current">
            <?= $isKit ? kit_img($team, 150) : crest_img($team, 140, 'crest crest-big') ?>
            <div>
                <?php if ($custom): ?>
                    <p><strong><?= e(t('Imagen personalizada')) ?></strong></p>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="what" value="<?= e($what) ?>"><input type="hidden" name="action" value="remove">
                        <button class="btn btn-small btn-grey" type="submit"><?= e(t('Quitar imagen personalizada')) ?></button></form>
                    <p class="small muted"><?= e(t('Si la quitas volverás al diseño nº {n}.', ['n' => $current])) ?></p>
                <?php else: ?>
                    <p><strong><?= e(t('Diseño nº {n}', ['n' => $current])) ?></strong></p>
                    <p class="small muted"><?= e($isKit ? t('Elige otro uniforme en la galería o sube el tuyo.') : t('Elige otro escudo en la galería o sube el tuyo.')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="box"><div class="box-head"><?= e($isKit ? t('Subir mi propio uniforme') : t('Subir mi propio escudo')) ?></div>
        <div class="box-body">
            <form method="post" enctype="multipart/form-data" class="upload-form">
                <?= csrf_field() ?><input type="hidden" name="what" value="<?= e($what) ?>"><input type="hidden" name="action" value="upload">
                <input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp" required>
                <button class="btn" type="submit">⬆️ <?= e(t('Subir')) ?></button>
            </form>
            <ul class="small upload-help">
                <?php if ($isKit): ?>
                    <li><?= t('Tamaño recomendado: <strong>{w}×{h} píxeles</strong> (camiseta arriba y pantalón debajo, centrados).', ['w' => $rw, 'h' => $rh]) ?></li>
                <?php else: ?>
                    <li><?= t('Tamaño recomendado: <strong>{w}×{h} píxeles</strong> (cuadrado, con el escudo centrado).', ['w' => $rw, 'h' => $rh]) ?></li>
                <?php endif; ?>
                <li><?= e(t('Cada lado debe medir entre {min} y {max} píxeles.', ['min' => $min, 'max' => $max])) ?></li>
                <li><?= e(t('Formatos: PNG (mejor con fondo transparente), JPG, GIF o WEBP. Máximo {kb} KB.', ['kb' => Config::int('app', 'upload_max_kb', 1024)])) ?></li>
                <li><?= e(t('La imagen se mostrará sin recortar: deja un pequeño margen alrededor.')) ?></li>
            </ul>
        </div>
    </div>
</div>

<div class="box">
    <div class="box-head"><?= e($isKit ? t('Galería de uniformes') : t('Galería de escudos')) ?> <span class="small"><?= e(t('{n} diseños', ['n' => num($total)])) ?></span></div>
    <form method="get" class="box-body inline-form art-filters">
        <input type="hidden" name="page" value="home"><input type="hidden" name="tab" value="<?= e($what) ?>">
        <select name="f" onchange="this.form.submit()">
            <option value=""><?= e($isKit ? t('Todos los diseños') : t('Todas las formas')) ?></option>
            <?php foreach ($styles as $i => $label): ?><option value="<?= $i ?>"<?= $f === $i ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach; ?>
        </select>
        <?php if (!$isKit): ?>
        <select name="s" onchange="this.form.submit()">
            <option value=""><?= e(t('Todos los símbolos')) ?></option>
            <?php foreach (ClubArt::CREST_SYMBOLS as $i => $label): ?><option value="<?= $i ?>"<?= $s2 === $i ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <span class="color-filter">
            <a class="swatch any<?= $c === '' ? ' on' : '' ?>" href="<?= e(url('home', array_diff_key($params, ['c' => 1]))) ?>" title="<?= e(t('Todos los colores')) ?>">✱</a>
            <?php foreach (ClubArt::COLORS as $key => [$hex, $cname]): ?>
                <a class="swatch<?= $c === $key ? ' on' : '' ?>" style="background:<?= e($hex) ?>" href="<?= e(url('home', array_merge($params, ['c' => $key]))) ?>" title="<?= e(t($cname)) ?>"></a>
            <?php endforeach; ?>
        </span>
    </form>
    <form method="post" class="art-grid">
        <?= csrf_field() ?><input type="hidden" name="what" value="<?= e($what) ?>"><input type="hidden" name="action" value="select">
        <?php foreach ($pageIds as $n): ?>
            <button type="submit" name="n" value="<?= $n ?>" class="art-item<?= !$custom && $n === $current ? ' on' : '' ?>" title="<?= e(t('Elegir el diseño nº {n}', ['n' => $n])) ?>">
                <img src="<?= e(art_url($what, $n)) ?>" width="<?= $isKit ? 84 : 76 ?>" height="<?= $isKit ? 97 : 87 ?>" alt="" loading="lazy">
                <span>nº <?= $n ?></span>
            </button>
        <?php endforeach; ?>
        <?php if (!$pageIds): ?><p class="box-body muted"><?= e(t('Ningún diseño coincide con el filtro.')) ?></p><?php endif; ?>
    </form>
    <?= paginate($total, $per, $pg, 'home', $params) ?>
</div>
<?php endif; ?>
