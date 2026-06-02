<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $path
 * @property string $md5
 * @property string $status
 * @property int $total_lines
 * @property int $processed_lines
 * @property string $created
 * @property string $updated
 */
class File extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return 'files';
    }
}
