<?php

declare(strict_types=1);

/**
 * Конфигурация подключения к PostgreSQL.
 * Параметры читаются из переменных окружения (удобно для Docker),
 * со значениями по умолчанию для локального запуска.
 */

return [
    'class' => \app\Db\Connection::class,
    'dsn' => getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=cars',
    'username' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'postgres',
    'charset' => 'utf8',
    'enableSchemaCache' => !YII_DEBUG,
];
