<?php
declare(strict_types=1);

/** Estado global del juego guardado en la tabla game_state */
final class GameState
{
    private static array $cache = [];

    public static function get(string $k, ?string $default = null): ?string
    {
        if (!array_key_exists($k, self::$cache)) {
            self::$cache[$k] = DB::value('SELECT v FROM game_state WHERE k = ?', [$k]);
        }
        return self::$cache[$k] ?? $default;
    }

    public static function set(string $k, string|int|float $v): void
    {
        DB::exec('INSERT INTO game_state (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, (string)$v]);
        self::$cache[$k] = (string)$v;
    }

    public static function season(): int
    {
        return (int)self::get('season', '1');
    }

    public static function week(): int
    {
        return (int)self::get('week', '1');
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
