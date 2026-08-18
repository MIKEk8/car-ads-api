<?php

declare(strict_types=1);

namespace app\Entity;

/**
 * Доменная сущность «Технические характеристики объявления».
 *
 * Связана с {@see Car} как has-one. Если характеристики добавляются,
 * все поля обязательны (см. проверку в {@see \app\Form\CarOptionForm}).
 */
final class CarOption
{
    public ?int $id = null;
    public ?int $carId = null;
    public string $brand;
    public string $model;
    public int $year;
    public string $body;
    public int $mileage;

    public function __construct(
        string $brand,
        string $model,
        int $year,
        string $body,
        int $mileage,
    ) {
        $this->brand = $brand;
        $this->model = $model;
        $this->year = $year;
        $this->body = $body;
        $this->mileage = $mileage;
    }
}
