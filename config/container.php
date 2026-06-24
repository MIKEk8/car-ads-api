<?php

declare(strict_types=1);

use app\Repository\CarRepository;
use app\Repository\CarRepositoryInterface;

/**
 * Конфигурация DI-контейнера приложения.
 *
 * Здесь связываются абстракции с конкретными реализациями (принцип DIP).
 * Маппер и репозиторий получают \yii\db\Connection через автоматический
 * autowiring контейнера — определение ниже отдаёт компонент `db` приложения.
 */

return [
    'singletons' => [
        // Repository зависит от интерфейса — подменяется одной строкой.
        CarRepositoryInterface::class => CarRepository::class,
    ],
    'definitions' => [
        // Любая зависимость с тайп-хинтом \yii\db\Connection получит общий компонент db.
        \yii\db\Connection::class => static fn (): \yii\db\Connection => Yii::$app->db,
    ],
];
