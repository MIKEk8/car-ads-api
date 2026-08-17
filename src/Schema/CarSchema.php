<?php

declare(strict_types=1);

namespace app\Schema;

/**
 * Ограничения таблиц `car` и `car_option` — единственное описание в проекте.
 *
 * Отсюда их берут и миграции (при создании колонок), и слой Service (при
 * валидации входных данных). До появления этого класса границы жили в двух
 * местах: миграция объявляла varchar(255) и numeric(12,2), а сервис проверял
 * лишь часть из них — всё непроверенное доходило до PostgreSQL и возвращалось
 * клиенту как HTTP 500 вместо 422.
 *
 * Важно: изменение константы меняет и то, что создаст миграция на чистой базе.
 * Поэтому вместе с правкой значения нужно добавлять ALTER-миграцию для уже
 * развёрнутых баз, иначе схемы свежей и обновлённой установки разойдутся.
 *
 * Полноту покрытия сторожит {@see \app\tests\unit\CarSchemaCoverageTest}:
 * каждая константа обязана иметь вход, который сервис отбивает как 422.
 */
final class CarSchema
{
    /** Длины varchar-колонок таблицы `car`. */
    public const TITLE_MAX = 255;
    public const PHOTO_URL_MAX = 255;
    public const CONTACTS_MAX = 255;

    /** numeric(PRICE_PRECISION, PRICE_SCALE) для `car.price`. */
    public const PRICE_PRECISION = 12;
    public const PRICE_SCALE = 2;

    /** Длина varchar-колонок `car_option`: brand, model, body. */
    public const OPTION_TEXT_MAX = 255;

    /** Верхняя граница 4-байтного integer — для `year` и `mileage`. */
    public const INT32_MAX = 2147483647;

    /** Год выпуска: 1885-й — Benz Patent-Motorwagen, первый автомобиль. */
    public const YEAR_MIN = 1885;

    /** Пробег не может быть отрицательным. */
    public const MILEAGE_MIN = 0;

    /**
     * Наибольшая цена, помещающаяся в numeric(PRICE_PRECISION, PRICE_SCALE):
     * для (12,2) это 9999999999.99 — десять целых знаков и два дробных.
     */
    public static function priceMax(): string
    {
        return str_repeat('9', self::PRICE_PRECISION - self::PRICE_SCALE)
            . '.' . str_repeat('9', self::PRICE_SCALE);
    }

    /** Число целых знаков, помещающихся в цену. */
    public static function priceWholeDigits(): int
    {
        return self::PRICE_PRECISION - self::PRICE_SCALE;
    }

    /** Год выпуска не может быть дальше следующего календарного. */
    public static function yearMax(): int
    {
        return (int)date('Y') + 1;
    }
}
