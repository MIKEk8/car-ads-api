<?php

declare(strict_types=1);

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$container = require __DIR__ . '/container.php';

return [
    'id' => 'car-api-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\commands',
    'container' => $container,
    'components' => [
        'db' => $db,
        // Тот же компонент, что и в web-конфигурации: db.php ссылается на него
        // через enableSchemaCache и должен находить его в обоих приложениях.
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
    ],
    'controllerMap' => [
        'migrate' => [
            'class' => \yii\console\controllers\MigrateController::class,
            'migrationPath' => '@app/migrations',
        ],
    ],
    'params' => $params,
];
