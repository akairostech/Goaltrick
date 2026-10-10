<?php
require_login();
$myTeam = current_team();
$id = (int)get('id');
$p = DB::row('SELECT p.*, c.name AS cname, c.code AS ccode, t.name AS team_name, t.is_bot FROM players p
    JOIN countries c ON c.id = p.country_id LEFT JOIN teams t ON t.id = p.team_id WHERE p.id = ?', [$id]);
if (!$p) {
    flash('error', 'Jugador no encontrado.');
    redirect(url('players'));
}
$own = $myTeam && (int)$p['team_id'] === (int)$myTeam['id'];
$listing = TransferService::activeListing($id);
$navActive = $own ? 'club' : 'transfers';
$pageTitle = player_name($p);

if (is_post() && $myTeam) {
    csrf_check();
    $action = (string)post('action');
    $err = null;
    $ok = '';
    switch ($action) {
        case 'sell':
            $err = $own ? TransferService::list($myTeam, $id, (int)post('price')) : 'No es tu jugador.';
            $ok = 'Jugador puesto a la venta.';
            break;
        case 'cancel':
            $err = $listing ? TransferService::cancel($myTeam, (int)$listing['id']) : 'No está en venta.';
            $ok = 'Venta cancelada.';
            break;
        case 'bid':
            $err = $listing ? TransferService::bid($myTeam, (int)$listing['id'], (int)post('amount')) : 'El jugador no está en venta.';
            $ok = 'Puja realizada correctamente.';
            break;
        case 'fire':
            $err = $own ? TransferService::fire($myTeam, $id) : 'No es tu jugador.';
            $ok = 'Jugador despedido.';
            if (!$err) {
                flash('success', $ok);
                redirect(url('players'));
            }
            break;
    }
    flash($err ? 'error' : 'success', $err ?? $ok);
    redirect(url('player', ['id' => $id]));
}

$history = DB::all('SELECT mp.*, m.type, m.played_at, m.home_goals, m.away_goals, h.name AS hn, a.name AS an, m.id AS mid
    FROM match_players mp JOIN matches m ON m.id = mp.match_id JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
    WHERE mp.player_id = ? ORDER BY m.played_at DESC LIMIT 10', [$id]);
$events = DB::all('SELECT * FROM player_events WHERE player_id = ? ORDER BY id DESC LIMIT 15', [$id]);
$bids = $listing ? DB::all('SELECT b.*, t.name FROM transfer_bids b JOIN teams t ON t.id = b.team_id WHERE b.listing_id = ? ORDER BY b.amount DESC LIMIT 10', [$listing['id']]) : [];
$value = PlayerFactory::value($p);
?>
<div class="page-title">
    <div class="player-head"><img class="avatar-big" src="<?= e(avatar_url($p)) ?>" alt=""><div><h1><?= e(player_name($p)) ?> <?= specialty_badge($p['specialty']) ?></h1>
        <div class="muted"><?= e(ROLES[$p['role']] ?? '') ?> · <?= e(age_label($p)) ?> · TSI <?= num($p['tsi']) ?></div></div></div>
    <span class="muted">
        <?php if ($p['team_id']): ?><?= team_link($p['team_id'], $p['team_name']) ?><?php else: ?>Sin equipo<?= (int)$p['retired'] ? ' (retirado)' : '' ?><?php endif; ?>
    </span>
</div>

<div class="grid-2">
    <div class="box">
        <div class="box-head">Datos personales</div>
        <table class="kv">
            <tr><th>Edad</th><td><?= e(age_label($p)) ?></td></tr>
            <tr><th>Nacionalidad</th><td><?= nation_link($p['country_id'], $p['cname'], $p['ccode']) ?></td></tr>
            <tr><th>Posición natural</th><td><?= e(ROLES[$p['role']] ?? $p['role']) ?></td></tr>
            <tr><th>TSI</th><td><?= num($p['tsi']) ?></td></tr>
            <tr><th>Salario</th><td><?= e(money($p['wage'])) ?> / semana</td></tr>
            <tr><th>Valor estimado</th><td><?= e(money($value)) ?></td></tr>
            <tr><th>Forma</th><td><?= e(form_name($p['form'])) ?> (<?= (int)floor((float)$p['form']) ?>)</td></tr>
            <tr><th>Experiencia</th><td><?= e(skill_label($p['experience'])) ?></td></tr>
            <tr><th>Liderazgo</th><td><?= e(skill_label($p['leadership'])) ?></td></tr>
            <tr><th>Fidelidad</th><td><?= e(skill_label($p['loyalty'])) ?></td></tr>
            <tr><th>Estado</th><td>
                <?php if ((float)$p['injury_weeks'] > 0.5): ?><span class="tag tag-injury">Lesionado: <?= ceil((float)$p['injury_weeks']) ?> semana(s)</span>
                <?php elseif ((float)$p['injury_weeks'] > 0): ?><span class="tag tag-bruise">Magullado</span>
                <?php else: ?><span class="tag tag-user">Sano</span><?php endif; ?>
                <?php if ((int)$p['suspended']): ?><span class="tag tag-red">Sancionado</span><?php endif; ?>
                <?php if ((int)$p['yellow_cards']): ?><span class="tag tag-card"><?= (int)$p['yellow_cards'] ?> amarilla(s)</span><?php endif; ?>
            </td></tr>
            <tr><th>Selección</th><td><?php $nt = NationService::nationalTeam((int)$p['country_id']); ?>
                <?= (int)$p['caps'] ?> partido(s) internacional(es) · <?= (int)$p['goals_national'] ?> gol(es)
                <?php if ($p['team_id'] && !(int)$p['retired'] && NationService::isCalledUp($p)): ?><br><span class="tag tag-user">Convocado por <?= e($p['cname']) ?></span><?php endif; ?>
            </td></tr>
            <tr><th>Goles</th><td><?= (int)$p['goals_season'] ?> esta temporada · <?= (int)$p['goals_career'] ?> en total (<?= (int)$p['matches_career'] ?> partidos)</td></tr>
        </table>
    </div>
    <div class="box">
        <div class="box-head">Habilidades</div>
        <table class="kv skills">
            <?php foreach (array_merge(['stamina' => 'Condición'], SKILLS) as $k => $label): ?>
                <tr class="skill-row">
                    <th><?= e($label) ?></th>
                    <td><?= skill_bar($p[$k]) ?> <span class="skill-txt"><?= e(skill_name($p[$k])) ?></span> <span class="skill-num">(<?= (int)floor((float)$p[$k]) ?>)</span></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<br>

<?php if ($listing): ?>
<div class="box">
    <div class="box-head">🏷 En el mercado de fichajes</div>
    <div class="box-body">
        <div class="grid-2">
            <table class="kv">
                <tr><th>Precio mínimo</th><td><?= e(money($listing['asking_price'])) ?></td></tr>
                <tr><th>Puja más alta</th><td><?= $listing['highest_bid'] ? e(money($listing['highest_bid'])) . ' (' . e(DB::value('SELECT name FROM teams WHERE id = ?', [$listing['highest_bidder_id']])) . ')' : '<span class="muted">Sin pujas</span>' ?></td></tr>
                <tr><th>Cierre</th><td><?= e(fdate($listing['deadline'])) ?> · <span data-deadline="<?= e(date('c', strtotime($listing['deadline']))) ?>"></span></td></tr>
            </table>
            <div>
                <?php if ($own): ?>
                    <?php if (!$listing['highest_bid']): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                            <button class="btn btn-grey" type="submit">Retirar del mercado</button></form>
                    <?php else: ?>
                        <p class="muted">Ya hay pujas: la venta no se puede cancelar.</p>
                    <?php endif; ?>
                <?php elseif ($myTeam): ?>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="bid">
                        <input type="number" name="amount" min="<?= TransferService::minNextBid($listing) ?>" step="1000" value="<?= TransferService::minNextBid($listing) ?>" required>
                        <button class="btn btn-accent" type="submit">Pujar</button>
                    </form>
                    <p class="muted small">Puja mínima: <?= e(money(TransferService::minNextBid($listing))) ?>. Tu caja: <?= e(money($myTeam['cash'])) ?>.</p>
                <?php endif; ?>
                <?php if ($bids): ?>
                    <table class="table compact"><thead><tr><th>Equipo</th><th class="num">Puja</th><th>Hora</th></tr></thead><tbody>
                    <?php foreach ($bids as $b): ?><tr><td><?= e($b['name']) ?></td><td class="num"><?= e(money($b['amount'])) ?></td><td><?= e(fdate($b['created_at'])) ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php elseif ($own): ?>
<div class="box">
    <div class="box-head">Acciones</div>
    <div class="box-body">
        <div class="grid-2">
            <form method="post" class="inline-form" data-confirm-submit="¿Seguro que quieres poner a <?= e(player_name($p)) ?> a la venta?">
                <?= csrf_field() ?><input type="hidden" name="action" value="sell">
                <label for="price" style="margin:0">Precio mínimo:</label>
                <input type="number" id="price" name="price" min="1000" step="1000" value="<?= (int)(round($value * 0.8 / 1000) * 1000) ?>" required>
                <button class="btn" type="submit">Poner a la venta</button>
            </form>
            <form method="post" class="right" data-confirm-submit="¿Despedir a <?= e(player_name($p)) ?>? Pagarás 2 semanas de salario como indemnización y el jugador se irá del club.">
                <?= csrf_field() ?><input type="hidden" name="action" value="fire">
                <button class="btn btn-danger" type="submit">Despedir</button>
            </form>
        </div>
        <p class="muted small">La subasta durará <?= Config::int('game', 'transfer_hours', 72) ?> horas. Si hay pujas en los últimos minutos, el cierre se alarga.</p>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="box">
        <div class="box-head">Últimos partidos</div>
        <?php if ($history): ?>
        <table class="table compact">
            <thead><tr><th>Fecha</th><th>Partido</th><th>Pos</th><th class="num">Min</th><th>Valoración</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td class="nowrap"><?= e(fdate($h['played_at'], false)) ?></td>
                    <td><a href="<?= e(url('match', ['id' => $h['mid']])) ?>"><?= e($h['hn']) ?> <?= (int)$h['home_goals'] ?>-<?= (int)$h['away_goals'] ?> <?= e($h['an']) ?></a>
                        <?= $h['goals'] ? ' ⚽×' . (int)$h['goals'] : '' ?><?= $h['injured'] ? ' 🩹' : '' ?><?= $h['red'] ? ' 🟥' : ($h['yellow'] ? ' 🟨' : '') ?></td>
                    <td><?= e($h['position']) ?></td>
                    <td class="num"><?= (int)$h['minutes'] ?></td>
                    <td><?= stars($h['stars']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="box-body muted">Sin partidos.</div><?php endif; ?>
    </div>
    <div class="box">
        <div class="box-head">Historial</div>
        <?php if ($events): ?>
            <ul class="news"><?php foreach ($events as $ev): ?><li><span class="when"><?= e(fdate($ev['created_at'], false)) ?></span><span><?= e($ev['message']) ?></span></li><?php endforeach; ?></ul>
        <?php else: ?><div class="box-body muted">Sin eventos.</div><?php endif; ?>
    </div>
</div>
