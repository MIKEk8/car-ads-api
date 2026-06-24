<?php

declare(strict_types=1);

namespace app\tests\unit;

use app\Entity\Car;
use app\Exception\ValidationException;
use app\Repository\CarRepositoryInterface;
use app\Service\CarService;
use PHPUnit\Framework\TestCase;

/**
 * Unit-тесты метода создания объявления в слое Service.
 * Репозиторий замокан — тест не обращается к БД.
 */
final class CarServiceTest extends TestCase
{
    private function makeService(?CarRepositoryInterface $repo = null): CarService
    {
        $repo ??= $this->createStub(CarRepositoryInterface::class);
        return new CarService($repo);
    }

    private function validPayload(): array
    {
        return [
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
    }

    public function testCreateWithOptionsPersistsAndReturnsEntity(): void
    {
        $repo = $this->createMock(CarRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Car::class))
            ->willReturnCallback(static function (Car $car): Car {
                $car->id = 42;
                $car->createdAt = '2026-06-24 10:00:00';
                return $car;
            });

        $car = $this->makeService($repo)->create($this->validPayload());

        self::assertSame(42, $car->id);
        self::assertSame('BMW X5 2020', $car->title);
        self::assertSame('4500000.00', $car->price);
        self::assertNotNull($car->option);
        self::assertSame('BMW', $car->option->brand);
        self::assertSame(2020, $car->option->year);
        self::assertSame(35000, $car->option->mileage);
    }

    public function testCreateWithoutOptionsKeepsOptionNull(): void
    {
        $payload = $this->validPayload();
        unset($payload['options']);

        $repo = $this->createMock(CarRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('save')
            ->willReturnArgument(0);

        $car = $this->makeService($repo)->create($payload);

        self::assertNull($car->option);
    }

    public function testCreateWithNullOptionsKeepsOptionNull(): void
    {
        $payload = $this->validPayload();
        $payload['options'] = null;

        $repo = $this->createMock(CarRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->willReturnArgument(0);

        $car = $this->makeService($repo)->create($payload);

        self::assertNull($car->option);
    }

    public function testCreateMissingTitleThrowsValidation(): void
    {
        $payload = $this->validPayload();
        unset($payload['title']);

        $repo = $this->createMock(CarRepositoryInterface::class);
        $repo->expects($this->never())->method('save');

        try {
            $this->makeService($repo)->create($payload);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('title', $e->getErrors());
        }
    }

    public function testCreateNegativePriceThrowsValidation(): void
    {
        $payload = $this->validPayload();
        $payload['price'] = -100;

        $this->expectException(ValidationException::class);
        $this->makeService()->create($payload);
    }

    public function testCreatePartialOptionsThrowsValidation(): void
    {
        $payload = $this->validPayload();
        unset($payload['options']['mileage']);

        try {
            $this->makeService()->create($payload);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('options.mileage', $e->getErrors());
        }
    }

    public function testCreateNonIntegerYearThrowsValidation(): void
    {
        $payload = $this->validPayload();
        $payload['options']['year'] = 'двадцать двадцать';

        $this->expectException(ValidationException::class);
        $this->makeService()->create($payload);
    }
}
