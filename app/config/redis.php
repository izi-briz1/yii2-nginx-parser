<?php

return [
    'class' => 'yii\redis\Connection',
    'hostname' => 'redis',
    'port' => getenv('REDIS_PORT'),
    'database' => 0
];
