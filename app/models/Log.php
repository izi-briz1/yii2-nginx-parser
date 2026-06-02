<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $file_id
 * @property string $ip
 * @property string $datetime
 * @property string $url
 * @property int $status
 * @property string $userAgent
 * @property string $os
 * @property string $arch
 * @property string $browser
 * @property int $isBot
 * @property string $created
 */
class Log extends ActiveRecord{
    /**
     * @return string
     */
    public static function tableName(){
        return 'logs';
    }
}
