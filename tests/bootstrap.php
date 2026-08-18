<?php

declare(strict_types=1);

/**
 * Бутстрап тестов.
 *
 * Кроме автозагрузчика нужен сам Yii: формы наследуют yii\base\Model, и без
 * загруженного Yii.php не разрешатся ни валидаторы, ни базовые классы.
 * Экземпляр приложения при этом НЕ создаётся — проверено, что валидация
 * работает и без него, и тесты остаются модульными: ни БД, ни компонентов.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
