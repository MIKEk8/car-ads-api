<?php

declare(strict_types=1);

// Автозагрузчик подключается первым: константы ниже читаются через app\Env.
// Порядок важен и в другую сторону — BaseYii.php при загрузке вычисляет
// YII_ENV_DEV/PROD/TEST из YII_ENV, поэтому Yii.php требуется последним.
require dirname(__DIR__) . '/vendor/autoload.php';

use app\Env;

defined('YII_DEBUG') or define('YII_DEBUG', Env::bool('YII_DEBUG', true));
defined('YII_ENV') or define('YII_ENV', Env::string('YII_ENV', 'dev'));

require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

$config = require dirname(__DIR__) . '/config/web.php';

(new yii\web\Application($config))->run();
