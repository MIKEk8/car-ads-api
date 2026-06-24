<?php

declare(strict_types=1);

namespace app\Repository;

use app\Entity\Car;
use app\Mapper\CarMapper;
use app\Mapper\CarOptionMapper;
use yii\db\Connection;

/**
 * Реализация хранилища объявлений поверх DataMapper'ов.
 *
 * Отвечает за согласованность агрегата Car + CarOption (транзакции)
 * и за сборку сущности из нескольких таблиц.
 */
final class CarRepository implements CarRepositoryInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly CarMapper $carMapper,
        private readonly CarOptionMapper $optionMapper,
    ) {
    }

    public function save(Car $car): Car
    {
        $transaction = $this->db->beginTransaction();
        try {
            $this->carMapper->insert($car);

            if ($car->option !== null) {
                $car->option->carId = $car->id;
                $this->optionMapper->insert($car->option);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $car;
    }

    public function getById(int $id): ?Car
    {
        $car = $this->carMapper->find($id);
        if ($car === null) {
            return null;
        }

        $car->option = $this->optionMapper->findByCarId($car->id);

        return $car;
    }

    public function paginate(int $page, int $perPage): array
    {
        $total = $this->carMapper->count();
        $cars = $this->carMapper->findAll($perPage, ($page - 1) * $perPage);

        $ids = array_map(static fn (Car $car): int => (int)$car->id, $cars);
        $options = $this->optionMapper->findByCarIds($ids);

        foreach ($cars as $car) {
            $car->option = $options[$car->id] ?? null;
        }

        return ['items' => $cars, 'total' => $total];
    }
}
