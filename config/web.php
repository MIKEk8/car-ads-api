<?php

declare(strict_types=1);

use app\Env;
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
    // Логгер поднимается до обработки запроса, иначе ранние ошибки минуют его.
    'bootstrap' => ['log'],
    'components' => [
        'db' => $db,
        // Клиенту про внутренние ошибки не сообщается ничего (см. errorHandler
        // и YII_DEBUG), но где-то след остаться обязан — иначе инцидент в
        // проде нечем разбирать. Проверено: без этого компонента падение
        // коннекта к БД не оставляло вообще никакой диагностики.
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/app.log',
                    // 4xx — это ошибки клиента, а не инциденты сервиса.
                    'except' => ['yii\web\HttpException:4*'],
                ],
            ],
        ],
        // Нужен для enableSchemaCache: без него настройка молча не работала бы.
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'request' => [
            // Нормализует Content-Type: без этого регистр или пробел перед «;»
            // ломают регистрозависимый подбор парсера внутри Yii, и JSON-тело
            // молча теряется. См. app\Web\MediaType.
            'class' => \app\Web\Request::class,
            'cookieValidationKey' => Env::string('COOKIE_VALIDATION_KEY', 'car-api-dev-secret-change-me'),
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
            // Правила без привязки к HTTP-методу: метод проверяет VerbFilter
            // в контроллере, поэтому чужой метод получает 405 с заголовком
            // Allow, а не безликий 404.
            'rules' => [
                'car/create' => 'car/create',
                'car/list' => 'car/list',
                'car/<id:\d+>' => 'car/view',
            ],
        ],
    ],
    'params' => $params,
];
