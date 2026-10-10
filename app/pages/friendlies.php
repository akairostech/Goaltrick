<?php
$team = require_team();
$pageTitle = 'Amistosos';
$navActive = 'club';
$teamId = (int)$team['id'];

if (is_post()) {
    csrf_check();
    $a = (string)post('action');
    $err = null;
    if ($a === 'challenge') {
        $to = (int)post('to');
        if (!$to && trim((string)post('name')) !== '') {
            $to = (int)DB::value('SELECT id FROM teams WHERE name = ?', [trim((string)post('name'))]);
        }
        if ($to && (int)DB::value('SELECT is_national FROM teams WHERE id = ?', [$to])) {
            $to = 0;
        }
        $err = $to ? FriendlyService::challenge($team, $to, post('venue') !== 'away') : 'Equipo no encontrado.';
        flash($err ? 'error' : 'success', $err ?? 'Invitación enviada. Los equipos de la máquina aceptan al instante.');
    } elseif ($a === 'accept' || $a === 'reject') {
        $err = FriendlyService::respond($team, (int)post('id'), $a === 'accept');
        flash($err ? 'error' : 'success', $err ?? ($a === 'accept' ? 'Amistoso aceptado.' : 'Invitación rechazada.'));
    } elseif ($a === 'cancel') {
        FriendlyService::cancel($team, (int)post('id'));
        flash('info', 'Invitación cancelada.');
    }
    redirect(url('friendlies'));
}

$slot = FriendlyService::nextSlot();
$scheduled = LeagueService::teamMatches($teamId, "type = 'friendly' AND status = 'scheduled'");
$incoming = DB::all("SELECT r.*, t.name FROM friendly_requests r JOIN teams t ON t.id = r.from_team_id WHERE r.to_team_id = ? AND r.status = 'pending'", [$teamId]);
$outgoing = DB::all("SELECT r.*, t.name FROM friendly_requests r JOIN teams t ON t.id = r.to_team_id WHERE r.from_team_id = ? AND r.status = 'pending'", [$teamId]);
$to = (int)get('to');
$toTeam = $to ? DB::row('SELECT id, name, is_bot FROM teams WHERE id = ?', [$to]) : null;
$suggest = DB::all('SELECT t.id, t.name, t.is_bot, lu.name AS unit FROM teams t JOIN league_units lu ON lu.id = t.league_unit_id WHERE t.id <> ? ORDER BY (t.league_unit_id = ?) DESC, RAND() LIMIT 12', [$teamId, $team['league_unit_id']]);
?>
<div class="page-title"><h1>Partidos amistosos</h1>
    <?php if ($slot): ?><span class="muted">Próxima fecha de amistosos: <strong><?= e(fdate_long($slot['run_at'])) ?></strong></span><?php endif; ?>
</div>
<?php if (CupService::isAlive((int)$team['id'])): ?>
    <div class="alert alert-info">🥇 Tu equipo sigue vivo en la <a href="<?= e(url('cup')) ?>">Copa</a>: sus partidos se juegan en la fecha de los amistosos, así que no podrás jugar amistosos hasta que quedes eliminado.</div>
<?php endif; ?>
<p class="muted">Los amistosos sirven para que entrenen los jugadores que no juegan en liga y para ganar algo de experiencia. Los ingresos de taquilla son la mitad que en liga y no afectan a la afición.</p>

<?php if ($scheduled): ?>
<div class="box"><div class="box-head">Amistoso programado</div><div class="box-body">
    <?php foreach ($scheduled as $m): ?><p><strong><?= e($m['hn']) ?></strong> vs <strong><?= e($m['an']) ?></strong> · <?= e(fdate_long($m['scheduled_at'])) ?></p><?php endforeach; ?>
</div></div>
<?php endif; ?>

<?php if ($incoming): ?>
<div class="box"><div class="box-head">Invitaciones recibidas</div>
    <table class="table">
        <?php foreach ($incoming as $r): ?>
            <tr><td><?= team_link($r['from_team_id'], $r['name']) ?> te invita (jugarías como visitante)</td><td class="small muted"><?= e(fdate($r['created_at'])) ?></td>
                <td class="right nowrap">
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-small" name="action" value="accept">Aceptar</button><button class="btn btn-small btn-grey" name="action" value="reject">Rechazar</button></form>
                </td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($outgoing): ?>
<div class="box"><div class="box-head">Invitaciones enviadas</div>
    <table class="table">
        <?php foreach ($outgoing as $r): ?>
            <tr><td>Esperando respuesta de <?= team_link($r['to_team_id'], $r['name']) ?></td>
                <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-small btn-grey" name="action" value="cancel">Cancelar</button></form></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if (!$scheduled && !$outgoing): ?>
<div class="grid-2">
    <div class="box">
        <div class="box-head">Invitar a un equipo</div>
        <div class="box-body">
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="challenge">
                <?php if ($toTeam): ?>
                    <input type="hidden" name="to" value="<?= (int)$toTeam['id'] ?>">
                    <p>Rival: <strong><?= e($toTeam['name']) ?></strong></p>
                <?php else: ?>
                    <div class="field"><label>Nombre exacto del equipo</label><input type="text" name="name" required></div>
                <?php endif; ?>
                <div class="field"><label>Campo</label>
                    <select name="venue"><option value="home">Jugar en casa</option><option value="away">Jugar fuera</option></select>
                    <div class="hint">Contra equipos de otros mánagers juegas siempre en casa si invitas tú.</div>
                </div>
                <button class="btn" type="submit">Enviar invitación</button>
            </form>
        </div>
    </div>
    <div class="box">
        <div class="box-head">Rivales sugeridos</div>
        <table class="table compact">
            <?php foreach ($suggest as $s): ?>
                <tr><td><?= team_link($s['id'], $s['name']) ?> <?= $s['is_bot'] ? '' : '<span class="tag tag-user">M</span>' ?></td><td class="small muted"><?= e($s['unit']) ?></td>
                    <td class="right"><a class="btn btn-small btn-grey" href="<?= e(url('friendlies', ['to' => $s['id']])) ?>">Elegir</a></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>
