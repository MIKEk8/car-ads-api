<?php

declare(strict_types=1);

namespace app\Mapper;

use app\Entity\CarOption;
use yii\db\Connection;

/**
 * DataMapper для сущности {@see CarOption} (таблица `car_option`).
 */
final class CarOptionMapper
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(CarOption $option): void
    {
        $sql = <<<SQL
            INSERT INTO car_option (car_id, brand, model, year, body, mileage)
            VALUES (:car_id, :brand, :model, :year, :body, :mileage)
            RETURNING id
        SQL;

        $id = $this->db->createCommand($sql, [
            ':car_id' => $option->carId,
            ':brand' => $option->brand,
            ':model' => $option->model,
            ':year' => $option->year,
            ':body' => $option->body,
            ':mileage' => $option->mileage,
        ])->queryScalar();

        $option->id = (int)$id;
    }

    public function findByCarId(int $carId): ?CarOption
    {
        $row = $this->db
            ->createCommand('SELECT * FROM car_option WHERE car_id = :car_id', [':car_id' => $carId])
            ->queryOne();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Загружает характеристики для набора объявлений одним запросом
     * (защита от проблемы N+1 в списках).
     *
     * @param int[] $carIds
     * @return array<int,CarOption> Карта carId => CarOption.
     */
    public function findByCarIds(array $carIds): array
    {
        if ($carIds === []) {
            return [];
        }

        $rows = $this->db
            ->createCommand('SELECT * FROM car_option WHERE car_id IN (' . implode(',', array_map('intval', $carIds)) . ')')
            ->queryAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['car_id']] = $this->hydrate($row);
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): CarOption
    {
        $option = new CarOption(
            (string)$row['brand'],
            (string)$row['model'],
            (int)$row['year'],
            (string)$row['body'],
            (int)$row['mileage'],
        );
        $option->id = (int)$row['id'];
        $option->carId = (int)$row['car_id'];

        return $option;
    }
}
