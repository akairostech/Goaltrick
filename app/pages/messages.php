<?php
$user = require_login();
$pageTitle = t('Mensajes');
$navActive = 'community';
$uid = (int)$user['id'];

if (is_post()) {
    csrf_check();
    $a = (string)post('action');
    if ($a === 'send') {
        $to = DB::row('SELECT id FROM users WHERE username = ?', [trim((string)post('to'))]);
        $subject = trim((string)post('subject'));
        $body = trim((string)post('body'));
        $recent = (int)DB::value('SELECT COUNT(*) FROM messages WHERE from_user_id = ? AND created_at > ?', [$uid, date('Y-m-d H:i:s', time() - 3600)]);
        if (!$to) {
            flash('error', t('El usuario destinatario no existe.'));
        } elseif ((int)$to['id'] === $uid) {
            flash('error', t('No puedes enviarte mensajes a ti mismo.'));
        } elseif ($subject === '' || $body === '') {
            flash('error', t('Escribe un asunto y un mensaje.'));
        } elseif ($recent >= 30) {
            flash('error', t('Has enviado demasiados mensajes en la última hora.'));
        } else {
            DB::insert('messages', ['from_user_id' => $uid, 'to_user_id' => $to['id'], 'subject' => mb_substr($subject, 0, 120),
                'body' => mb_substr($body, 0, 5000), 'created_at' => now()]);
            flash('success', t('Mensaje enviado.'));
            redirect(url('messages', ['box' => 'sent']));
        }
        redirect(url('messages', ['compose' => 1, 'to' => (string)post('to')]));
    }
    if ($a === 'delete') {
        $id = (int)post('id');
        DB::exec('UPDATE messages SET deleted_by_recipient = 1 WHERE id = ? AND to_user_id = ?', [$id, $uid]);
        DB::exec('UPDATE messages SET deleted_by_sender = 1 WHERE id = ? AND from_user_id = ?', [$id, $uid]);
        flash('info', t('Mensaje borrado.'));
    }
    redirect(url('messages'));
}

$box = (string)get('box', 'inbox');
$view = (int)get('id');
$compose = (bool)get('compose');
?>
<div class="page-title"><h1><?= e(t('Mensajes')) ?></h1><a class="btn" href="<?= e(url('messages', ['compose' => 1])) ?>">✏️ <?= e(t('Nuevo mensaje')) ?></a></div>
<div class="tabs">
    <a class="<?= $box === 'inbox' && !$compose && !$view ? 'active' : '' ?>" href="<?= e(url('messages')) ?>"><?= e(t('Recibidos')) ?></a>
    <a class="<?= $box === 'sent' ? 'active' : '' ?>" href="<?= e(url('messages', ['box' => 'sent'])) ?>"><?= e(t('Enviados')) ?></a>
    <?php if ($compose): ?><a class="active" href="#"><?= e(t('Redactar')) ?></a><?php endif; ?>
</div>

<?php if ($compose):
    $to = (string)get('to');
    $subject = '';
    $quote = '';
    if ($rid = (int)get('reply')) {
        $orig = DB::row('SELECT m.*, u.username FROM messages m LEFT JOIN users u ON u.id = m.from_user_id WHERE m.id = ? AND m.to_user_id = ?', [$rid, $uid]);
        if ($orig) {
            $to = (string)$orig['username'];
            $origSubject = tdb($orig['subject']);
            $subject = (str_starts_with($origSubject, 'Re: ') ? '' : 'Re: ') . $origSubject;
            $quote = "\n\n" . t('----- {user} escribió: -----', ['user' => $orig['username'] ?? t('Sistema')]) . "\n" . tdb($orig['body']);
        }
    }
?>
<div class="box"><div class="box-head"><?= e(t('Nuevo mensaje')) ?></div><div class="box-body">
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="send">
        <div class="field"><label><?= e(t('Para (nombre de usuario)')) ?></label><input type="text" name="to" value="<?= e($to) ?>" required></div>
        <div class="field"><label><?= e(t('Asunto')) ?></label><input type="text" name="subject" value="<?= e($subject) ?>" maxlength="120" required></div>
        <div class="field"><label><?= e(t('Mensaje')) ?></label><textarea name="body" maxlength="5000" required><?= e($quote) ?></textarea></div>
        <button class="btn" type="submit"><?= e(t('Enviar')) ?></button>
    </form>
</div></div>

<?php elseif ($view):
    $m = DB::row('SELECT m.*, f.username AS from_name, t.username AS to_name FROM messages m LEFT JOIN users f ON f.id = m.from_user_id JOIN users t ON t.id = m.to_user_id
        WHERE m.id = ? AND ((m.to_user_id = ? AND m.deleted_by_recipient = 0) OR (m.from_user_id = ? AND m.deleted_by_sender = 0))', [$view, $uid, $uid]);
    if (!$m) {
        echo '<div class="alert alert-error">' . e(t('Mensaje no encontrado.')) . '</div>';
        return;
    }
    if ((int)$m['to_user_id'] === $uid && !$m['read_at']) {
        DB::exec('UPDATE messages SET read_at = ? WHERE id = ?', [now(), $view]);
    }
?>
<div class="box">
    <div class="box-head"><?= e(tdb($m['subject'])) ?> <span class="small muted"><?= e(fdate($m['created_at'])) ?></span></div>
    <div class="box-body">
        <p class="small"><?= e(t('De')) ?>: <strong><?= $m['from_user_id'] ? manager_link($m['from_user_id'], $m['from_name'], true) : e(t('Sistema')) ?></strong> · <?= e(t('Para')) ?>: <strong><?= manager_link($m['to_user_id'], $m['to_name'], true) ?></strong></p>
        <div class="msg-body"><?= e(tdb($m['body'])) ?></div>
        <br>
        <?php if ((int)$m['to_user_id'] === $uid && $m['from_user_id']): ?><a class="btn" href="<?= e(url('messages', ['compose' => 1, 'reply' => $m['id']])) ?>"><?= e(t('Responder')) ?></a>
            <a class="btn btn-grey" href="<?= e(url('support', ['new' => 1, 'report' => $m['from_name'], 'link' => 'index.php?page=messages&view=' . (int)$m['id']])) ?>" title="<?= e(t('Reportar este mensaje a la administración')) ?>">🚩 <?= e(t('Reportar')) ?></a><?php endif; ?>
        <form method="post" class="inline-form" data-confirm-submit="<?= e(t('¿Borrar este mensaje?')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-grey" type="submit"><?= e(t('Borrar')) ?></button></form>
    </div>
</div>

<?php else:
    if ($box === 'sent') {
        $rows = DB::all('SELECT m.*, u.username AS other, u.id AS other_id FROM messages m JOIN users u ON u.id = m.to_user_id WHERE m.from_user_id = ? AND m.deleted_by_sender = 0 ORDER BY m.id DESC LIMIT 100', [$uid]);
    } else {
        $rows = DB::all('SELECT m.*, u.username AS other, u.id AS other_id FROM messages m LEFT JOIN users u ON u.id = m.from_user_id WHERE m.to_user_id = ? AND m.deleted_by_recipient = 0 ORDER BY m.id DESC LIMIT 100', [$uid]);
    }
?>
<div class="box">
    <table class="table">
        <thead><tr><th><?= e($box === 'sent' ? t('Para') : t('De')) ?></th><th><?= e(t('Asunto')) ?></th><th><?= e(t('Fecha')) ?></th></tr></thead>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= $box !== 'sent' && !$r['read_at'] ? 'msg-unread' : '' ?>">
                <td><?= $r['other_id'] ? manager_link($r['other_id'], $r['other'], true) : e(t('Sistema')) ?></td>
                <td><a href="<?= e(url('messages', ['id' => $r['id']])) ?>"><?= e(tdb($r['subject'])) ?></a></td>
                <td class="nowrap small"><?= e(fdate($r['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="3" class="muted"><?= e(t('No hay mensajes.')) ?></td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
