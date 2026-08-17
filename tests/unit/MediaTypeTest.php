<?php

declare(strict_types=1);

namespace app\tests\unit;

use app\Web\MediaType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Тесты нормализации Content-Type.
 *
 * Класс дефектов, который они закрывают: media type по RFC 7231 §3.1.1.1
 * регистронезависим и допускает пробел перед «;», а Yii ищет парсер тела
 * регистрозависимым `isset()` по строке, обрезанной по «;» без trim. Пока
 * нормализация была только в контроллере, запрос с `APPLICATION/JSON`
 * проходил проверку типа, но парсер для него не находился — тело уходило в
 * `$_POST`, и клиент получал 422 «поле title обязательно» на запрос, где
 * title был передан. Проверяем именно те начертания, на которых это ловилось.
 */
final class MediaTypeTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function headers(): array
    {
        return [
            'канонический' => ['application/json', 'application/json'],
            'с charset' => ['application/json; charset=utf-8', 'application/json'],
            'верхний регистр' => ['APPLICATION/JSON', 'application/json'],
            'смешанный регистр' => ['Application/Json', 'application/json'],
            'регистр и charset' => ['application/JSON; charset=UTF-8', 'application/json'],
            'пробел перед точкой с запятой' => ['application/json ; charset=utf-8', 'application/json'],
            'окружающие пробелы' => ['  application/json  ', 'application/json'],
            'чужой тип' => ['application/x-www-form-urlencoded', 'application/x-www-form-urlencoded'],
            'пустой заголовок' => ['', ''],
            'только пробелы' => ['   ', ''],
        ];
    }

    #[DataProvider('headers')]
    public function testExtractReturnsLowercasedMediaTypeWithoutParameters(
        string $header,
        string $expected,
    ): void {
        self::assertSame($expected, MediaType::extract($header));
    }

    /**
     * Главное свойство: Yii берёт результат getContentType(), режет по «;» и
     * ищет парсер точным совпадением. Значит после нормализации часть до «;»
     * обязана совпадать с ключом `application/json` из request.parsers.
     */
    #[DataProvider('headers')]
    public function testNormalizedHeaderSplitsToCanonicalMediaType(
        string $header,
        string $expected,
    ): void {
        $normalized = MediaType::normalizeHeader($header);
        $beforeSemicolon = explode(';', $normalized, 2)[0];

        self::assertSame($expected, $beforeSemicolon);
    }

    public function testNormalizeHeaderKeepsParametersIntact(): void
    {
        self::assertSame(
            'application/json; charset=UTF-8',
            MediaType::normalizeHeader('Application/JSON ; charset=UTF-8'),
        );
    }

    public function testNormalizeHeaderIsIdempotent(): void
    {
        $once = MediaType::normalizeHeader('APPLICATION/JSON ; charset=utf-8');

        self::assertSame($once, MediaType::normalizeHeader($once));
    }
}
