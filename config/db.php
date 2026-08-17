<?php

declare(strict_types=1);

use app\Env;

/**
 * Конфигурация подключения к PostgreSQL.
 * Параметры читаются из переменных окружения (удобно для Docker),
 * со значениями по умолчанию для локального запуска.
 */

return [
    'class' => \app\Db\Connection::class,
    'dsn' => Env::string('DB_DSN', 'pgsql:host=127.0.0.1;port=5432;dbname=cars'),
    'username' => Env::string('DB_USER', 'postgres'),
    // Пустое значение сохраняется: DB_PASSWORD= — это заданный пустой пароль.
    'password' => Env::string('DB_PASSWORD', 'postgres'),
    'charset' => 'utf8',
    // Работает благодаря компоненту `cache` в конфигурации приложения.
    'enableSchemaCache' => !YII_DEBUG,
];
