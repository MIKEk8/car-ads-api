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
    ],
    'controllerMap' => [
        'migrate' => [
            'class' => \yii\console\controllers\MigrateController::class,
            'migrationPath' => '@app/migrations',
        ],
    ],
    'params' => $params,
];
