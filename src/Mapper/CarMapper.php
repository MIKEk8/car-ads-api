<?php

declare(strict_types=1);

namespace app\Mapper;

use app\Entity\Car;
use yii\db\Connection;

/**
 * DataMapper для сущности {@see Car}.
 *
 * Изолирует доменную сущность от деталей хранения: переносит данные между
 * объектом Car и таблицей `car` через Yii2 DAO (без ActiveRecord).
 */
final class CarMapper
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Вставляет новое объявление и заполняет сгенерированные БД поля
     * (id, created_at) обратно в сущность.
     */
    public function insert(Car $car): void
    {
        $sql = <<<SQL
            INSERT INTO car (title, description, price, photo_url, contacts, created_at)
            VALUES (:title, :description, :price, :photo_url, :contacts, CURRENT_TIMESTAMP)
            RETURNING id, created_at
        SQL;

        $row = $this->db->createCommand($sql, [
            ':title' => $car->title,
            ':description' => $car->description,
            ':price' => $car->price,
            ':photo_url' => $car->photoUrl,
            ':contacts' => $car->contacts,
        ])->queryOne();

        $car->id = (int)$row['id'];
        $car->createdAt = (string)$row['created_at'];
    }

    public function find(int $id): ?Car
    {
        $row = $this->db
            ->createCommand('SELECT * FROM car WHERE id = :id', [':id' => $id])
            ->queryOne();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return Car[] Объявления, отсортированные по убыванию id (новые сверху).
     */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->db->createCommand(
            'SELECT * FROM car ORDER BY id DESC LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset],
        )->queryAll();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function count(): int
    {
        return (int)$this->db->createCommand('SELECT COUNT(*) FROM car')->queryScalar();
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Car
    {
        $car = new Car(
            (string)$row['title'],
            (string)$row['description'],
            (string)$row['price'],
            $row['photo_url'] !== null ? (string)$row['photo_url'] : null,
            (string)$row['contacts'],
        );
        $car->id = (int)$row['id'];
        $car->createdAt = (string)$row['created_at'];

        return $car;
    }
}
