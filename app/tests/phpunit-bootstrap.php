<?php

declare(strict_types=1);

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../vendor/autoload.php';

// Namespace app\ резолвится автозагрузчиком Yii через алиас @app,
// который обычно выставляет Application. Для чистых unit-тестов задаём его вручную.
Yii::setAlias('@app', dirname(__DIR__));