<?php

declare(strict_types=1);

namespace app\Entity;

/**
 * Доменная сущность «Объявление об автомобиле».
 *
 * Чистый PHP-объект (POPO) без знания о способе хранения — за персистентность
 * отвечает DataMapper. Связь с техническими характеристиками — has-one
 * (необязательная).
 */
final class Car
{
    public ?int $id = null;
    public string $title;
    public string $description;
    public string $price;
    public ?string $photoUrl;
    public string $contacts;
    public ?string $createdAt = null;

    /** Технические характеристики (has-one), либо null. */
    public ?CarOption $option = null;

    public function __construct(
        string $title,
        string $description,
        string $price,
        ?string $photoUrl,
        string $contacts,
        ?CarOption $option = null,
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->price = $price;
        $this->photoUrl = $photoUrl;
        $this->contacts = $contacts;
        $this->option = $option;
    }
}
