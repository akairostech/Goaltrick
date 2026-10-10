<?php
declare(strict_types=1);

/**
 * Gestión de usuarios: registro, inicio de sesión, "recordarme",
 * protección contra fuerza bruta y recuperación de contraseña.
 */
final class Auth
{
    private static array|false|null $user = null;
    private const COOKIE = 'gt_remember';

    public static function user(): ?array
    {
        if (self::$user === null) {
            self::$user = false;
            $id = $_SESSION['user_id'] ?? null;
            if ($id) {
                $u = DB::row('SELECT * FROM users WHERE id = ?', [$id]);
                if ($u && (int)$u['is_banned']) {
                    $u = Moderation::refresh($u);
                }
                if ($u && !(int)$u['is_banned']) {
                    self::$user = $u;
                } else {
                    unset($_SESSION['user_id']);
                }
            } elseif (!empty($_COOKIE[self::COOKIE])) {
                self::loginFromCookie();
            }
            if (self::$user) {
                // Actualizar la última actividad como máximo una vez por minuto
                $last = self::$user['last_activity_at'] ?? null;
                if (!$last || strtotime((string)$last) < time() - 60) {
                    DB::exec('UPDATE users SET last_activity_at = ? WHERE id = ?', [now(), self::$user['id']]);
                }
            }
        }
        return self::$user ?: null;
    }

    public static function validateRegistration(string $username, string $email, string $password, string $password2, string $teamName): array
    {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_\-\.]{3,30}$/', $username)) {
            $errors[] = t('El nombre de usuario debe tener entre 3 y 30 caracteres (letras, números, _ - .).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = t('El correo electrónico no es válido.');
        }
        if (strlen($password) < 8) {
            $errors[] = t('La contraseña debe tener al menos 8 caracteres.');
        }
        if ($password !== $password2) {
            $errors[] = t('Las contraseñas no coinciden.');
        }
        $tn = trim($teamName);
        if (mb_strlen($tn) < 3 || mb_strlen($tn) > 40) {
            $errors[] = t('El nombre del equipo debe tener entre 3 y 40 caracteres.');
        }
        if (!$errors) {
            if (DB::value('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) {
                $errors[] = t('Ese nombre de usuario ya está en uso.');
            }
            if (DB::value('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
                $errors[] = t('Ya existe una cuenta con ese correo electrónico.');
            }
            if (DB::value('SELECT COUNT(*) FROM teams WHERE name = ?', [$tn])) {
                $errors[] = t('Ya existe un equipo con ese nombre.');
            }
        }
        return $errors;
    }

    /**
     * Crea el usuario y le asigna un equipo libre (controlado por la máquina)
     * de la división más baja. Devuelve el id del usuario.
     */
    public static function register(string $username, string $email, string $password, string $teamName, bool $admin = false, ?int $countryId = null): int
    {
        return DB::transaction(function () use ($username, $email, $password, $teamName, $admin, $countryId) {
            $teamId = TeamService::findFreeTeam($countryId) ?? ($countryId ? null : TeamService::findFreeTeam());
            if (!$teamId) {
                throw new RuntimeException(t('No quedan equipos libres en ese país. Elige otro país o pide al administrador que amplíe las divisiones.'));
            }
            $userId = DB::insert('users', [
                'username'      => $username,
                'email'         => strtolower($email),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'is_admin'      => $admin ? 1 : 0,
                'created_at'    => now(),
            ]);
            TeamService::assignToUser($teamId, $userId, trim($teamName));
            return $userId;
        });
    }

    public static function tooManyAttempts(string $ip): bool
    {
        $max = Config::int('security', 'max_login_attempts', 5);
        $mins = Config::int('security', 'lockout_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - $mins * 60);
        $n = (int)DB::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND attempted_at > ?', [$ip, $since]);
        return $n >= $max;
    }

    /** Devuelve null si va bien, o un mensaje de error. */
    public static function attempt(string $login, string $password, bool $remember): ?string
    {
        $ip = client_ip();
        // Limpieza de intentos antiguos
        if (mt_rand(1, 50) === 1) {
            DB::exec('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
        if (self::tooManyAttempts($ip)) {
            return t('Demasiados intentos fallidos. Espera {n} minutos.', ['n' => Config::int('security', 'lockout_minutes', 15)]);
        }
        $u = DB::row('SELECT * FROM users WHERE username = ? OR email = ?', [$login, strtolower($login)]);
        $ok = $u && password_verify($password, $u['password_hash']);
        DB::insert('login_attempts', ['ip' => $ip, 'username' => mb_substr($login, 0, 190), 'success' => $ok ? 1 : 0, 'attempted_at' => now()]);
        if (!$ok) {
            return t('Usuario o contraseña incorrectos.');
        }
        if ((int)$u['is_banned']) {
            $u = Moderation::refresh($u);
            if ((int)$u['is_banned']) {
                return Moderation::bannedMessage($u);
            }
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        self::loginUser((int)$u['id']);
        if ($remember) {
            self::issueRememberToken((int)$u['id']);
        }
        return null;
    }

    public static function loginUser(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        DB::exec('UPDATE users SET last_login_at = ?, last_activity_at = ? WHERE id = ?', [now(), now(), $userId]);
        Moderation::recordLogin($userId);
        self::$user = null;
    }

    public static function logout(): void
    {
        if (!empty($_COOKIE[self::COOKIE])) {
            [$selector] = explode(':', (string)$_COOKIE[self::COOKIE]) + [''];
            DB::exec('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
            setcookie(self::COOKIE, '', time() - 3600, '/');
        }
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = false;
    }

    private static function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $days = Config::int('app', 'remember_days', 30);
        $expires = time() + $days * 86400;
        DB::insert('remember_tokens', [
            'user_id'    => $userId,
            'selector'   => $selector,
            'token_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', $expires),
        ]);
        setcookie(self::COOKIE, $selector . ':' . $validator, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }

    private static function loginFromCookie(): void
    {
        $parts = explode(':', (string)$_COOKIE[self::COOKIE]);
        if (count($parts) !== 2) {
            return;
        }
        [$selector, $validator] = $parts;
        $row = DB::row('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > ?', [$selector, now()]);
        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $validator))) {
            setcookie(self::COOKIE, '', time() - 3600, '/');
            return;
        }
        $u = DB::row('SELECT * FROM users WHERE id = ?', [$row['user_id']]);
        if ($u && (int)$u['is_banned']) {
            $u = Moderation::refresh($u);
        }
        if (!$u || (int)$u['is_banned']) {
            return;
        }
        // Rotar el token
        DB::exec('DELETE FROM remember_tokens WHERE id = ?', [$row['id']]);
        self::loginUser((int)$u['id']);
        self::issueRememberToken((int)$u['id']);
        self::$user = $u;
    }

    public static function changePassword(int $userId, string $current, string $new, string $new2): ?string
    {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$u || !password_verify($current, $u['password_hash'])) {
            return t('La contraseña actual no es correcta.');
        }
        if (strlen($new) < 8) {
            return t('La nueva contraseña debe tener al menos 8 caracteres.');
        }
        if ($new !== $new2) {
            return t('Las contraseñas nuevas no coinciden.');
        }
        DB::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $userId]);
        DB::exec('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
        return null;
    }

    /** Genera un token de recuperación. Devuelve el token en claro (para enviar por correo / mostrar). */
    public static function createResetToken(string $email): ?string
    {
        $u = DB::row('SELECT id FROM users WHERE email = ?', [strtolower($email)]);
        if (!$u) {
            return null;
        }
        $token = bin2hex(random_bytes(20));
        DB::exec('UPDATE users SET reset_token_hash = ?, reset_expires_at = ? WHERE id = ?', [
            hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600), $u['id'],
        ]);
        return $token;
    }

    public static function resetPassword(string $token, string $new, string $new2): ?string
    {
        if (strlen($new) < 8) {
            return t('La contraseña debe tener al menos 8 caracteres.');
        }
        if ($new !== $new2) {
            return t('Las contraseñas no coinciden.');
        }
        $u = DB::row('SELECT id FROM users WHERE reset_token_hash = ? AND reset_expires_at > ?', [hash('sha256', $token), now()]);
        if (!$u) {
            return t('El enlace de recuperación no es válido o ha caducado.');
        }
        DB::exec('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL WHERE id = ?', [
            password_hash($new, PASSWORD_DEFAULT), $u['id'],
        ]);
        DB::exec('DELETE FROM remember_tokens WHERE user_id = ?', [$u['id']]);
        return null;
    }
}
