<?php
/** Ayuda y soporte: los usuarios abren tickets (errores, denuncias, dudas) y la administración responde */
$user = require_login();
$pageTitle = t('Ayuda y soporte');
$navActive = 'help';
$uid = (int)$user['id'];
$id = (int)get('id');

if (is_post()) {
    csrf_check();
    $a = (string)post('action');
    if ($a === 'create') {
        [$newId, $err] = Moderation::createTicket($user, (string)post('category'), (string)post('subject'), (string)post('body'), (string)post('reported'), (string)post('link'));
        if ($err) {
            flash('error', $err);
            $_SESSION['ticket_draft'] = ['category' => post('category'), 'subject' => post('subject'), 'body' => post('body'), 'reported' => post('reported'), 'link' => post('link')];
            redirect(url('support', ['new' => 1]));
        }
        unset($_SESSION['ticket_draft']);
        flash('success', t('Ticket #{id} enviado. Te avisaremos por mensaje cuando la administración responda.', ['id' => $newId]));
        redirect(url('support', ['id' => $newId]));
    }
    $tk = DB::row('SELECT * FROM support_tickets WHERE id = ? AND user_id = ?', [(int)post('id'), $uid]);
    if ($tk) {
        if ($a === 'reply') {
            $err = Moderation::reply((int)$tk['id'], $user, (string)post('body'), false);
            flash($err ? 'error' : 'success', $err ?? t('Respuesta enviada.'));
        } elseif ($a === 'close') {
            Moderation::reply((int)$tk['id'], $user, '', false, false, 'closed');
            flash('success', t('Ticket cerrado. ¡Gracias!'));
        }
        redirect(url('support', ['id' => $tk['id']]));
    }
    redirect(url('support'));
}

$catLabel = fn(string $c) => (Moderation::CATEGORIES[$c][1] ?? '') . ' ' . t(Moderation::CATEGORIES[$c][0] ?? $c);
$statusTag = fn(string $s) => '<span class="tag ' . e(Moderation::STATUSES[$s][1] ?? '') . '">' . e(t(Moderation::STATUSES[$s][0] ?? $s)) . '</span>';
?>
<div class="page-title"><h1>🆘 <?= e(t('Ayuda y soporte')) ?></h1>
    <span><a class="btn btn-small btn-grey" href="<?= e(url('help')) ?>">📖 <?= e(t('Manual del juego')) ?></a>
    <a class="btn btn-small" href="<?= e(url('support', ['new' => 1])) ?>">✚ <?= e(t('Nuevo ticket')) ?></a></span>
</div>

<?php if ($id):
    $tk = DB::row('SELECT t.*, r.username AS reported FROM support_tickets t LEFT JOIN users r ON r.id = t.reported_user_id WHERE t.id = ? AND t.user_id = ?', [$id, $uid]);
    if (!$tk) {
        echo '<div class="alert alert-error">' . e(t('Ticket no encontrado.')) . '</div>';
        return;
    }
    DB::exec('UPDATE support_tickets SET user_read_at = ? WHERE id = ?', [now(), $id]);
    $replies = DB::all('SELECT r.*, u.username FROM support_replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ? AND r.is_internal = 0 ORDER BY r.id', [$id]);
?>
<div class="box">
    <div class="box-head">#<?= (int)$tk['id'] ?> · <?= e($tk['subject']) ?> <?= $statusTag($tk['status']) ?></div>
    <div class="box-body small muted">
        <?= e($catLabel($tk['category'])) ?> · <?= e(t('Abierto el {date}', ['date' => fdate($tk['created_at'])])) ?>
        <?php if ($tk['reported']): ?> · <?= e(t('Usuario reportado: {name}', ['name' => $tk['reported']])) ?><?php endif; ?>
        <?php if ($tk['link']): ?> · <a href="<?= e($tk['link']) ?>" rel="nofollow noopener"><?= e(t('Enlace adjunto')) ?></a><?php endif; ?>
    </div>
    <div class="ticket-thread">
        <?php foreach ($replies as $r): ?>
            <div class="tmsg <?= (int)$r['is_staff'] ? 'tmsg-staff' : 'tmsg-user' ?>">
                <div class="tmsg-head"><strong><?= (int)$r['is_staff'] ? '🛡 ' . e(t('Administración')) : e($r['username'] ?? '') ?></strong> <span class="small muted"><?= e(fdate($r['created_at'])) ?></span></div>
                <div class="tmsg-body"><?= nl2br(e($r['body'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($tk['status'] !== 'closed'): ?>
    <div class="box-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <div class="field"><label><?= e(t('Añadir información')) ?></label><textarea name="body" rows="4" maxlength="5000"></textarea></div>
            <button class="btn" type="submit" name="action" value="reply"><?= e(t('Enviar')) ?></button>
            <button class="btn btn-grey" type="submit" name="action" value="close" formnovalidate><?= e(t('Mi problema está resuelto: cerrar ticket')) ?></button>
        </form>
    </div>
    <?php else: ?>
        <div class="box-body muted"><?= e(t('Este ticket está cerrado. Abre uno nuevo si necesitas más ayuda.')) ?></div>
    <?php endif; ?>
</div>
<p><a href="<?= e(url('support')) ?>">« <?= e(t('Volver a mis tickets')) ?></a></p>

<?php else:
    $draft = $_SESSION['ticket_draft'] ?? [];
    $cat = (string)($draft['category'] ?? get('category', get('new') && get('report') ? 'report' : 'bug'));
    $mine = DB::all('SELECT * FROM support_tickets WHERE user_id = ? ORDER BY (status = \'closed\'), updated_at DESC LIMIT 50', [$uid]);
?>
<div class="grid-2 support-grid">
    <div class="box" id="new-ticket">
        <div class="box-head">✚ <?= e(t('Nuevo ticket')) ?></div>
        <div class="box-body">
            <form method="post" class="ticket-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="create">
                <div class="field"><label><?= e(t('¿En qué podemos ayudarte?')) ?></label>
                    <select name="category" id="ticket-cat">
                        <?php foreach (Moderation::CATEGORIES as $k => [$label, $icon]): ?><option value="<?= e($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= $icon ?> <?= e(t($label)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field" id="reported-field"><label><?= e(t('Nombre del usuario que quieres reportar')) ?></label>
                    <input type="text" name="reported" maxlength="30" value="<?= e((string)($draft['reported'] ?? get('report', ''))) ?>">
                    <div class="hint"><?= e(t('Explica qué ha hecho (insultos, multicuentas, aprovechamiento de errores, mensajes molestos...). Tu denuncia es confidencial: el usuario no sabrá quién le ha reportado.')) ?></div>
                </div>
                <div class="field"><label><?= e(t('Asunto')) ?></label><input type="text" name="subject" maxlength="150" required value="<?= e((string)($draft['subject'] ?? '')) ?>"></div>
                <div class="field"><label><?= e(t('Descripción')) ?></label><textarea name="body" rows="7" maxlength="5000" required placeholder="<?= e(t('Cuéntanos qué ha pasado, cuándo y en qué página. Cuantos más detalles, mejor.')) ?>"><?= e((string)($draft['body'] ?? '')) ?></textarea></div>
                <div class="field"><label><?= e(t('Enlace (opcional)')) ?></label><input type="text" name="link" maxlength="255" value="<?= e((string)($draft['link'] ?? get('link', ''))) ?>" placeholder="index.php?page=match&id=123">
                    <div class="hint"><?= e(t('Por ejemplo, la dirección del partido, del jugador o del equipo relacionado.')) ?></div></div>
                <button class="btn btn-accent" type="submit"><?= e(t('Enviar ticket')) ?></button>
            </form>
        </div>
    </div>
    <div class="box">
        <div class="box-head"><?= e(t('Mis tickets')) ?></div>
        <table class="table compact">
            <?php foreach ($mine as $tk): $unread = in_array($tk['status'], ['answered', 'pending'], true) && (!$tk['user_read_at'] || $tk['user_read_at'] < $tk['updated_at']); ?>
                <tr<?= $unread ? ' class="highlight"' : '' ?>>
                    <td class="nowrap">#<?= (int)$tk['id'] ?></td>
                    <td><a href="<?= e(url('support', ['id' => $tk['id']])) ?>"><?= e($tk['subject']) ?></a><br><span class="small muted"><?= e($catLabel($tk['category'])) ?> · <?= e(fdate($tk['updated_at'])) ?></span></td>
                    <td class="right"><?= $statusTag($tk['status']) ?><?= $unread ? ' <span class="badge">' . e(t('nuevo')) . '</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$mine): ?><tr><td class="muted"><?= e(t('No has abierto ningún ticket.')) ?></td></tr><?php endif; ?>
        </table>
        <div class="box-body small muted"><?= e(t('Respondemos lo antes posible. Cuando la administración conteste recibirás un mensaje en tu buzón.')) ?></div>
    </div>
</div>
<script>
(function () {
    var sel = document.getElementById('ticket-cat'), f = document.getElementById('reported-field');
    if (!sel || !f) { return; }
    var sync = function () { f.style.display = sel.value === 'report' ? '' : 'none'; f.querySelector('input').required = sel.value === 'report'; };
    sel.addEventListener('change', sync); sync();
})();
</script>
<?php endif; ?>
