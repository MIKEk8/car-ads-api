<?php

declare(strict_types=1);

namespace app\Repository;

use app\Entity\Car;

/**
 * Контракт хранилища объявлений. Слой Service зависит от этой абстракции,
 * а не от конкретной реализации (DIP), что позволяет подменять её в тестах.
 */
interface CarRepositoryInterface
{
    /**
     * Сохраняет новое объявление вместе с характеристиками (если заданы)
     * в одной транзакции и возвращает сущность с заполненными id/created_at.
     */
    public function save(Car $car): Car;

    /** Возвращает объявление с характеристиками или null, если не найдено. */
    public function getById(int $id): ?Car;

    /**
     * Постраничная выборка объявлений.
     *
     * @return array{items: Car[], total: int}
     */
    public function paginate(int $page, int $perPage): array;
}
