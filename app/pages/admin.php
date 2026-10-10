<?php
$admin = require_admin();
$pageTitle = 'Administración';
$navActive = 'admin';
$tab = (string)get('tab', 'game');

if (is_post()) {
    csrf_check();
    $a = (string)post('action');
    switch ($a) {
        case 'run_due':
            $log = Scheduler::runDue();
            flash('success', $log ? implode(' | ', $log) : 'No había nada pendiente.');
            break;
        case 'force_next':
            $n = max(1, min(30, (int)post('n', 1)));
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $out[] = Scheduler::forceNext();
            }
            flash('success', implode(' | ', $out));
            break;
        case 'force_week':
            $out = [];
            for ($i = 0; $i < 6; $i++) {
                $ev = Scheduler::nextEvent();
                if (!$ev) {
                    break;
                }
                $out[] = Scheduler::forceNext();
                if ($ev['type'] === 'league_round' || $ev['type'] === 'season_end') {
                    break;
                }
            }
            flash('success', $out ? implode(' | ', $out) : 'Nada que ejecutar.');
            break;
        case 'ban':
        case 'unban':
            $uid = (int)post('id');
            if ($uid !== (int)$admin['id']) {
                DB::exec('UPDATE users SET is_banned = ? WHERE id = ?', [$a === 'ban' ? 1 : 0, $uid]);
                if ($a === 'ban') {
                    DB::exec('DELETE FROM remember_tokens WHERE user_id = ?', [$uid]);
                }
                flash('success', $a === 'ban' ? 'Usuario suspendido.' : 'Usuario reactivado.');
            }
            break;
        case 'toggle_admin':
            $uid = (int)post('id');
            if ($uid !== (int)$admin['id']) {
                DB::exec('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?', [$uid]);
                flash('success', 'Permisos actualizados.');
            }
            break;
        case 'release':
            $tid = (int)DB::value('SELECT id FROM teams WHERE user_id = ?', [(int)post('id')]);
            if ($tid) {
                TeamService::release($tid);
                flash('success', 'El equipo ha pasado a control de la máquina.');
            }
            break;
        case 'reset_link':
            $u = DB::row('SELECT email FROM users WHERE id = ?', [(int)post('id')]);
            if ($u) {
                $token = Auth::createResetToken($u['email']);
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('reset', ['token' => $token]);
                flash('info', 'Enlace de recuperación (válido 1 hora): ' . $link);
            }
            break;
        case 'announce':
            $title = trim((string)post('title'));
            $body = trim((string)post('body'));
            if ($title && $body) {
                DB::insert('announcements', ['user_id' => $admin['id'], 'title' => mb_substr($title, 0, 150), 'body' => $body, 'created_at' => now()]);
                flash('success', 'Anuncio publicado.');
            }
            break;
        case 'del_announce':
            DB::exec('DELETE FROM announcements WHERE id = ?', [(int)post('id')]);
            flash('success', 'Anuncio borrado.');
            break;
        case 'give_cash':
            $tid = (int)post('team_id');
            $amount = (int)post('amount');
            if ($tid && $amount) {
                Economy::transaction($tid, $amount, 'other', 'Ajuste del administrador');
                flash('success', 'Caja ajustada.');
            }
            break;
    }
    redirect(url('admin', ['tab' => $tab]));
}
$stats = DB::row("SELECT (SELECT COUNT(*) FROM users) users, (SELECT COUNT(*) FROM users WHERE last_activity_at > ?) active,
    (SELECT COUNT(*) FROM teams WHERE user_id IS NULL) free_teams, (SELECT COUNT(*) FROM matches WHERE status = 'played') played,
    (SELECT COUNT(*) FROM transfer_listings WHERE status = 'active') listings, (SELECT COUNT(*) FROM players WHERE retired = 0) players", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
?>
<div class="page-title"><h1>Administración</h1><span class="muted">Temporada <?= GameState::season() ?> · Semana <?= GameState::week() ?></span></div>
<div class="tabs">
    <a class="<?= $tab === 'game' ? 'active' : '' ?>" href="<?= e(url('admin')) ?>">Juego</a>
    <a class="<?= $tab === 'users' ? 'active' : '' ?>" href="<?= e(url('admin', ['tab' => 'users'])) ?>">Usuarios</a>
    <a class="<?= $tab === 'news' ? 'active' : '' ?>" href="<?= e(url('admin', ['tab' => 'news'])) ?>">Anuncios</a>
    <a href="<?= e(url('admin_team')) ?>">Editar equipos</a>
    <a class="<?= $tab === 'config' ? 'active' : '' ?>" href="<?= e(url('admin', ['tab' => 'config'])) ?>">Configuración</a>
    <a href="<?= e(url('admin_reset')) ?>" style="color:#b0281b">Reiniciar partida</a>
</div>

<?php if ($tab === 'game'):
    $events = DB::all("SELECT * FROM scheduled_events WHERE status = 'pending' ORDER BY run_at LIMIT 12");
    $done = DB::all("SELECT * FROM scheduled_events WHERE status = 'done' ORDER BY executed_at DESC, id DESC LIMIT 8");
?>
<div class="stat-tiles">
    <div class="tile"><div class="tv"><?= num($stats['users']) ?></div><div class="tl">Usuarios (<?= num($stats['active']) ?> activos 7d)</div></div>
    <div class="tile"><div class="tv"><?= num($stats['free_teams']) ?></div><div class="tl">Equipos libres</div></div>
    <div class="tile"><div class="tv"><?= num($stats['played']) ?></div><div class="tl">Partidos jugados</div></div>
    <div class="tile"><div class="tv"><?= num($stats['listings']) ?></div><div class="tl">Jugadores en venta</div></div>
</div>
<div class="grid-2">
    <div class="box"><div class="box-head">Planificador</div><div class="box-body">
        <p>Las tareas se ejecutan solas a su hora <?= Config::bool('game', 'auto_update', true) ? '(modo automático por visitas activado)' : '(usa <code>php cron/run.php</code> en el Programador de tareas)' ?>.</p>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="run_due"><button class="btn" type="submit">Ejecutar lo pendiente ahora</button></form>
        <hr>
        <p><strong>Modo pruebas:</strong> adelanta el calendario sin esperar.</p>
        <form method="post" class="inline-form" data-confirm-submit="¿Ejecutar los siguientes eventos antes de su hora?"><?= csrf_field() ?><input type="hidden" name="action" value="force_next">
            <input type="number" name="n" value="1" min="1" max="30" style="width:70px"><button class="btn btn-accent" type="submit">Forzar siguientes eventos</button></form>
        <br><br>
        <form method="post" class="inline-form" data-confirm-submit="¿Simular el resto de la semana hasta la jornada de liga?"><?= csrf_field() ?><input type="hidden" name="action" value="force_week"><button class="btn btn-accent" type="submit">Simular hasta la próxima jornada</button></form>
        <p class="small muted" style="margin-top:8px">Una actualización de entrenamiento con muchos equipos puede tardar algunos segundos.</p>
    </div></div>
    <div class="box"><div class="box-head">Próximos eventos</div>
        <table class="table compact">
            <?php foreach ($events as $ev): ?><tr><td><?= e(Scheduler::eventName($ev['type'])) ?> <?= $ev['round'] ? (int)$ev['round'] : '' ?></td><td class="small">T<?= (int)$ev['season'] ?> S<?= (int)$ev['week'] ?></td><td class="num small"><?= e(fdate($ev['run_at'])) ?></td></tr><?php endforeach; ?>
        </table>
        <div class="box-head">Últimos ejecutados</div>
        <table class="table compact">
            <?php foreach ($done as $ev): ?><tr><td><?= e(Scheduler::eventName($ev['type'])) ?> <?= $ev['round'] ? (int)$ev['round'] : '' ?></td><td class="small">T<?= (int)$ev['season'] ?> S<?= (int)$ev['week'] ?></td><td class="num small"><?= e(fdate($ev['executed_at'])) ?></td></tr><?php endforeach; ?>
        </table>
    </div>
</div>
<br>
<div class="box"><div class="box-head">Ajustar caja de un equipo</div><div class="box-body">
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="give_cash">
        <label style="margin:0">ID del equipo</label><input type="number" name="team_id" style="width:100px" required>
        <label style="margin:0">Importe (+/-)</label><input type="number" name="amount" step="1000" style="width:140px" required>
        <button class="btn btn-grey" type="submit">Aplicar</button></form>
</div></div>

<?php elseif ($tab === 'users'):
    $q = trim((string)get('q'));
    $params = [];
    $where = '';
    if ($q !== '') {
        $where = 'WHERE u.username LIKE ? OR u.email LIKE ?';
        $params = ['%' . $q . '%', '%' . $q . '%'];
    }
    $users = DB::all("SELECT u.*, t.id AS tid, t.name AS tname FROM users u LEFT JOIN teams t ON t.user_id = u.id $where ORDER BY u.id DESC LIMIT 200", $params);
?>
<div class="box"><div class="box-body">
    <form method="get" class="inline-form"><input type="hidden" name="page" value="admin"><input type="hidden" name="tab" value="users"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Usuario o correo"><button class="btn" type="submit">Buscar</button></form>
</div></div>
<div class="box"><div class="table-wrap"><table class="table compact">
    <thead><tr><th>ID</th><th>Usuario</th><th>Correo</th><th>Equipo</th><th>Última actividad</th><th>Estado</th><th>Acciones</th></tr></thead>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= e($u['username']) ?> <?= (int)$u['is_admin'] ? '<span class="tag tag-user">admin</span>' : '' ?></td>
            <td class="small"><?= e($u['email']) ?></td>
            <td><?= $u['tid'] ? team_link($u['tid'], $u['tname']) . ' <span class="muted small">#' . (int)$u['tid'] . '</span>' : '<span class="muted">—</span>' ?></td>
            <td class="small"><?= e(fdate($u['last_activity_at'])) ?></td>
            <td><?= (int)$u['is_banned'] ? '<span class="tag tag-red">Suspendido</span>' : '<span class="tag tag-user">Activo</span>' ?></td>
            <td class="nowrap">
                <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-small btn-grey" name="action" value="<?= (int)$u['is_banned'] ? 'unban' : 'ban' ?>"><?= (int)$u['is_banned'] ? 'Reactivar' : 'Suspender' ?></button>
                    <button class="btn btn-small btn-grey" name="action" value="toggle_admin"><?= (int)$u['is_admin'] ? 'Quitar admin' : 'Hacer admin' ?></button>
                    <button class="btn btn-small btn-grey" name="action" value="reset_link">Enlace contraseña</button>
                    <?php if ($u['tid']): ?><a class="btn btn-small" href="<?= e(url('admin_team', ['id' => $u['tid']])) ?>">Editar equipo</a><?php endif; ?>
                    <?php if ($u['tid']): ?><button class="btn btn-small btn-danger" name="action" value="release" data-confirm="¿Liberar el equipo de este usuario?">Liberar equipo</button><?php endif; ?>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table></div></div>

<?php elseif ($tab === 'news'):
    $news = DB::all('SELECT * FROM announcements ORDER BY id DESC');
?>
<div class="box"><div class="box-head">Nuevo anuncio (aparece en la portada de todos los mánagers)</div><div class="box-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="announce">
        <div class="field"><label>Título</label><input type="text" name="title" maxlength="150" required></div>
        <div class="field"><label>Texto</label><textarea name="body" required></textarea></div>
        <button class="btn" type="submit">Publicar</button></form>
</div></div>
<div class="box"><table class="table">
    <?php foreach ($news as $n): ?><tr><td><strong><?= e($n['title']) ?></strong><div class="small muted"><?= e(mb_strimwidth($n['body'], 0, 140, '…')) ?></div></td><td class="small"><?= e(fdate($n['created_at'])) ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="del_announce"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-small btn-danger" type="submit">Borrar</button></form></td></tr><?php endforeach; ?>
    <?php if (!$news): ?><tr><td class="muted">No hay anuncios.</td></tr><?php endif; ?>
</table></div>

<?php else: ?>
<div class="box"><div class="box-head">Configuración actual (config.ini)</div><div class="box-body">
    <p class="muted">Para cambiarla edita el archivo <code>config.ini</code> en la carpeta del juego. Los cambios se aplican al instante.</p>
    <?php foreach (['app', 'game', 'worldcup', 'economy', 'mail'] as $sec): ?>
        <h3>[<?= e($sec) ?>]</h3>
        <table class="table compact">
            <?php foreach (Config::section($sec) as $k => $v): ?><tr><td style="width:40%"><?= e($k) ?></td><td><code><?= e(is_bool($v) ? ($v ? 'true' : 'false') : (string)$v) ?></code></td></tr><?php endforeach; ?>
        </table>
    <?php endforeach; ?>
    <h3>[database]</h3>
    <table class="table compact">
        <tr><td style="width:40%">host</td><td><code><?= e((string)Config::get('database', 'host')) ?>:<?= (int)Config::get('database', 'port') ?></code></td></tr>
        <tr><td>name</td><td><code><?= e((string)Config::get('database', 'name')) ?></code></td></tr>
        <tr><td>user</td><td><code><?= e((string)Config::get('database', 'user')) ?></code></td></tr>
    </table>
</div></div>
<?php endif; ?>
