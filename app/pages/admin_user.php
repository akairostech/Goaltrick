<?php
/**
 * Administración: actividad de un usuario y su equipo (para reunir pruebas) y sanciones
 * (aviso, suspensión temporal, expulsión, readmisión).
 */
$admin = require_admin();
$pageTitle = t('Investigar usuario');
$navActive = 'admin';
$id = (int)get('id');
if (!$id && get('team')) {
    $id = (int)DB::value('SELECT user_id FROM teams WHERE id = ?', [(int)get('team')]);
}
$u = $id ? DB::row('SELECT * FROM users WHERE id = ?', [$id]) : null;
if (!$u) {
    flash('error', t('Usuario no encontrado.'));
    redirect(url('admin', ['tab' => 'users']));
}
$u = Moderation::refresh($u);
$tab = (string)get('tab', 'summary');
$ticketId = (int)get('ticket') ?: null;

if (is_post()) {
    csrf_check();
    $action = (string)post('action');
    $err = Moderation::sanction($id, (int)$admin['id'], $action, (string)post('reason'), (int)post('days'), (bool)post('release'), $ticketId);
    if ($err) {
        flash('error', $err);
    } else {
        flash('success', match ($action) {
            'warn' => t('Aviso enviado al usuario.'),
            'suspend' => t('Usuario suspendido.'),
            'expel' => t('Usuario expulsado del juego.'),
            default => t('Usuario readmitido.'),
        });
        if ($ticketId) {
            Moderation::reply($ticketId, $admin, t('Sanción aplicada: {action}.', ['action' => t(Moderation::ACTIONS[$action][0])]), true, true);
        }
    }
    redirect(url('admin_user', array_filter(['id' => $id, 'ticket' => $ticketId])));
}

$team = DB::row('SELECT t.*, lu.name AS unit FROM teams t LEFT JOIN league_units lu ON lu.id = t.league_unit_id WHERE t.user_id = ?', [$id]);
$teamId = $team ? (int)$team['id'] : 0;
$tabs = [
    'summary'   => '📋 ' . t('Resumen y sanciones'),
    'transfers' => '🔁 ' . t('Transferencias'),
    'messages'  => '✉️ ' . t('Mensajes'),
    'finances'  => '💰 ' . t('Finanzas'),
    'matches'   => '⚽ ' . t('Amistosos y partidos'),
    'events'    => '📰 ' . t('Noticias del club'),
    'tickets'   => '🆘 ' . t('Tickets'),
];
$tab = isset($tabs[$tab]) ? $tab : 'summary';
$link = fn(string $tb) => url('admin_user', array_filter(['id' => $id, 'tab' => $tb, 'ticket' => $ticketId]));
$userLink = fn($uid, $name) => $uid ? '<a href="' . e(url('admin_user', ['id' => $uid])) . '">' . e($name) . '</a>' : '<span class="muted">' . e($name ?? '–') . '</span>';
?>
<div class="page-title"><h1>🔎 <?= e($u['username']) ?> <?php if ((int)$u['is_banned']): ?><span class="tag tag-red"><?= e($u['banned_until'] ? t('Suspendido') : t('Expulsado')) ?></span><?php endif; ?></h1>
    <span><?php if ($ticketId): ?><a class="btn btn-small btn-grey" href="<?= e(url('admin_tickets', ['id' => $ticketId])) ?>">« <?= e(t('Volver al ticket #{id}', ['id' => $ticketId])) ?></a><?php endif; ?>
    <a href="<?= e(url('admin', ['tab' => 'users'])) ?>">« <?= e(t('Usuarios')) ?></a></span>
</div>
<div class="tabs">
    <?php foreach ($tabs as $k => $label): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="<?= e($link($k)) ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>

<?php if ($tab === 'summary'):
    $sanctions = DB::all('SELECT s.*, a.username AS admin FROM user_sanctions s LEFT JOIN users a ON a.id = s.admin_id WHERE s.user_id = ? ORDER BY s.id DESC', [$id]);
    $logins = DB::all('SELECT ip, COUNT(*) n, MIN(created_at) first_seen, MAX(created_at) last_seen, MAX(user_agent) ua FROM user_logins WHERE user_id = ? GROUP BY ip ORDER BY last_seen DESC LIMIT 20', [$id]);
    $shared = Moderation::sharedIps($id);
    $reports = (int)DB::value('SELECT COUNT(*) FROM support_tickets WHERE reported_user_id = ?', [$id]);
    $userTeams = $teamId ? DB::all("SELECT x.other_user, x.n, x.total, ou.username FROM (
            SELECT CASE WHEN l.seller_team_id = ? THEN bt.user_id ELSE st.user_id END AS other_user, COUNT(*) n, SUM(l.highest_bid) total
            FROM transfer_listings l JOIN teams st ON st.id = l.seller_team_id JOIN teams bt ON bt.id = l.highest_bidder_id
            WHERE l.status = 'sold' AND (l.seller_team_id = ? OR l.highest_bidder_id = ?) GROUP BY other_user) x
        JOIN users ou ON ou.id = x.other_user ORDER BY x.n DESC", [$teamId, $teamId, $teamId]) : [];
?>
<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Cuenta')) ?></div>
        <table class="kv">
            <tr><th>ID</th><td><?= (int)$u['id'] ?></td></tr>
            <tr><th><?= e(t('Correo')) ?></th><td><?= e($u['email']) ?></td></tr>
            <tr><th><?= e(t('Registrado')) ?></th><td><?= e(fdate($u['created_at'])) ?></td></tr>
            <tr><th><?= e(t('Último acceso')) ?></th><td><?= e(fdate($u['last_login_at'])) ?> · <?= e(t('actividad: {date}', ['date' => fdate($u['last_activity_at'])])) ?></td></tr>
            <tr><th><?= e(t('Equipo')) ?></th><td><?php if ($team): ?><?= crest_img($team, 18) ?> <?= team_link($teamId, $team['name']) ?> · <?= e(t('División {unit}', ['unit' => $team['unit'] ?? '-'])) ?> · <a class="btn btn-small btn-grey" href="<?= e(url('admin_team', ['id' => $teamId])) ?>"><?= e(t('Editar equipo')) ?></a><?php else: ?>–<?php endif; ?></td></tr>
            <tr><th><?= e(t('Estado')) ?></th><td><?php if ((int)$u['is_banned']): ?><span class="neg"><?= e(Moderation::bannedMessage($u)) ?></span><?php else: ?><span class="pos"><?= e(t('Activo')) ?></span><?php endif; ?></td></tr>
            <tr><th><?= e(t('Denuncias recibidas')) ?></th><td><?= $reports ? '<a href="' . e($link('tickets')) . '">' . $reports . '</a>' : '0' ?></td></tr>
        </table>
    </div>
    <div class="box"><div class="box-head">⚖️ <?= e(t('Sancionar')) ?></div>
        <div class="box-body">
            <?php if ((int)$u['id'] === (int)$admin['id']): ?>
                <p class="muted"><?= e(t('No puedes sancionarte a ti mismo.')) ?></p>
            <?php elseif ((int)$u['is_banned']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unban">
                    <div class="field"><label><?= e(t('Motivo de la readmisión (opcional)')) ?></label><input type="text" name="reason" maxlength="500"></div>
                    <button class="btn" type="submit">✅ <?= e(t('Levantar la sanción')) ?></button>
                </form>
            <?php else: ?>
                <form method="post" class="sanction-form"><?= csrf_field() ?>
                    <div class="field"><label><?= e(t('Motivo (lo verá el usuario)')) ?></label><textarea name="reason" rows="3" maxlength="500" required placeholder="<?= e(t('Ej.: aprovechamiento de un error del mercado para obtener dinero; insultos por mensaje privado...')) ?>"></textarea></div>
                    <div class="form-row">
                        <div class="field"><label><?= e(t('Duración de la suspensión')) ?></label>
                            <select name="days"><?php foreach ([1, 3, 7, 14, 30, 90, 365] as $d): ?><option value="<?= $d ?>"<?= $d === 7 ? ' selected' : '' ?>><?= e(t('{n} días', ['n' => $d])) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <label class="checkbox"><input type="checkbox" name="release" value="1"> <?= e(t('Quitarle el equipo (pasa a control de la máquina)')) ?></label>
                    <div class="sanction-buttons">
                        <button class="btn btn-grey" type="submit" name="action" value="warn">⚠️ <?= e(t('Enviar aviso')) ?></button>
                        <button class="btn" type="submit" name="action" value="suspend" data-confirm="<?= e(t('¿Suspender a {name}?', ['name' => $u['username']])) ?>">⏸ <?= e(t('Suspender')) ?></button>
                        <button class="btn btn-danger" type="submit" name="action" value="expel" data-confirm="<?= e(t('¿Expulsar a {name} del juego de forma permanente?', ['name' => $u['username']])) ?>">⛔ <?= e(t('Expulsar del juego')) ?></button>
                    </div>
                    <p class="small muted"><?= e(t('Al suspender o expulsar se cierran sus sesiones y se anulan sus ventas y pujas activas. El usuario recibe un mensaje con el motivo y lo verá también al intentar entrar.')) ?></p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="box"><div class="box-head"><?= e(t('Historial de sanciones')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Acción')) ?></th><th><?= e(t('Motivo')) ?></th><th><?= e(t('Hasta')) ?></th><th><?= e(t('Administrador')) ?></th></tr></thead>
        <?php foreach ($sanctions as $s): ?>
            <tr><td class="nowrap small"><?= e(fdate($s['created_at'])) ?></td><td class="nowrap"><?= Moderation::ACTIONS[$s['action']][1] ?? '' ?> <?= e(t(Moderation::ACTIONS[$s['action']][0] ?? $s['action'])) ?></td>
                <td><?= e(tdb($s['reason'])) ?><?= $s['ticket_id'] ? ' <a class="small" href="' . e(url('admin_tickets', ['id' => $s['ticket_id']])) . '">#' . (int)$s['ticket_id'] . '</a>' : '' ?></td>
                <td class="small nowrap"><?= $s['until_at'] ? e(fdate($s['until_at'])) : '–' ?></td><td class="small"><?= e($s['admin'] ?? t('Sistema')) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$sanctions): ?><tr><td colspan="5" class="muted"><?= e(t('Sin sanciones.')) ?></td></tr><?php endif; ?>
    </table>
</div>

<div class="grid-2">
    <div class="box"><div class="box-head">🌐 <?= e(t('Accesos (IP)')) ?></div>
        <table class="table compact">
            <thead><tr><th>IP</th><th class="num"><?= e(t('Veces')) ?></th><th><?= e(t('Último')) ?></th></tr></thead>
            <?php foreach ($logins as $l): ?><tr title="<?= e($l['ua'] ?? '') ?>"><td class="nowrap"><?= e($l['ip']) ?></td><td class="num"><?= (int)$l['n'] ?></td><td class="small nowrap"><?= e(fdate($l['last_seen'])) ?></td></tr><?php endforeach; ?>
            <?php if (!$logins): ?><tr><td colspan="3" class="muted"><?= e(t('Sin accesos registrados todavía.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div class="box"><div class="box-head">👥 <?= e(t('Otras cuentas desde las mismas IP')) ?></div>
        <table class="table compact">
            <?php foreach ($shared as $s): ?><tr><td><?= $userLink($s['id'], $s['username']) ?><?= (int)$s['is_banned'] ? ' <span class="tag tag-red">' . e(t('Sancionado')) . '</span>' : '' ?></td><td class="small"><?= e($s['list']) ?></td><td class="small nowrap"><?= e(fdate($s['last_seen'])) ?></td></tr><?php endforeach; ?>
            <?php if (!$shared): ?><tr><td class="muted"><?= e(t('Ninguna. (Varias cuentas desde la misma IP pueden indicar multicuentas, aunque también pueden ser familiares o una red compartida.)')) ?></td></tr><?php endif; ?>
        </table>
    </div>
</div>

<div class="box"><div class="box-head">🤝 <?= e(t('Traspasos con otros mánagers')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Mánager')) ?></th><th class="num"><?= e(t('Traspasos')) ?></th><th class="num"><?= e(t('Importe total')) ?></th></tr></thead>
        <?php foreach ($userTeams as $x): ?><tr<?= (int)$x['n'] >= 3 ? ' class="highlight"' : '' ?>><td><?= $userLink($x['other_user'], $x['username']) ?></td><td class="num"><?= (int)$x['n'] ?></td><td class="num"><?= e(money($x['total'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$userTeams): ?><tr><td colspan="3" class="muted"><?= e(t('No ha hecho traspasos con otros mánagers.')) ?></td></tr><?php endif; ?>
    </table>
    <div class="box-body small muted"><?= e(t('Se resaltan los mánagers con 3 o más traspasos: muchas operaciones con la misma persona pueden indicar trasvase de dinero o de jugadores entre cuentas.')) ?></div>
</div>

<?php elseif ($tab === 'transfers'):
    $rows = $teamId ? DB::all("SELECT l.*, p.first_name, p.last_name, p.id AS pid, s.id AS sid, s.name AS seller, su.id AS suid, su.username AS seller_user,
            b.id AS bid, b.name AS buyer, bu.id AS buid, bu.username AS buyer_user
        FROM transfer_listings l JOIN players p ON p.id = l.player_id JOIN teams s ON s.id = l.seller_team_id LEFT JOIN users su ON su.id = s.user_id
        LEFT JOIN teams b ON b.id = l.highest_bidder_id LEFT JOIN users bu ON bu.id = b.user_id
        WHERE l.seller_team_id = ? OR l.highest_bidder_id = ? OR EXISTS (SELECT 1 FROM transfer_bids x WHERE x.listing_id = l.id AND x.team_id = ?)
        ORDER BY l.created_at DESC LIMIT 200", [$teamId, $teamId, $teamId]) : [];
    $bids = $teamId ? DB::all('SELECT x.*, p.first_name, p.last_name, p.id AS pid, l.status, l.highest_bid FROM transfer_bids x JOIN transfer_listings l ON l.id = x.listing_id JOIN players p ON p.id = l.player_id WHERE x.team_id = ? ORDER BY x.id DESC LIMIT 100', [$teamId]) : [];
?>
<div class="box"><div class="box-head"><?= e(t('Ventas, compras y subastas en las que ha participado')) ?></div>
    <div class="table-wrap"><table class="table compact">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Jugador')) ?></th><th><?= e(t('Vendedor')) ?></th><th><?= e(t('Comprador')) ?></th><th class="num"><?= e(t('Precio mínimo')) ?></th><th class="num"><?= e(t('Precio final')) ?></th><th class="num">TSI</th><th><?= e(t('Estado')) ?></th></tr></thead>
        <?php foreach ($rows as $r): $ratio = $r['tsi_at_sale'] && $r['highest_bid'] ? (int)$r['highest_bid'] / max(1, (int)$r['tsi_at_sale']) : null; ?>
            <tr>
                <td class="small nowrap"><?= e(fdate($r['closed_at'] ?? $r['created_at'])) ?></td>
                <td><a href="<?= e(url('player', ['id' => $r['pid']])) ?>"><?= e(player_name($r)) ?></a></td>
                <td><?= team_link($r['sid'], $r['seller']) ?><?= $r['suid'] ? ' <span class="small">(' . $userLink($r['suid'], $r['seller_user']) . ')</span>' : '' ?></td>
                <td><?= $r['bid'] ? team_link($r['bid'], $r['buyer']) . ($r['buid'] ? ' <span class="small">(' . $userLink($r['buid'], $r['buyer_user']) . ')</span>' : '') : '–' ?></td>
                <td class="num"><?= e(money($r['asking_price'])) ?></td>
                <td class="num"><?= $r['highest_bid'] ? e(money($r['highest_bid'])) : '–' ?></td>
                <td class="num<?= $ratio !== null && ($ratio < 5 || $ratio > 400) ? ' neg' : '' ?>" title="<?= $ratio !== null ? e(t('Precio / TSI: {r}', ['r' => num($ratio, 1)])) : '' ?>"><?= $r['tsi_at_sale'] ? num($r['tsi_at_sale']) : '–' ?></td>
                <td class="small"><?= e(t(['active' => 'En venta', 'sold' => 'Vendido', 'unsold' => 'Sin vender', 'cancelled' => 'Cancelado'][$r['status']] ?? $r['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted"><?= e(t('Sin transferencias.')) ?></td></tr><?php endif; ?>
    </table></div>
    <div class="box-body small muted"><?= e(t('En rojo, los traspasos con un precio muy alejado del TSI del jugador (posible trasvase de dinero).')) ?></div>
</div>
<div class="box"><div class="box-head"><?= e(t('Últimas pujas')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Jugador')) ?></th><th class="num"><?= e(t('Puja')) ?></th><th class="num"><?= e(t('Puja más alta')) ?></th><th><?= e(t('Estado')) ?></th></tr></thead>
        <?php foreach ($bids as $b): ?><tr><td class="small nowrap"><?= e(fdate($b['created_at'])) ?></td><td><a href="<?= e(url('player', ['id' => $b['pid']])) ?>"><?= e(player_name($b)) ?></a></td><td class="num"><?= e(money($b['amount'])) ?></td><td class="num"><?= e(money($b['highest_bid'])) ?></td><td class="small"><?= e($b['status']) ?></td></tr><?php endforeach; ?>
        <?php if (!$bids): ?><tr><td colspan="5" class="muted"><?= e(t('Sin pujas.')) ?></td></tr><?php endif; ?>
    </table>
</div>

<?php elseif ($tab === 'messages'):
    $dir = (string)get('dir', 'all');
    $where = match ($dir) { 'sent' => 'm.from_user_id = ?', 'received' => 'm.to_user_id = ? AND m.from_user_id IS NOT NULL', default => '(m.from_user_id = ? OR (m.to_user_id = ? AND m.from_user_id IS NOT NULL))' };
    $params = $dir === 'all' ? [$id, $id] : [$id];
    $total = (int)DB::value("SELECT COUNT(*) FROM messages m WHERE $where", $params);
    $pg = max(1, (int)get('p', 1));
    $msgs = DB::all("SELECT m.*, f.username AS from_name, r.username AS to_name FROM messages m LEFT JOIN users f ON f.id = m.from_user_id LEFT JOIN users r ON r.id = m.to_user_id
        WHERE $where ORDER BY m.id DESC LIMIT 30 OFFSET " . (($pg - 1) * 30), $params);
?>
<div class="box"><div class="box-head"><?= e(t('Mensajes privados ({n})', ['n' => $total])) ?>
    <span><?php foreach (['all' => t('Todos'), 'sent' => t('Enviados'), 'received' => t('Recibidos')] as $k => $label): ?><a class="btn btn-small <?= $dir === $k ? '' : 'btn-grey' ?>" href="<?= e(url('admin_user', ['id' => $id, 'tab' => 'messages', 'dir' => $k])) ?>"><?= e($label) ?></a> <?php endforeach; ?></span></div>
    <?php foreach ($msgs as $m): $out = (int)$m['from_user_id'] === $id; ?>
        <div class="evidence-msg <?= $out ? 'em-out' : 'em-in' ?>">
            <div class="small"><strong><?= $out ? '📤 ' . e(t('Enviado a')) : '📥 ' . e(t('Recibido de')) ?></strong> <?= $out ? $userLink($m['to_user_id'], $m['to_name']) : $userLink($m['from_user_id'], $m['from_name']) ?>
                · <span class="muted"><?= e(fdate($m['created_at'])) ?></span>
                <?= ((int)$m['deleted_by_sender'] || (int)$m['deleted_by_recipient']) ? ' · <span class="tag">' . e(t('borrado por un usuario')) . '</span>' : '' ?></div>
            <div><strong><?= e(tdb($m['subject'])) ?></strong></div>
            <div class="small"><?= nl2br(e(tdb($m['body']))) ?></div>
        </div>
    <?php endforeach; ?>
    <?php if (!$msgs): ?><div class="box-body muted"><?= e(t('Sin mensajes.')) ?></div><?php endif; ?>
    <?= paginate($total, 30, $pg, 'admin_user', ['id' => $id, 'tab' => 'messages', 'dir' => $dir]) ?>
</div>

<?php elseif ($tab === 'finances'):
    $rows = $teamId ? DB::all('SELECT * FROM finances WHERE team_id = ? ORDER BY id DESC LIMIT 150', [$teamId]) : [];
?>
<div class="box"><div class="box-head"><?= e(t('Últimos movimientos de dinero')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Temp.')) ?></th><th><?= e(t('Concepto')) ?></th><th class="num"><?= e(t('Importe')) ?></th></tr></thead>
        <?php foreach ($rows as $f): ?><tr><td class="small nowrap"><?= e(fdate($f['created_at'])) ?></td><td class="small"><?= (int)$f['season'] ?>/<?= (int)$f['week'] ?></td><td><?= e(tdb($f['description'])) ?> <span class="small muted"><?= e($f['category']) ?></span></td><td class="num <?= (int)$f['amount'] < 0 ? 'neg' : 'pos' ?>"><?= e(money($f['amount'], true)) ?></td></tr><?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="muted"><?= e(t('Sin movimientos.')) ?></td></tr><?php endif; ?>
    </table>
</div>

<?php elseif ($tab === 'matches'):
    $fr = $teamId ? DB::all("SELECT f.*, a.name AS fn, a.user_id AS fu, b.name AS tn, b.user_id AS tu FROM friendly_requests f JOIN teams a ON a.id = f.from_team_id JOIN teams b ON b.id = f.to_team_id
        WHERE f.from_team_id = ? OR f.to_team_id = ? ORDER BY f.id DESC LIMIT 100", [$teamId, $teamId]) : [];
    $played = $teamId ? LeagueService::teamMatches($teamId, "status = 'played'", [], 'x.played_at DESC', 40) : [];
?>
<div class="grid-2">
    <div class="box"><div class="box-head"><?= e(t('Invitaciones a amistosos')) ?></div>
        <table class="table compact">
            <?php foreach ($fr as $f): ?><tr><td class="small nowrap"><?= e(fdate($f['created_at'])) ?></td><td><?= team_link($f['from_team_id'], $f['fn']) ?> → <?= team_link($f['to_team_id'], $f['tn']) ?></td><td class="small"><?= e($f['status']) ?></td></tr><?php endforeach; ?>
            <?php if (!$fr): ?><tr><td class="muted"><?= e(t('Sin amistosos.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Últimos partidos')) ?></div>
        <table class="table compact">
            <?php foreach ($played as $m): ?><tr><td class="small nowrap"><?= e(fdate($m['played_at'], false)) ?></td><td class="small"><?= e(match_type_label($m)) ?></td><td><a href="<?= e(url('match', ['id' => $m['id']])) ?>"><?= e($m['hn']) ?> <?= e(score_label($m)) ?> <?= e($m['an']) ?></a></td></tr><?php endforeach; ?>
            <?php if (!$played): ?><tr><td class="muted"><?= e(t('Sin partidos.')) ?></td></tr><?php endif; ?>
        </table>
    </div>
</div>

<?php elseif ($tab === 'events'):
    $ev = $teamId ? DB::all('SELECT * FROM team_events WHERE team_id = ? ORDER BY id DESC LIMIT 150', [$teamId]) : [];
?>
<div class="box"><div class="box-head"><?= e(t('Noticias del club')) ?></div>
    <ul class="news"><?php foreach ($ev as $n): ?><li><span class="when"><?= e(fdate($n['created_at'])) ?></span><span><?= e(tdb($n['message'])) ?></span></li><?php endforeach; ?></ul>
    <?php if (!$ev): ?><div class="box-body muted"><?= e(t('Sin noticias.')) ?></div><?php endif; ?>
</div>

<?php else:
    $by = DB::all('SELECT t.*, r.username AS reported FROM support_tickets t LEFT JOIN users r ON r.id = t.reported_user_id WHERE t.user_id = ? ORDER BY t.id DESC', [$id]);
    $against = DB::all('SELECT t.*, u.username FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE t.reported_user_id = ? ORDER BY t.id DESC', [$id]);
    $row = fn(array $t, string $who) => '<tr><td><a href="' . e(url('admin_tickets', ['id' => $t['id']])) . '">#' . (int)$t['id'] . ' ' . e($t['subject']) . '</a></td><td class="small">' . $who . '</td><td class="small nowrap">' . e(fdate($t['created_at'])) . '</td><td class="small">' . e(t(Moderation::STATUSES[$t['status']][0] ?? $t['status'])) . '</td></tr>';
?>
<div class="grid-2">
    <div class="box"><div class="box-head">🚩 <?= e(t('Denuncias contra este usuario ({n})', ['n' => count($against)])) ?></div>
        <table class="table compact"><?php foreach ($against as $tk) { echo $row($tk, e(t('de {name}', ['name' => $tk['username']]))); } ?>
            <?php if (!$against): ?><tr><td class="muted"><?= e(t('Ninguna.')) ?></td></tr><?php endif; ?></table>
    </div>
    <div class="box"><div class="box-head"><?= e(t('Tickets abiertos por este usuario ({n})', ['n' => count($by)])) ?></div>
        <table class="table compact"><?php foreach ($by as $tk) { echo $row($tk, $tk['reported'] ? e(t('reporta a {name}', ['name' => $tk['reported']])) : e(t(Moderation::CATEGORIES[$tk['category']][0] ?? ''))); } ?>
            <?php if (!$by): ?><tr><td class="muted"><?= e(t('Ninguno.')) ?></td></tr><?php endif; ?></table>
    </div>
</div>
<?php endif; ?>
