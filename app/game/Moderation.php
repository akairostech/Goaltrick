<?php
declare(strict_types=1);

/**
 * Moderación: sanciones a usuarios (aviso, suspensión temporal, expulsión), historial de accesos
 * para detectar multicuentas y tickets de ayuda y soporte.
 */
final class Moderation
{
    public const ACTIONS = [
        'warn'    => ['Aviso', '⚠️'],
        'suspend' => ['Suspensión temporal', '⏸'],
        'expel'   => ['Expulsión permanente', '⛔'],
        'unban'   => ['Readmisión', '✅'],
    ];

    public const CATEGORIES = [
        'bug'        => ['Problema o error del juego', '🐞'],
        'report'     => ['Reportar a un usuario', '🚩'],
        'account'    => ['Mi cuenta', '👤'],
        'transfer'   => ['Mercado de fichajes', '🔁'],
        'suggestion' => ['Sugerencia', '💡'],
        'other'      => ['Otro', '❓'],
    ];

    public const STATUSES = [
        'open'     => ['Abierto', 'tag-red'],
        'pending'  => ['Esperando al usuario', ''],
        'answered' => ['Respondido', 'tag-user'],
        'closed'   => ['Cerrado', 'tag-bot'],
    ];

    /* ------------------------------------------------------------------
     *  Sanciones
     * ------------------------------------------------------------------ */

    /**
     * Aplica una sanción. $days sólo para 'suspend'. Con $releaseTeam el equipo del usuario pasa a la máquina.
     */
    public static function sanction(int $userId, int $adminId, string $action, string $reason, ?int $days = null, bool $releaseTeam = false, ?int $ticketId = null): ?string
    {
        if (!isset(self::ACTIONS[$action])) {
            return t('Acción no válida.');
        }
        $u = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$u) {
            return t('Usuario no encontrado.');
        }
        if ($userId === $adminId) {
            return t('No puedes sancionarte a ti mismo.');
        }
        $reason = trim($reason);
        if ($action !== 'unban' && mb_strlen($reason) < 5) {
            return t('Escribe el motivo de la sanción (al menos 5 caracteres).');
        }
        $until = null;
        if ($action === 'suspend') {
            $days = max(1, min(365, (int)$days));
            $until = date('Y-m-d H:i:s', time() + $days * 86400);
        }
        DB::transaction(function () use ($u, $userId, $adminId, $action, $reason, $until, $releaseTeam, $ticketId) {
            DB::insert('user_sanctions', [
                'user_id' => $userId, 'admin_id' => $adminId, 'action' => $action, 'reason' => mb_substr($reason ?: '-', 0, 500),
                'until_at' => $until, 'ticket_id' => $ticketId, 'created_at' => now(),
            ]);
            if ($action === 'suspend' || $action === 'expel') {
                DB::update('users', ['is_banned' => 1, 'ban_reason' => mb_substr($reason, 0, 500), 'banned_until' => $until, 'banned_at' => now()], 'id = ?', [$userId]);
                DB::exec('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
                // Sus jugadores en venta y sus pujas se anulan para no dejar operaciones a medias
                $tid = (int)DB::value('SELECT id FROM teams WHERE user_id = ?', [$userId]);
                if ($tid) {
                    DB::exec("UPDATE transfer_listings SET status = 'cancelled', closed_at = ? WHERE seller_team_id = ? AND status = 'active'", [now(), $tid]);
                    self::cancelBids($tid);
                    if ($releaseTeam) {
                        TeamService::release($tid);
                    }
                }
            } elseif ($action === 'unban') {
                DB::update('users', ['is_banned' => 0, 'ban_reason' => null, 'banned_until' => null, 'banned_at' => null], 'id = ?', [$userId]);
            }
            // El usuario recibe un mensaje (lo leerá al volver a entrar si estaba suspendido)
            $subject = match ($action) {
                'warn' => tx('⚠️ Aviso de la administración'),
                'suspend' => tx('⏸ Tu cuenta ha sido suspendida temporalmente'),
                'expel' => tx('⛔ Tu cuenta ha sido expulsada del juego'),
                default => tx('✅ Tu cuenta vuelve a estar activa'),
            };
            $body = $action === 'unban'
                ? tx('La administración ha levantado la sanción de tu cuenta. ¡Bienvenido de nuevo! Recuerda respetar las normas del juego.')
                : tx("Motivo: {reason}\n\nSi crees que se trata de un error puedes responder a través de «Ayuda y soporte».", ['reason' => $reason]);
            DB::insert('messages', ['from_user_id' => null, 'to_user_id' => $userId, 'subject' => $subject, 'body' => $body, 'created_at' => now()]);
        });
        return null;
    }

    /** Retira las pujas activas de un equipo (devuelve la puja más alta al anterior pujador si hace falta) */
    private static function cancelBids(int $teamId): void
    {
        $listings = DB::all("SELECT id FROM transfer_listings WHERE status = 'active' AND highest_bidder_id = ?", [$teamId]);
        foreach ($listings as $l) {
            DB::exec('DELETE FROM transfer_bids WHERE listing_id = ? AND team_id = ?', [$l['id'], $teamId]);
            $best = DB::row('SELECT team_id, amount FROM transfer_bids WHERE listing_id = ? ORDER BY amount DESC, id LIMIT 1', [$l['id']]);
            DB::update('transfer_listings', ['highest_bid' => $best['amount'] ?? null, 'highest_bidder_id' => $best['team_id'] ?? null], 'id = ?', [$l['id']]);
        }
    }

    /** Si la suspensión de un usuario ya ha terminado, se levanta. Devuelve el usuario actualizado. */
    public static function refresh(array $u): array
    {
        if ((int)$u['is_banned'] && !empty($u['banned_until']) && strtotime((string)$u['banned_until']) <= time()) {
            DB::update('users', ['is_banned' => 0, 'ban_reason' => null, 'banned_until' => null, 'banned_at' => null], 'id = ?', [$u['id']]);
            DB::insert('user_sanctions', ['user_id' => $u['id'], 'admin_id' => null, 'action' => 'unban', 'reason' => tx('Fin de la suspensión'), 'created_at' => now()]);
            $u['is_banned'] = 0;
            $u['banned_until'] = null;
        }
        return $u;
    }

    /** Mensaje que ve un usuario sancionado al intentar entrar */
    public static function bannedMessage(array $u): string
    {
        $msg = $u['banned_until']
            ? t('Tu cuenta está suspendida hasta el {date}.', ['date' => fdate($u['banned_until'])])
            : t('Tu cuenta ha sido expulsada del juego.');
        if (!empty($u['ban_reason'])) {
            $msg .= ' ' . t('Motivo: {reason}', ['reason' => $u['ban_reason']]);
        }
        return $msg;
    }

    /* ------------------------------------------------------------------
     *  Accesos
     * ------------------------------------------------------------------ */

    public static function recordLogin(int $userId): void
    {
        try {
            DB::insert('user_logins', ['user_id' => $userId, 'ip' => mb_substr(client_ip(), 0, 45),
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null, 'created_at' => now()]);
            if (mt_rand(1, 50) === 1) {
                DB::exec('DELETE FROM user_logins WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 180 * 86400)]);
            }
        } catch (Throwable $e) {
            // La tabla puede no existir todavía durante una actualización
        }
    }

    /** Otros usuarios que han entrado desde alguna de las mismas IP */
    public static function sharedIps(int $userId): array
    {
        return DB::all(
            'SELECT u.id, u.username, u.is_banned, COUNT(DISTINCT o.ip) AS ips, GROUP_CONCAT(DISTINCT o.ip ORDER BY o.ip SEPARATOR \', \') AS list, MAX(o.created_at) AS last_seen
             FROM user_logins mine JOIN user_logins o ON o.ip = mine.ip AND o.user_id <> mine.user_id JOIN users u ON u.id = o.user_id
             WHERE mine.user_id = ? GROUP BY u.id, u.username, u.is_banned ORDER BY ips DESC LIMIT 30',
            [$userId]
        );
    }

    /* ------------------------------------------------------------------
     *  Tickets
     * ------------------------------------------------------------------ */

    public static function openTicketsCount(): int
    {
        try {
            return (int)DB::value("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'");
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Tickets del usuario con respuestas que aún no ha leído */
    public static function unreadForUser(int $userId): int
    {
        try {
            return (int)DB::value("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status IN ('answered', 'pending') AND (user_read_at IS NULL OR user_read_at < updated_at)", [$userId]);
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function createTicket(array $user, string $category, string $subject, string $body, ?string $reported, ?string $link): array
    {
        $category = isset(self::CATEGORIES[$category]) ? $category : 'other';
        $subject = trim($subject);
        $body = trim($body);
        if (mb_strlen($subject) < 4 || mb_strlen($subject) > 150) {
            return [null, t('El asunto debe tener entre 4 y 150 caracteres.')];
        }
        if (mb_strlen($body) < 15) {
            return [null, t('Explica el problema con algo más de detalle (al menos 15 caracteres).')];
        }
        if (mb_strlen($body) > 5000) {
            return [null, t('El mensaje es demasiado largo (máximo 5000 caracteres).')];
        }
        $openN = (int)DB::value("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status <> 'closed'", [$user['id']]);
        if ($openN >= 5) {
            return [null, t('Tienes demasiados tickets abiertos. Espera a que respondamos a los anteriores.')];
        }
        $recent = (int)DB::value('SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND created_at > ?', [$user['id'], date('Y-m-d H:i:s', time() - 120)]);
        if ($recent) {
            return [null, t('Espera un par de minutos antes de abrir otro ticket.')];
        }
        $reportedId = null;
        if ($category === 'report') {
            $r = DB::row('SELECT id FROM users WHERE username = ?', [trim((string)$reported)]);
            if (!$r) {
                return [null, t('No existe ningún usuario con ese nombre.')];
            }
            if ((int)$r['id'] === (int)$user['id']) {
                return [null, t('No puedes reportarte a ti mismo.')];
            }
            $reportedId = (int)$r['id'];
        }
        $link = trim((string)$link);
        if ($link !== '' && !preg_match('#^https?://#i', $link) && !str_starts_with($link, 'index.php')) {
            $link = '';
        }
        $id = DB::transaction(function () use ($user, $category, $subject, $body, $reportedId, $link) {
            $id = DB::insert('support_tickets', [
                'user_id' => $user['id'], 'category' => $category, 'subject' => $subject, 'status' => 'open',
                'reported_user_id' => $reportedId, 'link' => $link !== '' ? mb_substr($link, 0, 255) : null,
                'created_at' => now(), 'updated_at' => now(), 'user_read_at' => now(),
            ]);
            DB::insert('support_replies', ['ticket_id' => $id, 'user_id' => $user['id'], 'is_staff' => 0, 'body' => $body, 'created_at' => now()]);
            return $id;
        });
        return [$id, null];
    }

    /** Respuesta en un ticket. $staff = respuesta de la administración; $internal = nota que sólo ven los administradores */
    public static function reply(int $ticketId, array $user, string $body, bool $staff, bool $internal = false, ?string $newStatus = null): ?string
    {
        $tk = DB::row('SELECT * FROM support_tickets WHERE id = ?', [$ticketId]);
        if (!$tk) {
            return t('Ticket no encontrado.');
        }
        $body = trim($body);
        if ($body === '' && $newStatus === null) {
            return t('Escribe una respuesta.');
        }
        if (!$staff && $tk['status'] === 'closed') {
            return t('Este ticket está cerrado. Abre uno nuevo si necesitas más ayuda.');
        }
        DB::transaction(function () use ($tk, $user, $body, $staff, $internal, $newStatus) {
            if ($body !== '') {
                DB::insert('support_replies', ['ticket_id' => $tk['id'], 'user_id' => $user['id'], 'is_staff' => $staff ? 1 : 0,
                    'is_internal' => $internal ? 1 : 0, 'body' => mb_substr($body, 0, 5000), 'created_at' => now()]);
            }
            $status = $newStatus ?? ($internal ? $tk['status'] : ($staff ? 'answered' : 'open'));
            $upd = ['status' => $status];
            if (!$internal) {
                $upd['updated_at'] = now();
            }
            if ($staff) {
                $upd['staff_read_at'] = now();
                if (!$tk['assigned_to']) {
                    $upd['assigned_to'] = $user['id'];
                }
            } else {
                $upd['user_read_at'] = now();
            }
            DB::update('support_tickets', $upd, 'id = ?', [$tk['id']]);
            if ($staff && !$internal && $body !== '') {
                DB::insert('messages', ['from_user_id' => null, 'to_user_id' => $tk['user_id'],
                    'subject' => tx('💬 Respuesta a tu ticket #{id}', ['id' => $tk['id']]),
                    'body' => tx("La administración ha respondido a tu ticket «{subject}». Puedes verlo en «Ayuda y soporte».", ['subject' => $tk['subject']]),
                    'created_at' => now()]);
            }
        });
        return null;
    }
}
