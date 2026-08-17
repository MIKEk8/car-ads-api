<?php

declare(strict_types=1);

namespace app\tests\unit;

use app\Exception\ValidationException;
use app\Repository\CarRepositoryInterface;
use app\Schema\CarSchema;
use app\Service\CarService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Гейт против целого класса дефектов, а не против отдельных его проявлений.
 *
 * Исходная ошибка: ограничения колонок были объявлены в миграции, а слой
 * Service проверял лишь часть из них. Непроверенное доходило до PostgreSQL и
 * возвращалось клиенту как HTTP 500 вместо 422 — и так было с четырьмя полями
 * сразу. Починить четыре места недостаточно: следующая добавленная колонка
 * снова оказалась бы без проверки.
 *
 * Поэтому здесь два теста. Первый требует, чтобы у каждого ограничения из
 * {@see CarSchema} существовал вход, который сервис отбивает как ошибку
 * валидации. Второй — что каждый такой вход действительно отбивается, причём
 * под ожидаемым именем поля. Новая константа в CarSchema без записи в
 * rejectionCases() валит сборку.
 */
final class CarSchemaCoverageTest extends TestCase
{
    /**
     * Ограничение → [ожидаемое поле ошибки, изменение валидного запроса].
     *
     * @return array<string, array{0: string, 1: array<string,mixed>}>
     */
    public static function rejectionCases(): array
    {
        $tooLong = str_repeat('a', 300);

        return [
            'TITLE_MAX' => ['title', ['title' => $tooLong]],
            'PHOTO_URL_MAX' => ['photo_url', ['photo_url' => 'https://example.com/' . $tooLong]],
            'CONTACTS_MAX' => ['contacts', ['contacts' => $tooLong]],
            'PRICE_PRECISION' => ['price', ['price' => '99999999999999']],
            'PRICE_SCALE' => ['price', ['price' => '100.999']],
            'OPTION_TEXT_MAX' => ['options.brand', ['options' => ['brand' => $tooLong]]],
            'INT32_MAX' => ['options.mileage', ['options' => ['mileage' => '99999999999999']]],
            'YEAR_MIN' => ['options.year', ['options' => ['year' => 1500]]],
            'MILEAGE_MIN' => ['options.mileage', ['options' => ['mileage' => -1]]],
        ];
    }

    public function testEveryConstraintHasRejectionCase(): void
    {
        $declared = array_keys((new ReflectionClass(CarSchema::class))->getConstants());
        $covered = array_keys(self::rejectionCases());

        self::assertSame(
            [],
            array_values(array_diff($declared, $covered)),
            'Ограничение из CarSchema не покрыто входом, который сервис обязан '
            . 'отбить как 422. Пока покрытия нет, такой ввод дойдёт до PostgreSQL '
            . 'и вернётся клиенту пятисоткой — добавьте случай в rejectionCases().',
        );
    }

    /**
     * @param array<string,mixed> $override
     */
    #[DataProvider('rejectionCases')]
    public function testConstraintIsRejectedAsValidationError(string $expectedField, array $override): void
    {
        $repository = $this->createMock(CarRepositoryInterface::class);
        // Ключевая проверка: до хранилища, а значит и до БД, запрос не доходит.
        $repository->expects($this->never())->method('save');

        $service = new CarService($repository);

        try {
            $service->create($this->payloadWith($override));
            self::fail("Ожидалось ValidationException по полю $expectedField");
        } catch (ValidationException $e) {
            self::assertArrayHasKey($expectedField, $e->getErrors());
        }
    }

    /**
     * Валидный запрос с наложенным изменением. Блок options сливается
     * поверх валидного, чтобы менять в нём одно поле за раз.
     *
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function payloadWith(array $override): array
    {
        $payload = [
            'title' => 'BMW X5 2020',
            'description' => 'Отличное состояние',
            'price' => 4500000,
            'photo_url' => 'https://example.com/car.jpg',
            'contacts' => '+7 999 123-45-67',
            'options' => [
                'brand' => 'BMW',
                'model' => 'X5',
                'year' => 2020,
                'body' => 'SUV',
                'mileage' => 35000,
            ],
        ];

        if (isset($override['options'])) {
            $payload['options'] = array_merge($payload['options'], $override['options']);
            unset($override['options']);
        }

        return array_merge($payload, $override);
    }
}
