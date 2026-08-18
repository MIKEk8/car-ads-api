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

    public function testCreateNormalizesDecimalPriceFromString(): void
    {
        $payload = $this->validPayload();
        $payload['price'] = '1250000.5';

        $repo = $this->createMock(CarRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->willReturnArgument(0);

        $car = $this->makeService($repo)->create($payload);

        // Дробная часть дополняется до scale колонки, а не пересчитывается.
        self::assertSame('1250000.50', $car->price);
    }

    public function testCreateRejectsPriceWithExtraDecimalsInsteadOfRounding(): void
    {
        $payload = $this->validPayload();
        $payload['price'] = '100.999';

        try {
            $this->makeService()->create($payload);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            // Молча округлить сумму хуже, чем отказать: клиент должен узнать,
            // что переданное значение не может быть сохранено как есть.
            self::assertArrayHasKey('price', $e->getErrors());
        }
    }

    public function testCreateRejectsExponentialPrice(): void
    {
        $payload = $this->validPayload();
        $payload['price'] = '1e6';

        $this->expectException(ValidationException::class);
        $this->makeService()->create($payload);
    }

    public function testCreateRejectsYearBeyondNextCalendarYear(): void
    {
        $payload = $this->validPayload();
        $payload['options']['year'] = (int)date('Y') + 5;

        try {
            $this->makeService()->create($payload);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('options.year', $e->getErrors());
        }
    }

    public function testCreateRejectsNestedArrayInOptionField(): void
    {
        $payload = $this->validPayload();
        $payload['options']['brand'] = ['BMW'];

        try {
            $this->makeService()->create($payload);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            // Иначе массив дошёл бы до приведения (string) и стал строкой «Array».
            self::assertArrayHasKey('options.brand', $e->getErrors());
        }
    }

    /**
     * Регрессия, найденная сравнением поведения до и после выделения формы.
     *
     * Пустой объект options создавал объявление без характеристик и отвечал
     * 201 вместо 422. Причина общая для всего класса: у inline-валидаторов Yii
     * `skipOnEmpty` по умолчанию true, а пустой массив считается пустым
     * значением — правило просто не вызывалось. Тест держит `skipOnEmpty` на
     * месте: без него снова пропускался бы объект без единого поля.
     */
    public function testCreateWithEmptyOptionsObjectRequiresEveryField(): void
    {
        $payload = $this->validPayload();
        $payload['options'] = [];

        $repo = $this->createMock(CarRepositoryInterface::class);
        $repo->expects($this->never())->method('save');

        try {
            $this->makeService($repo)->create($payload);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(
                ['options.brand', 'options.model', 'options.year', 'options.body', 'options.mileage'],
                array_keys($e->getErrors()),
            );
        }
    }

    /**
     * Регрессия оттуда же: {attribute} подставляет метку атрибута, а Yii
     * генерирует её из имени свойства — сообщения превращались в «Поле Title
     * обязательно». Клиенту сообщают о поле JSON, значит и называть его надо
     * так, как оно передаётся. Тест сторожит attributeLabels() в обеих формах.
     */
    public function testValidationMessagesNameFieldsAsTheRequestDoes(): void
    {
        try {
            $this->makeService()->create(['options' => []]);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            self::assertStringContainsString('Поле title ', $errors['title']);
            self::assertStringContainsString('Поле contacts ', $errors['contacts']);
            self::assertStringContainsString('Поле options.brand ', $errors['options.brand']);
        }
    }

    /**
     * И третья: пустая строка в photo_url — это не «фотографии нет».
     * Различие видно в базе (пустая строка против NULL), поэтому форма не
     * вправе подменять одно другим по дороге.
     */
    public function testCreateKeepsEmptyPhotoUrlDistinctFromMissingOne(): void
    {
        $repo = $this->createMock(CarRepositoryInterface::class);
        $repo->expects($this->exactly(2))->method('save')->willReturnArgument(0);
        $service = $this->makeService($repo);

        $withEmpty = $this->validPayload();
        $withEmpty['photo_url'] = '';
        self::assertSame('', $service->create($withEmpty)->photoUrl);

        $without = $this->validPayload();
        unset($without['photo_url']);
        self::assertNull($service->create($without)->photoUrl);
    }

    public function testCreateCollectsAllErrorsAtOnce(): void
    {
        try {
            $this->makeService()->create(['options' => ['year' => 'нет']]);
            self::fail('Ожидалось ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            // Валидация не должна прерываться на первом промахе.
            self::assertArrayHasKey('title', $errors);
            self::assertArrayHasKey('price', $errors);
            self::assertArrayHasKey('contacts', $errors);
            self::assertArrayHasKey('options.brand', $errors);
            self::assertArrayHasKey('options.year', $errors);
        }
    }
}
