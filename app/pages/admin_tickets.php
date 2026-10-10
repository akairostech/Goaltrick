<?php
/** Administración: tickets de ayuda y soporte */
$admin = require_admin();
$pageTitle = t('Tickets de ayuda');
$navActive = 'admin';
$id = (int)get('id');

if (is_post()) {
    csrf_check();
    $tk = DB::row('SELECT * FROM support_tickets WHERE id = ?', [(int)post('id')]);
    if ($tk) {
        $a = (string)post('action');
        $status = (string)post('status');
        $status = isset(Moderation::STATUSES[$status]) ? $status : null;
        if ($a === 'reply') {
            $err = Moderation::reply((int)$tk['id'], $admin, (string)post('body'), true, false, $status ?? 'answered');
            flash($err ? 'error' : 'success', $err ?? t('Respuesta enviada. El usuario recibirá un aviso por mensaje.'));
        } elseif ($a === 'note') {
            $err = Moderation::reply((int)$tk['id'], $admin, (string)post('body'), true, true);
            flash($err ? 'error' : 'success', $err ?? t('Nota interna guardada.'));
        } elseif ($a === 'status' && $status) {
            DB::update('support_tickets', ['status' => $status, 'staff_read_at' => now()], 'id = ?', [$tk['id']]);
            flash('success', t('Estado actualizado.'));
        } elseif ($a === 'assign') {
            DB::update('support_tickets', ['assigned_to' => $admin['id']], 'id = ?', [$tk['id']]);
            flash('success', t('Ticket asignado a ti.'));
        }
        redirect(url('admin_tickets', ['id' => $tk['id']]));
    }
    redirect(url('admin_tickets'));
}

$catLabel = fn(string $c) => (Moderation::CATEGORIES[$c][1] ?? '') . ' ' . t(Moderation::CATEGORIES[$c][0] ?? $c);
$statusTag = fn(string $s) => '<span class="tag ' . e(Moderation::STATUSES[$s][1] ?? '') . '">' . e(t(Moderation::STATUSES[$s][0] ?? $s)) . '</span>';
?>
<div class="page-title"><h1>🆘 <?= e(t('Tickets de ayuda')) ?></h1><a href="<?= e(url('admin')) ?>">« <?= e(t('Volver a Administración')) ?></a></div>

<?php if ($id):
    $tk = DB::row('SELECT t.*, u.username, u.email, u.is_banned, r.username AS reported, a.username AS assigned, tm.id AS team_id, tm.name AS team_name
        FROM support_tickets t JOIN users u ON u.id = t.user_id LEFT JOIN users r ON r.id = t.reported_user_id
        LEFT JOIN users a ON a.id = t.assigned_to LEFT JOIN teams tm ON tm.user_id = t.user_id WHERE t.id = ?', [$id]);
    if (!$tk) {
        echo '<div class="alert alert-error">' . e(t('Ticket no encontrado.')) . '</div>';
        return;
    }
    DB::exec('UPDATE support_tickets SET staff_read_at = ? WHERE id = ?', [now(), $id]);
    $replies = DB::all('SELECT r.*, u.username FROM support_replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ? ORDER BY r.id', [$id]);
    $others = DB::all('SELECT id, subject, status, created_at FROM support_tickets WHERE user_id = ? AND id <> ? ORDER BY id DESC LIMIT 10', [$tk['user_id'], $id]);
    $againstN = $tk['reported_user_id'] ? (int)DB::value('SELECT COUNT(*) FROM support_tickets WHERE reported_user_id = ?', [$tk['reported_user_id']]) : 0;
?>
<div class="grid-2 ticket-admin">
    <div class="box">
        <div class="box-head">#<?= (int)$tk['id'] ?> · <?= e($tk['subject']) ?> <?= $statusTag($tk['status']) ?></div>
        <div class="ticket-thread">
            <?php foreach ($replies as $r): ?>
                <div class="tmsg <?= (int)$r['is_internal'] ? 'tmsg-note' : ((int)$r['is_staff'] ? 'tmsg-staff' : 'tmsg-user') ?>">
                    <div class="tmsg-head"><strong><?= (int)$r['is_internal'] ? '📝 ' . e(t('Nota interna')) . ' · ' : ((int)$r['is_staff'] ? '🛡 ' : '') ?><?= e($r['username'] ?? '-') ?></strong> <span class="small muted"><?= e(fdate($r['created_at'])) ?></span></div>
                    <div class="tmsg-body"><?= nl2br(e($r['body'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="box-body">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                <div class="field"><label><?= e(t('Respuesta')) ?></label><textarea name="body" rows="5" maxlength="5000"></textarea></div>
                <div class="inline-form">
                    <label style="margin:0"><?= e(t('Estado tras responder')) ?>:</label>
                    <select name="status">
                        <?php foreach (['answered', 'pending', 'closed', 'open'] as $s): ?><option value="<?= $s ?>"><?= e(t(Moderation::STATUSES[$s][0])) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-accent" type="submit" name="action" value="reply"><?= e(t('Responder al usuario')) ?></button>
                    <button class="btn btn-grey" type="submit" name="action" value="note"><?= e(t('Guardar como nota interna')) ?></button>
                </div>
            </form>
        </div>
    </div>
    <div class="stack">
        <div class="box"><div class="box-head"><?= e(t('Datos del ticket')) ?></div>
            <table class="kv">
                <tr><th><?= e(t('Usuario')) ?></th><td><a href="<?= e(url('admin_user', ['id' => $tk['user_id']])) ?>"><?= e($tk['username']) ?></a><?= (int)$tk['is_banned'] ? ' <span class="tag tag-red">' . e(t('Sancionado')) . '</span>' : '' ?><br><span class="small muted"><?= e($tk['email']) ?></span></td></tr>
                <tr><th><?= e(t('Equipo')) ?></th><td><?= $tk['team_id'] ? team_link($tk['team_id'], $tk['team_name']) : '–' ?></td></tr>
                <tr><th><?= e(t('Categoría')) ?></th><td><?= e($catLabel($tk['category'])) ?></td></tr>
                <?php if ($tk['reported']): ?>
                <tr><th><?= e(t('Usuario reportado')) ?></th><td><strong><?= e($tk['reported']) ?></strong> <span class="small muted">(<?= e(t('{n} denuncias en total', ['n' => $againstN])) ?>)</span><br>
                    <a class="btn btn-small btn-danger" href="<?= e(url('admin_user', ['id' => $tk['reported_user_id'], 'ticket' => $id])) ?>">🔎 <?= e(t('Investigar y sancionar')) ?></a></td></tr>
                <?php endif; ?>
                <?php if ($tk['link']): ?><tr><th><?= e(t('Enlace')) ?></th><td class="small"><a href="<?= e($tk['link']) ?>" rel="nofollow noopener" target="_blank"><?= e($tk['link']) ?></a></td></tr><?php endif; ?>
                <tr><th><?= e(t('Abierto')) ?></th><td><?= e(fdate($tk['created_at'])) ?></td></tr>
                <tr><th><?= e(t('Asignado a')) ?></th><td><?= $tk['assigned'] ? e($tk['assigned']) : '–' ?>
                    <?php if ((int)$tk['assigned_to'] !== (int)$admin['id']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-small btn-grey" name="action" value="assign"><?= e(t('Asignármelo')) ?></button></form><?php endif; ?></td></tr>
                <tr><th><?= e(t('Estado')) ?></th><td>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="status">
                        <select name="status" onchange="this.form.submit()"><?php foreach (Moderation::STATUSES as $s => [$label]): ?><option value="<?= $s ?>"<?= $tk['status'] === $s ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach; ?></select>
                    </form></td></tr>
            </table>
            <div class="box-body"><a class="btn btn-small" href="<?= e(url('admin_user', ['id' => $tk['user_id'], 'ticket' => $id])) ?>">🔎 <?= e(t('Ver la actividad de {name}', ['name' => $tk['username']])) ?></a></div>
        </div>
        <?php if ($others): ?>
        <div class="box"><div class="box-head"><?= e(t('Otros tickets de este usuario')) ?></div>
            <table class="table compact">
                <?php foreach ($others as $o): ?><tr><td><a href="<?= e(url('admin_tickets', ['id' => $o['id']])) ?>">#<?= (int)$o['id'] ?> <?= e($o['subject']) ?></a></td><td class="right"><?= $statusTag($o['status']) ?></td></tr><?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<p><a href="<?= e(url('admin_tickets')) ?>">« <?= e(t('Volver a la lista de tickets')) ?></a></p>

<?php else:
    $status = (string)get('status', 'active');
    $cat = (string)get('cat');
    $where = [];
    $params = [];
    if ($status === 'active') {
        $where[] = "t.status <> 'closed'";
    } elseif (isset(Moderation::STATUSES[$status])) {
        $where[] = 't.status = ?';
        $params[] = $status;
    }
    if (isset(Moderation::CATEGORIES[$cat])) {
        $where[] = 't.category = ?';
        $params[] = $cat;
    }
    $sql = 'FROM support_tickets t JOIN users u ON u.id = t.user_id LEFT JOIN users r ON r.id = t.reported_user_id LEFT JOIN users a ON a.id = t.assigned_to'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $total = (int)DB::value("SELECT COUNT(*) $sql", $params);
    $pg = max(1, (int)get('p', 1));
    $rows = DB::all("SELECT t.*, u.username, r.username AS reported, a.username AS assigned $sql
        ORDER BY FIELD(t.status, 'open', 'pending', 'answered', 'closed'), t.updated_at DESC LIMIT 30 OFFSET " . (($pg - 1) * 30), $params);
    $counts = array_column(DB::all('SELECT status, COUNT(*) n FROM support_tickets GROUP BY status'), 'n', 'status');
?>
<div class="stat-tiles">
    <?php foreach (['open', 'pending', 'answered', 'closed'] as $s): ?>
        <a class="tile" href="<?= e(url('admin_tickets', ['status' => $s])) ?>"><div class="tv"><?= (int)($counts[$s] ?? 0) ?></div><div class="tl"><?= e(t(Moderation::STATUSES[$s][0])) ?></div></a>
    <?php endforeach; ?>
</div>
<div class="box">
    <form method="get" class="box-body inline-form">
        <input type="hidden" name="page" value="admin_tickets">
        <select name="status" onchange="this.form.submit()">
            <option value="active"<?= $status === 'active' ? ' selected' : '' ?>><?= e(t('Sin cerrar')) ?></option>
            <option value="all"<?= $status === 'all' ? ' selected' : '' ?>><?= e(t('Todos')) ?></option>
            <?php foreach (Moderation::STATUSES as $s => [$label]): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach; ?>
        </select>
        <select name="cat" onchange="this.form.submit()">
            <option value=""><?= e(t('Todas las categorías')) ?></option>
            <?php foreach (Moderation::CATEGORIES as $k => [$label, $icon]): ?><option value="<?= $k ?>"<?= $cat === $k ? ' selected' : '' ?>><?= $icon ?> <?= e(t($label)) ?></option><?php endforeach; ?>
        </select>
    </form>
    <div class="table-wrap">
    <table class="table compact">
        <thead><tr><th>#</th><th><?= e(t('Asunto')) ?></th><th><?= e(t('Usuario')) ?></th><th><?= e(t('Categoría')) ?></th><th><?= e(t('Reportado')) ?></th><th><?= e(t('Asignado a')) ?></th><th><?= e(t('Actualizado')) ?></th><th><?= e(t('Estado')) ?></th></tr></thead>
        <?php foreach ($rows as $r): $new = $r['status'] === 'open' && (!$r['staff_read_at'] || $r['staff_read_at'] < $r['updated_at']); ?>
            <tr<?= $new ? ' class="highlight"' : '' ?>>
                <td><?= (int)$r['id'] ?></td>
                <td><a href="<?= e(url('admin_tickets', ['id' => $r['id']])) ?>"><?= e($r['subject']) ?></a></td>
                <td><a href="<?= e(url('admin_user', ['id' => $r['user_id']])) ?>"><?= e($r['username']) ?></a></td>
                <td class="small"><?= e($catLabel($r['category'])) ?></td>
                <td><?= $r['reported'] ? '<a href="' . e(url('admin_user', ['id' => $r['reported_user_id']])) . '">' . e($r['reported']) . '</a>' : '–' ?></td>
                <td class="small"><?= e($r['assigned'] ?? '–') ?></td>
                <td class="small nowrap"><?= e(fdate($r['updated_at'])) ?></td>
                <td><?= $statusTag($r['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted"><?= e(t('No hay tickets.')) ?></td></tr><?php endif; ?>
    </table>
    </div>
    <?= paginate($total, 30, $pg, 'admin_tickets', ['status' => $status, 'cat' => $cat]) ?>
</div>
<?php endif; ?>
