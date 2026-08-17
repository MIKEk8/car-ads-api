<?php

declare(strict_types=1);

namespace app;

/**
 * Типизированное чтение переменных окружения — единственное место, где
 * значения из окружения интерпретируются.
 *
 * Прямая идиома `getenv('FLAG') ?: $default` для флагов неработоспособна:
 * строка "0" в PHP ложна, поэтому `?:` подставляет дефолт, а "false" —
 * непустая строка и приводится к true. То есть выключить флаг через
 * окружение невозможно ни одним значением. Отсюда отдельный метод на
 * каждый тип: тип значения задаётся вызовом, а не угадывается по месту.
 */
final class Env
{
    /**
     * Строка. Пустое значение сохраняется как есть: `DB_PASSWORD=` — это
     * осознанно заданный пустой пароль (например, при trust-аутентификации),
     * а не «переменная не задана».
     */
    public static function string(string $name, string $default): string
    {
        $raw = getenv($name);

        return $raw === false ? $default : $raw;
    }

    /**
     * Флаг. Понимает 1/0, true/false, yes/no, on/off в любом регистре;
     * нераспознанное значение считается неуказанным и даёт $default.
     */
    public static function bool(string $name, bool $default): bool
    {
        $raw = getenv($name);
        if ($raw === false) {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** Целое. Нечисловое значение считается неуказанным и даёт $default. */
    public static function int(string $name, int $default): int
    {
        $raw = getenv($name);
        if ($raw === false || !is_numeric($raw)) {
            return $default;
        }

        return (int)$raw;
    }
}
