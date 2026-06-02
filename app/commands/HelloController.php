<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

declare(strict_types=1);

namespace app\commands;

use app\components\NginxLogLineParser;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HelloController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     * @return int Exit code
     */
    public function actionIndex(NginxLogLineParser $nginxLogLineParser): int
    {
        $line = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /favicon/favicon-32.png HTTP/1.1" 200 1306 "http://modimio.loc/icms/catalog/catalog_edit?id=4" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"';

        $Redis = Yii::$app->get('redis'); /* @var $Redis \yii\redis\Connection */
        //$Redis->lpush('nginx:logs', '123');
        //$r = $Redis->brpop('nginx:logs', 5);

        $Redis->set('nginx:logs111', json_encode([
            'status' => 1,
            'total' => 2,
            'processed' => 3,
            'percent' => 4,
        ]));

        var_dump($Redis->get('nginx:logs111'));

        return ExitCode::OK;
    }
}
