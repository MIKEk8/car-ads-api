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
    'bootstrap' => ['log'],
    'components' => [
        'db' => $db,
        // Тот же журнал, что и у web-приложения: ошибки миграций тоже должны
        // оставлять след, а не только печататься в консоль.
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/console.log',
                ],
            ],
        ],
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
