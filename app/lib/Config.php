<?php
declare(strict_types=1);

/**
 * Lee config.ini y ofrece acceso tipado a sus valores.
 */
final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException("No se encontró el archivo de configuración: $file");
        }
        $parsed = parse_ini_file($file, true, INI_SCANNER_TYPED);
        if ($parsed === false) {
            throw new RuntimeException("config.ini tiene errores de sintaxis");
        }
        self::$data = $parsed;
    }

    public static function get(string $section, string $key, mixed $default = null): mixed
    {
        return self::$data[$section][$key] ?? $default;
    }

    public static function int(string $section, string $key, int $default = 0): int
    {
        return (int)self::get($section, $key, $default);
    }

    public static function float(string $section, string $key, float $default = 0.0): float
    {
        return (float)self::get($section, $key, $default);
    }

    public static function bool(string $section, string $key, bool $default = false): bool
    {
        $v = self::get($section, $key, $default);
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
    }

    public static function section(string $section): array
    {
        return self::$data[$section] ?? [];
    }
}
