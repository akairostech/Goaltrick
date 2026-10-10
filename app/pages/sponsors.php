<?php
$team = require_team();
$pageTitle = t('Patrocinio');
$navActive = 'club';
$teamId = (int)$team['id'];
$season = GameState::season();

if (is_post()) {
    csrf_check();
    $err = SponsorService::sign($teamId, (int)post('offer_id'));
    flash($err ? 'error' : 'success', $err ?? t('¡Contrato firmado! Recibirás el pago cada semana en la actualización económica.'));
    redirect(url('sponsors'));
}

$contract = SponsorService::activeContract($teamId, $season);
$offers = SponsorService::offers($teamId);
$history = DB::all("SELECT sc.*, c.name AS company, c.sector FROM sponsor_contracts sc JOIN companies c ON c.id = sc.company_id
    WHERE sc.team_id = ? AND sc.status IN ('active', 'ended') ORDER BY sc.season_from DESC, sc.id DESC LIMIT 20", [$teamId]);
$level = (int)DB::value('SELECT level FROM league_units WHERE id = ?', [$team['league_unit_id']]);
$weeks = max(LeagueService::LEAGUE_ROUNDS, Config::int('game', 'season_weeks', 16));
$prest = fn(int $p) => '<span class="stars">' . str_repeat('★', $p) . '</span><span class="muted">' . str_repeat('☆', 5 - $p) . '</span>';

/** Estimación de ingresos por temporada de una oferta (16 semanas, ~6 victorias) */
$estimate = function (array $o) use ($weeks) {
    $seasons = (int)$o['season_to'] - (int)$o['season_from'] + 1;
    return (int)$o['weekly_amount'] * $weeks + (int)$o['signing_bonus'] / max(1, $seasons) + (int)$o['win_bonus'] * 6;
};
?>
<div class="page-title"><h1><?= e(t('Patrocinio')) ?></h1><span class="muted"><?= e(t('Temporada {n}', ['n' => $season])) ?> · <?= e(t('División {n}', ['n' => LeagueService::roman($level)])) ?> · <?= e(t('Base de la división: {amount}/semana', ['amount' => money(SponsorService::base($level))])) ?></span></div>

<?php if ($offers): $deadline = $offers[0]['expires_at']; ?>
    <div class="alert alert-info">
        <?= t('<strong>Tienes {n} ofertas de patrocinio.</strong> Elige una antes del {date}', ['n' => count($offers), 'date' => '<strong>' . e(fdate_long($deadline)) . '</strong>']) ?>
        (<span data-deadline="<?= e(date('c', strtotime($deadline))) ?>"></span>). <?= e(t('Si no eliges, se firmará automáticamente el contrato fijo.')) ?>
    </div>
    <div class="offers">
        <?php foreach ($offers as $o): [$tname, $tdesc] = SponsorService::TYPES[$o['type']]; $seasons = (int)$o['season_to'] - (int)$o['season_from'] + 1; ?>
            <div class="offer offer-<?= e($o['type']) ?>">
                <div class="offer-type"><?= e(t($tname)) ?></div>
                <img class="offer-logo" src="<?= e(SponsorService::logoUrl((int)$o['company_id'])) ?>" alt="">
                <div class="offer-company"><?= e($o['company']) ?></div>
                <div class="small muted"><?= e(t($o['sector'])) ?> · <?= $prest((int)$o['prestige']) ?></div>
                <div class="offer-slogan">«<?= e(t($o['slogan'])) ?>»</div>
                <table class="kv">
                    <tr><th><?= e(t('Pago semanal')) ?></th><td><?= e(money($o['weekly_amount'])) ?></td></tr>
                    <tr><th><?= e(t('Prima de firma')) ?></th><td><?= (int)$o['signing_bonus'] ? e(money($o['signing_bonus'])) : '—' ?></td></tr>
                    <tr><th><?= e(t('Por victoria')) ?></th><td><?= (int)$o['win_bonus'] ? e(money($o['win_bonus'])) : '—' ?></td></tr>
                    <tr><th><?= e(t('Por título')) ?></th><td><?= (int)$o['title_bonus'] ? e(money($o['title_bonus'])) : '—' ?></td></tr>
                    <tr><th><?= e(t('Duración')) ?></th><td><?= e(t($seasons > 1 ? '{n} temporadas (hasta la {to})' : '{n} temporada (hasta la {to})', ['n' => $seasons, 'to' => (int)$o['season_to']])) ?></td></tr>
                    <tr><th><?= e(t('Estimado/temporada')) ?></th><td class="pos"><?= e(money($estimate($o))) ?></td></tr>
                </table>
                <p class="small muted"><?= e(t($tdesc)) ?></p>
                <form method="post" data-confirm-submit="<?= e(t('¿Firmar con {company}? Las otras ofertas se rechazarán.', ['company' => $o['company']])) ?>">
                    <?= csrf_field() ?><input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
                    <button class="btn btn-block btn-accent" type="submit"><?= e(t('Firmar contrato')) ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="small muted"><?= e(t('El estimado supone {weeks} semanas de pagos y unas 6 victorias en liga. En los contratos de varias temporadas la prima de firma se reparte entre ellas.', ['weeks' => $weeks])) ?></p>
<?php elseif ($contract): $seasons = (int)$contract['season_to'] - (int)$contract['season_from'] + 1; ?>
    <div class="box sponsor-card">
        <div class="box-head"><?= e(t('Patrocinador principal')) ?></div>
        <div class="box-body sponsor-main">
            <img src="<?= e(SponsorService::logoUrl((int)$contract['company_id'])) ?>" alt="" width="140" height="140">
            <div>
                <h2 style="margin-bottom:2px"><?= e($contract['company']) ?></h2>
                <div class="muted"><?= e(t($contract['sector'])) ?> · <?= $prest((int)$contract['prestige']) ?> · <em>«<?= e(t($contract['slogan'])) ?>»</em></div>
                <table class="kv" style="margin-top:8px;max-width:520px">
                    <tr><th><?= e(t('Tipo de contrato')) ?></th><td><?= e(t(SponsorService::TYPES[$contract['type']][0])) ?></td></tr>
                    <tr><th><?= e(t('Pago semanal')) ?></th><td><?= e(money($contract['weekly_amount'])) ?></td></tr>
                    <?php if ((int)$contract['win_bonus']): ?><tr><th><?= e(t('Prima por victoria')) ?></th><td><?= e(money($contract['win_bonus'])) ?></td></tr><?php endif; ?>
                    <?php if ((int)$contract['title_bonus']): ?><tr><th><?= e(t('Premio por título')) ?></th><td><?= e(money($contract['title_bonus'])) ?></td></tr><?php endif; ?>
                    <tr><th><?= e(t('Vigencia')) ?></th><td><?= e($seasons > 1 ? t('Temporada {from} a {to}', ['from' => (int)$contract['season_from'], 'to' => (int)$contract['season_to']]) : t('Temporada {n}', ['n' => (int)$contract['season_from']])) ?></td></tr>
                    <tr><th><?= e(t('Cobrado hasta ahora')) ?></th><td class="pos"><?= e(money($contract['total_paid'])) ?></td></tr>
                </table>
            </div>
        </div>
        <div class="box-body small muted"><?= e(t('Cuando termine el contrato recibirás nuevas ofertas antes de empezar la temporada siguiente, según la división en la que juegues.')) ?></div>
    </div>
<?php else: ?>
    <div class="alert alert-warning"><?= e(t('Ahora mismo no tienes patrocinador. Recibirás ofertas en la próxima actualización económica.')) ?></div>
<?php endif; ?>

<div class="box">
    <div class="box-head"><?= e(t('Historial de patrocinadores')) ?> <a href="<?= e(url('companies')) ?>"><?= e(t('Ver todas las empresas')) ?> »</a></div>
    <table class="table compact">
        <thead><tr><th><?= e(t('Empresa')) ?></th><th><?= e(t('Rubro')) ?></th><th><?= e(t('Tipo')) ?></th><th><?= e(t('Temporadas')) ?></th><th class="num"><?= e(t('Semanal')) ?></th><th class="num"><?= e(t('Cobrado')) ?></th><th><?= e(t('Estado')) ?></th></tr></thead>
        <?php foreach ($history as $h): ?>
            <tr>
                <td class="nowrap"><img src="<?= e(SponsorService::logoUrl((int)$h['company_id'])) ?>" width="26" height="26" class="avatar" alt=""> <?= e($h['company']) ?></td>
                <td class="small"><?= e(t($h['sector'])) ?></td>
                <td><?= e(t(SponsorService::TYPES[$h['type']][0])) ?></td>
                <td><?= (int)$h['season_from'] ?><?= $h['season_to'] != $h['season_from'] ? '–' . (int)$h['season_to'] : '' ?></td>
                <td class="num"><?= e(money($h['weekly_amount'])) ?></td>
                <td class="num"><?= e(money($h['total_paid'])) ?></td>
                <td><?= $h['status'] === 'active' ? '<span class="tag tag-user">' . e(t('Vigente')) . '</span>' : '<span class="tag">' . e(t('Terminado')) . '</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td colspan="7" class="muted"><?= e(t('Todavía no has tenido patrocinadores.')) ?></td></tr><?php endif; ?>
    </table>
</div>
