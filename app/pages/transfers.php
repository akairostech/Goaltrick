<?php
$team = require_team();
$pageTitle = t('Transferencias');
$navActive = 'transfers';
$tab = (string)get('tab', 'search');
if (!in_array($tab, ['search', 'mine', 'history', 'latest'], true)) {
    $tab = 'search';
}
$teamId = (int)$team['id'];
?>
<div class="page-title">
    <h1>🔁 <?= e(t('Transferencias')) ?></h1>
    <span class="muted"><?= e(t('Caja')) ?>: <strong><?= e(money($team['cash'])) ?></strong> · <?= e(t('Comprometido en pujas')) ?>: <strong><?= e(money(TransferService::committed($teamId))) ?></strong></span>
</div>
<div class="tabs">
    <a class="<?= $tab === 'search' ? 'active' : '' ?>" href="<?= e(url('transfers')) ?>"><?= e(t('Buscar jugadores')) ?></a>
    <a class="<?= $tab === 'mine' ? 'active' : '' ?>" href="<?= e(url('transfers', ['tab' => 'mine'])) ?>"><?= e(t('Mis ventas y pujas')) ?></a>
    <a class="<?= $tab === 'history' ? 'active' : '' ?>" href="<?= e(url('transfers', ['tab' => 'history'])) ?>"><?= e(t('Historial de transferencias')) ?></a>
    <a class="<?= $tab === 'latest' ? 'active' : '' ?>" href="<?= e(url('transfers', ['tab' => 'latest'])) ?>"><?= e(t('Últimos traspasos del mundo')) ?></a>
</div>

<?php if ($tab === 'search'):
    $f = [
        'role' => (string)get('role'), 'age_min' => (int)get('age_min', 17), 'age_max' => (int)get('age_max', 40),
        'skill' => (string)get('skill'), 'skill_min' => (int)get('skill_min', 0), 'skill2' => (string)get('skill2'), 'skill2_min' => (int)get('skill2_min', 0),
        'spec' => (string)get('spec'), 'price_max' => (int)get('price_max', 0),
    ];
    $where = ["l.status = 'active'", 'l.deadline > ?', 'p.age_years BETWEEN ? AND ?'];
    $params = [now(), $f['age_min'], $f['age_max']];
    if (isset(ROLES[$f['role']])) {
        $where[] = 'p.role = ?';
        $params[] = $f['role'];
    }
    foreach ([['skill', 'skill_min'], ['skill2', 'skill2_min']] as [$sk, $mn]) {
        if (isset(SKILLS[$f[$sk]]) && $f[$mn] > 0) {
            $where[] = 'p.' . $f[$sk] . ' >= ?';
            $params[] = $f[$mn];
        }
    }
    if (isset(SPECIALTIES[$f['spec']])) {
        $where[] = 'p.specialty = ?';
        $params[] = $f['spec'];
    }
    if ($f['price_max'] > 0) {
        $where[] = 'GREATEST(l.asking_price, COALESCE(l.highest_bid, 0)) <= ?';
        $params[] = $f['price_max'];
    }
    $pageN = max(1, (int)get('p', 1));
    $per = 25;
    $sqlWhere = implode(' AND ', $where);
    $total = (int)DB::value("SELECT COUNT(*) FROM transfer_listings l JOIN players p ON p.id = l.player_id WHERE $sqlWhere", $params);
    $rows = DB::all("SELECT l.*, l.id AS lid, p.*, t.name AS seller FROM transfer_listings l JOIN players p ON p.id = l.player_id JOIN teams t ON t.id = l.seller_team_id
        WHERE $sqlWhere ORDER BY l.deadline LIMIT $per OFFSET " . (($pageN - 1) * $per), $params);
    $skillSelect = function (string $name, string $val) {
        $h = '<select name="' . $name . '"><option value="">—</option>';
        foreach (SKILLS as $k => $v) {
            $h .= '<option value="' . $k . '"' . ($val === $k ? ' selected' : '') . '>' . e(skill_title($k)) . '</option>';
        }
        return $h . '</select>';
    };
    $levelSelect = function (string $name, int $val) {
        $h = '<select name="' . $name . '">';
        foreach (SKILL_LEVELS as $i => $n) {
            $h .= '<option value="' . $i . '"' . ($val === $i ? ' selected' : '') . '>' . e(t($n)) . ' (' . $i . ')</option>';
        }
        return $h . '</select>';
    };
?>
<div class="box">
    <div class="box-head"><?= e(t('Filtros de búsqueda')) ?></div>
    <div class="box-body">
        <form method="get">
            <input type="hidden" name="page" value="transfers">
            <div class="form-row">
                <div class="field"><label><?= e(t('Posición')) ?></label><select name="role"><option value=""><?= e(t('Todas')) ?></option><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>"<?= $f['role'] === $k ? ' selected' : '' ?>><?= e(role_name($k)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label><?= e(t('Edad mínima')) ?></label><input type="number" name="age_min" min="17" max="45" value="<?= $f['age_min'] ?>"></div>
                <div class="field"><label><?= e(t('Edad máxima')) ?></label><input type="number" name="age_max" min="17" max="45" value="<?= $f['age_max'] ?>"></div>
                <div class="field"><label><?= e(t('Especialidad')) ?></label><select name="spec"><option value=""><?= e(t('Cualquiera')) ?></option><?php foreach (SPECIALTIES as $k => [$n]): ?><option value="<?= $k ?>"<?= $f['spec'] === $k ? ' selected' : '' ?>><?= e(specialty_name($k)) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="field"><label><?= e(t('Habilidad')) ?></label><?= $skillSelect('skill', $f['skill']) ?></div>
                <div class="field"><label><?= e(t('Mínimo')) ?></label><?= $levelSelect('skill_min', $f['skill_min']) ?></div>
                <div class="field"><label><?= e(t('Habilidad 2')) ?></label><?= $skillSelect('skill2', $f['skill2']) ?></div>
                <div class="field"><label><?= e(t('Mínimo')) ?></label><?= $levelSelect('skill2_min', $f['skill2_min']) ?></div>
                <div class="field"><label><?= e(t('Precio máximo')) ?></label><input type="number" name="price_max" min="0" step="10000" value="<?= $f['price_max'] ?: '' ?>"></div>
            </div>
            <button class="btn" type="submit">🔎 <?= e(t('Buscar')) ?></button>
            <a class="btn btn-grey" href="<?= e(url('transfers')) ?>"><?= e(t('Limpiar')) ?></a>
        </form>
    </div>
</div>
<div class="box">
    <div class="box-head"><?= e(t('{n} jugadores encontrados', ['n' => num($total)])) ?></div>
    <div class="table-wrap">
    <table class="table compact">
        <thead><tr><th><?= e(t('Jugador')) ?></th><th><?= e(t('Pos')) ?></th><th><?= e(t('Edad')) ?></th><th class="num">TSI</th><th class="num"><?= e(t('For')) ?></th><th class="num"><?= e(t('Por')) ?></th><th class="num"><?= e(t('Def')) ?></th><th class="num" title="<?= e(t('Jugadas')) ?>"><?= e(t('Jug')) ?></th><th class="num" title="<?= e(t('Lateral')) ?>"><?= e(t('Lat')) ?></th><th class="num"><?= e(t('Pas')) ?></th><th class="num"><?= e(t('Ano')) ?></th><th class="num"><?= e(t('BP')) ?></th><th><?= e(t('Esp.')) ?></th><th class="num"><?= e(t('Precio / puja')) ?></th><th><?= e(t('Cierre')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr<?= (int)$r['highest_bidder_id'] === $teamId ? ' class="me"' : '' ?>>
                <td><?= avatar_img($r, 24) ?> <?= player_link($r) ?><div class="small muted"><?= e($r['seller']) ?></div></td>
                <td><span class="tag"><?= e($r['role']) ?></span></td>
                <td><?= (int)$r['age_years'] ?></td>
                <td class="num"><?= num($r['tsi']) ?></td>
                <td class="num"><?= (int)floor((float)$r['form']) ?></td>
                <?php foreach (['keeper', 'defending', 'playmaking', 'winger', 'passing', 'scoring', 'set_pieces'] as $sk): ?>
                    <td class="num" title="<?= e(skill_label($r[$sk])) ?>"><?= (int)floor((float)$r[$sk]) ?></td>
                <?php endforeach; ?>
                <td><?= $r['specialty'] ? SPECIALTIES[$r['specialty']][1] : '' ?></td>
                <td class="num"><?= $r['highest_bid'] ? '<strong>' . e(money($r['highest_bid'])) . '</strong>' : e(money($r['asking_price'])) ?></td>
                <td class="nowrap small" data-deadline="<?= e(date('c', strtotime($r['deadline']))) ?>"><?= e(time_left($r['deadline'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="15" class="muted"><?= e(t('No hay jugadores que cumplan los filtros.')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <div class="box-body"><?= paginate($total, $per, $pageN, 'transfers', array_filter($f)) ?></div>
</div>

<?php elseif ($tab === 'mine'):
    $selling = DB::all("SELECT l.*, p.first_name, p.last_name, p.id AS pid, t.name AS bidder FROM transfer_listings l JOIN players p ON p.id = l.player_id
        LEFT JOIN teams t ON t.id = l.highest_bidder_id WHERE l.seller_team_id = ? AND l.status = 'active' ORDER BY l.deadline", [$teamId]);
    $bids = DB::all("SELECT l.*, p.first_name, p.last_name, p.id AS pid, s.name AS seller, (SELECT MAX(b.amount) FROM transfer_bids b WHERE b.listing_id = l.id AND b.team_id = ?) AS my_bid
        FROM transfer_listings l JOIN players p ON p.id = l.player_id JOIN teams s ON s.id = l.seller_team_id
        WHERE l.status = 'active' AND EXISTS (SELECT 1 FROM transfer_bids b WHERE b.listing_id = l.id AND b.team_id = ?) ORDER BY l.deadline", [$teamId, $teamId]);
    $done = DB::all("SELECT l.*, p.first_name, p.last_name, p.id AS pid, s.name AS seller, b.name AS buyer FROM transfer_listings l JOIN players p ON p.id = l.player_id
        JOIN teams s ON s.id = l.seller_team_id LEFT JOIN teams b ON b.id = l.highest_bidder_id
        WHERE l.status = 'sold' AND (l.seller_team_id = ? OR l.highest_bidder_id = ?) ORDER BY l.closed_at DESC LIMIT 20", [$teamId, $teamId]);
?>
<div class="box">
    <div class="box-head"><?= e(t('Mis jugadores en venta')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Jugador')) ?></th><th class="num"><?= e(t('Precio mínimo')) ?></th><th class="num"><?= e(t('Puja más alta')) ?></th><th><?= e(t('Pujador')) ?></th><th><?= e(t('Cierre')) ?></th></tr></thead>
        <?php foreach ($selling as $s): ?>
            <tr><td><a href="<?= e(url('player', ['id' => $s['pid']])) ?>"><?= e(player_name($s)) ?></a></td><td class="num"><?= e(money($s['asking_price'])) ?></td>
                <td class="num"><?= $s['highest_bid'] ? e(money($s['highest_bid'])) : '-' ?></td><td><?= e($s['bidder'] ?? '-') ?></td>
                <td data-deadline="<?= e(date('c', strtotime($s['deadline']))) ?>"><?= e(time_left($s['deadline'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$selling): ?><tr><td colspan="5" class="muted"><?= e(t('No tienes jugadores en venta. Ve a la ficha de un jugador para venderlo.')) ?></td></tr><?php endif; ?>
    </table>
</div>
<div class="box">
    <div class="box-head"><?= e(t('Mis pujas activas')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Jugador')) ?></th><th><?= e(t('Vendedor')) ?></th><th class="num"><?= e(t('Mi puja')) ?></th><th class="num"><?= e(t('Puja más alta')) ?></th><th><?= e(t('Estado')) ?></th><th><?= e(t('Cierre')) ?></th></tr></thead>
        <?php foreach ($bids as $b): $winning = (int)$b['highest_bidder_id'] === $teamId; ?>
            <tr<?= $winning ? ' class="me"' : '' ?>><td><a href="<?= e(url('player', ['id' => $b['pid']])) ?>"><?= e(player_name($b)) ?></a></td><td><?= e($b['seller']) ?></td>
                <td class="num"><?= e(money($b['my_bid'])) ?></td><td class="num"><?= e(money($b['highest_bid'])) ?></td>
                <td><?= $winning ? '<span class="pos">' . e(t('Ganando')) . '</span>' : '<span class="neg">' . e(t('Superado')) . '</span>' ?></td>
                <td data-deadline="<?= e(date('c', strtotime($b['deadline']))) ?>"><?= e(time_left($b['deadline'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$bids): ?><tr><td colspan="6" class="muted"><?= e(t('No tienes pujas activas.')) ?></td></tr><?php endif; ?>
    </table>
</div>
<div class="box">
    <div class="box-head"><?= e(t('Mis traspasos recientes')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Jugador')) ?></th><th><?= e(t('De')) ?></th><th><?= e(t('A##destino')) ?></th><th class="num"><?= e(t('Precio')) ?></th></tr></thead>
        <?php foreach ($done as $d): ?>
            <tr><td><?= e(fdate($d['closed_at'])) ?></td><td><a href="<?= e(url('player', ['id' => $d['pid']])) ?>"><?= e(player_name($d)) ?></a></td><td><?= e($d['seller']) ?></td><td><?= e($d['buyer']) ?></td><td class="num"><?= e(money($d['highest_bid'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$done): ?><tr><td colspan="5" class="muted"><?= e(t('Sin traspasos.')) ?></td></tr><?php endif; ?>
    </table>
</div>

<?php elseif ($tab === 'history'):
    $kind = (string)get('kind', 'all');
    $kind = in_array($kind, ['all', 'buy', 'sell'], true) ? $kind : 'all';
    $sum = DB::row("SELECT
            SUM(highest_bidder_id = ?) AS buys, COALESCE(SUM(CASE WHEN highest_bidder_id = ? THEN highest_bid END), 0) AS spent,
            SUM(seller_team_id = ?) AS sells, COALESCE(SUM(CASE WHEN seller_team_id = ? THEN highest_bid END), 0) AS earned
        FROM transfer_listings WHERE status = 'sold' AND (seller_team_id = ? OR highest_bidder_id = ?)", array_fill(0, 6, $teamId));
    $buys = (int)$sum['buys'];
    $sells = (int)$sum['sells'];
    $spent = (int)$sum['spent'];
    $earned = (int)$sum['earned'];
    $count = $buys + $sells;
    $diff = $earned - $spent;
    $where = match ($kind) {
        'buy' => 'l.highest_bidder_id = ?',
        'sell' => 'l.seller_team_id = ?',
        default => '(l.seller_team_id = ? OR l.highest_bidder_id = ?)',
    };
    $wp = $kind === 'all' ? [$teamId, $teamId] : [$teamId];
    $total = (int)DB::value("SELECT COUNT(*) FROM transfer_listings l WHERE l.status = 'sold' AND $where", $wp);
    $per = 25;
    $pg = max(1, (int)get('p', 1));
    $rows = DB::all("SELECT l.*, p.first_name, p.last_name, p.id AS pid, p.avatar, p.team_id AS ptid,
            s.id AS sid, s.name AS seller, s.crest_id AS s_crest_id, s.crest_custom AS s_crest_custom,
            b.id AS bid, b.name AS buyer, b.crest_id AS b_crest_id, b.crest_custom AS b_crest_custom
        FROM transfer_listings l JOIN players p ON p.id = l.player_id JOIN teams s ON s.id = l.seller_team_id LEFT JOIN teams b ON b.id = l.highest_bidder_id
        WHERE l.status = 'sold' AND $where ORDER BY l.closed_at DESC LIMIT " . $per . ' OFFSET ' . (($pg - 1) * $per), $wp);
    $crestOf = fn(array $r, string $x) => ['id' => $r[$x . 'id'], 'crest_id' => $r[$x . '_crest_id'], 'crest_custom' => $r[$x . '_crest_custom']];
    $avg = fn(int $amount, int $n) => $n > 0 ? (int)round($amount / $n) : 0;
?>
<div class="stat-tiles">
    <div class="tile"><div class="tv neg"><?= e(money($spent)) ?></div><div class="tl"><?= e(t('Total en compras')) ?></div><div class="small muted"><?= e(t('{n} compras · media {avg}', ['n' => $buys, 'avg' => money($avg($spent, $buys))])) ?></div></div>
    <div class="tile"><div class="tv pos"><?= e(money($earned)) ?></div><div class="tl"><?= e(t('Total en ventas')) ?></div><div class="small muted"><?= e(t('{n} ventas · media {avg}', ['n' => $sells, 'avg' => money($avg($earned, $sells))])) ?></div></div>
    <div class="tile"><div class="tv <?= $diff >= 0 ? 'pos' : 'neg' ?>"><?= e(money($diff, true)) ?></div><div class="tl"><?= e(t('Diferencia (ventas − compras)')) ?></div><div class="small muted"><?= e(t('media por traspaso: {avg}', ['avg' => money($avg($diff, $count), true)])) ?></div></div>
    <div class="tile"><div class="tv"><?= num($count) ?></div><div class="tl"><?= e(t('Transferencias realizadas')) ?></div><div class="small muted"><?= e(t('{b} compras · {s} ventas', ['b' => $buys, 's' => $sells])) ?></div></div>
</div>
<div class="box">
    <div class="box-head"><?= e(t('Promedios')) ?></div>
    <table class="table compact">
        <thead><tr><th></th><th class="num"><?= e(t('Total')) ?></th><th class="num"><?= e(t('Operaciones')) ?></th><th class="num"><?= e(t('Promedio')) ?></th></tr></thead>
        <tr><td>🛒 <?= e(t('Compras')) ?></td><td class="num"><?= e(money($spent)) ?></td><td class="num"><?= $buys ?></td><td class="num"><?= e(money($avg($spent, $buys))) ?></td></tr>
        <tr><td>💰 <?= e(t('Ventas')) ?></td><td class="num"><?= e(money($earned)) ?></td><td class="num"><?= $sells ?></td><td class="num"><?= e(money($avg($earned, $sells))) ?></td></tr>
        <tr><td>⚖️ <?= e(t('Diferencia')) ?></td><td class="num <?= $diff >= 0 ? 'pos' : 'neg' ?>"><?= e(money($diff, true)) ?></td><td class="num"><?= $count ?></td><td class="num <?= $diff >= 0 ? 'pos' : 'neg' ?>"><?= e(money($avg($diff, $count), true)) ?></td></tr>
    </table>
</div>
<div class="box">
    <div class="box-head"><?= e(t('Historial de transferencias')) ?>
        <span class="small">
            <?php foreach (['all' => t('Todas'), 'buy' => t('Compras'), 'sell' => t('Ventas')] as $k => $label): ?>
                <a class="btn btn-small <?= $kind === $k ? '' : 'btn-grey' ?>" href="<?= e(url('transfers', ['tab' => 'history', 'kind' => $k])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </span>
    </div>
    <div class="table-wrap">
    <table class="table compact">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Jugador')) ?></th><th></th><th><?= e(t('Procedencia')) ?></th><th></th><th><?= e(t('Destino')) ?></th><th class="num"><?= e(t('Precio')) ?></th><th class="num" title="<?= e(t('TSI del jugador en el momento del traspaso')) ?>">TSI</th><th class="num"><?= e(t('Edad')) ?></th></tr></thead>
        <?php foreach ($rows as $r): $isBuy = (int)$r['highest_bidder_id'] === $teamId; ?>
            <tr>
                <td class="nowrap"><?= e(fdate($r['closed_at'], false)) ?></td>
                <td class="nowrap"><?= avatar_img(['id' => $r['pid'], 'avatar' => $r['avatar'], 'team_id' => $r['ptid']], 22) ?> <a href="<?= e(url('player', ['id' => $r['pid']])) ?>"><?= e(player_name($r)) ?></a></td>
                <td><span class="tag <?= $isBuy ? 'tag-buy' : 'tag-sell' ?>"><?= e($isBuy ? t('Compra') : t('Venta')) ?></span></td>
                <td class="nowrap"><?= crest_img($crestOf($r, 's'), 18) ?> <?= team_link($r['sid'], $r['seller']) ?></td>
                <td class="center muted">→</td>
                <td class="nowrap"><?php if ($r['bid']): ?><?= crest_img($crestOf($r, 'b'), 18) ?> <?= team_link($r['bid'], $r['buyer']) ?><?php else: ?>–<?php endif; ?></td>
                <td class="num <?= $isBuy ? 'neg' : 'pos' ?>"><?= e(money($isBuy ? -(int)$r['highest_bid'] : (int)$r['highest_bid'], true)) ?></td>
                <td class="num"><?= $r['tsi_at_sale'] !== null ? num($r['tsi_at_sale']) : '–' ?></td>
                <td class="num"><?= $r['age_at_sale'] !== null ? (int)$r['age_at_sale'] : '–' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" class="muted"><?= e(t('Todavía no has comprado ni vendido ningún jugador.')) ?></td></tr><?php endif; ?>
    </table>
    </div>
    <?= paginate($total, $per, $pg, 'transfers', ['tab' => 'history', 'kind' => $kind]) ?>
</div>

<?php else:
    $hist = DB::all("SELECT l.*, p.first_name, p.last_name, p.id AS pid, p.age_years, p.tsi, s.name AS seller, b.name AS buyer FROM transfer_listings l JOIN players p ON p.id = l.player_id
        JOIN teams s ON s.id = l.seller_team_id LEFT JOIN teams b ON b.id = l.highest_bidder_id WHERE l.status = 'sold' ORDER BY l.closed_at DESC LIMIT 40");
?>
<div class="box">
    <div class="box-head"><?= e(t('Últimos traspasos del mundo')) ?></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Fecha')) ?></th><th><?= e(t('Jugador')) ?></th><th><?= e(t('Edad')) ?></th><th class="num">TSI</th><th><?= e(t('De')) ?></th><th><?= e(t('A##destino')) ?></th><th class="num"><?= e(t('Precio')) ?></th></tr></thead>
        <?php foreach ($hist as $d): ?>
            <tr><td class="nowrap"><?= e(fdate($d['closed_at'])) ?></td><td><a href="<?= e(url('player', ['id' => $d['pid']])) ?>"><?= e(player_name($d)) ?></a></td><td><?= (int)($d['age_at_sale'] ?? $d['age_years']) ?></td><td class="num"><?= num($d['tsi_at_sale'] ?? $d['tsi']) ?></td><td><?= e($d['seller']) ?></td><td><?= e($d['buyer']) ?></td><td class="num"><?= e(money($d['highest_bid'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$hist): ?><tr><td colspan="7" class="muted"><?= e(t('Todavía no se ha realizado ningún traspaso.')) ?></td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>
