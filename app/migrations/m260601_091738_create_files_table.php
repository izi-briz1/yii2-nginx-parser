<?php

use yii\db\Migration;

/**
 * Create files table
 */
class m260601_091738_create_files_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('files', [
            'id' => $this->primaryKey()->unsigned(),
            'path' => $this->string(1024)->notNull(),
            'md5' => $this->char(32)->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('pending'),
            'total_lines' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'processed_lines' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        # повторная загрузка того же файла не должна создавать дубли
        $this->createIndex('IK-files-md5', 'files', 'md5', true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('files');
    }
}
