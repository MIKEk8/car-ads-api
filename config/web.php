<?php

declare(strict_types=1);

use yii\web\Response;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$container = require __DIR__ . '/container.php';

return [
    'id' => 'car-api',
    'name' => 'Car Ads API',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\Controller',
    'container' => $container,
    'components' => [
        'db' => $db,
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'car-api-dev-secret-change-me',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            // Весь API отвечает в JSON; кириллица и слэши — без \u-эскейпинга.
            'format' => Response::FORMAT_JSON,
            'formatters' => [
                Response::FORMAT_JSON => [
                    'class' => \yii\web\JsonResponseFormatter::class,
                    'prettyPrint' => YII_DEBUG,
                    'encodeOptions' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ],
            ],
        ],
        'errorHandler' => [
            // Ошибки рендерятся стандартным обработчиком как JSON.
            'errorAction' => null,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
                'POST car/create' => 'car/create',
                'GET car/list' => 'car/list',
                'GET car/<id:\d+>' => 'car/view',
            ],
        ],
    ],
    'params' => $params,
];
