<?php

declare(strict_types=1);

namespace app\Web;

/**
 * Нормализация значения заголовка `Content-Type`.
 *
 * По RFC 7231 §3.1.1.1 media type регистронезависим, а перед `;` допустим
 * необязательный пробел. Yii с этим не считается: {@see \yii\web\Request::getBodyParams()}
 * режет заголовок по `;` без trim и ищет парсер регистрозависимым `isset()`.
 * Промах уводит разбор в `$_POST`, то есть JSON-тело молча теряется целиком.
 *
 * Поэтому нормализация живёт здесь, в одном месте, и её используют оба
 * потребителя: подбор парсера (через {@see Request}) и проверка типа в
 * контроллере. Пока значение приводилось к нижнему регистру только в
 * контроллере, `Content-Type: APPLICATION/JSON` проходил проверку, но не
 * находил парсер — и клиент получал 422 «поле title обязательно» на запрос,
 * где title был передан.
 */
final class MediaType
{
    /**
     * Приводит заголовок к виду, пригодному и для сравнения, и для поиска
     * парсера: media type — в нижнем регистре и без окружающих пробелов,
     * параметры (`charset` и прочие) сохраняются без изменений.
     */
    public static function normalizeHeader(string $header): string
    {
        if (trim($header) === '') {
            return '';
        }

        $parts = explode(';', $header, 2);
        $mediaType = self::extract($header);

        return isset($parts[1]) ? $mediaType . ';' . $parts[1] : $mediaType;
    }

    /** Только media type, без параметров: `application/json`. */
    public static function extract(string $header): string
    {
        return strtolower(trim(explode(';', $header, 2)[0]));
    }
}
