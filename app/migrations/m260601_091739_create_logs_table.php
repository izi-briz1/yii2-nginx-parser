<?php

use yii\db\Migration;

/**
 * Create logs table
 */
class m260601_091739_create_logs_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('logs', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'file_id' => $this->integer()->unsigned()->notNull(),
            'ip' => $this->string(45)->notNull(),
            'datetime' => $this->timestamp()->notNull(),
            'url' => $this->string(2048)->notNull(),
            'status' => $this->smallInteger()->unsigned()->notNull(),
            'user_agent' => $this->string(1024)->notNull()->defaultValue(''),
            'os' => $this->string(64)->notNull()->defaultValue('unknown'),
            'arch' => $this->string(16)->notNull()->defaultValue('unknown'),
            'browser' => $this->string(64)->notNull()->defaultValue('unknown'),
            'is_bot' => $this->boolean()->notNull()->defaultValue(false),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('IK-logs-file_id', 'logs', 'file_id');
        $this->createIndex('IK-logs-datetime', 'logs', 'datetime');
        $this->createIndex('IK-logs-os', 'logs', 'os');
        $this->createIndex('IK-logs-arch', 'logs', 'arch');
        $this->createIndex('IK-logs-is_bot', 'logs', 'is_bot');
        $this->createIndex('IK-logs-browser', 'logs', 'browser');

        $this->addForeignKey(
            'FK-logs-file_id',
            'logs',
            'file_id',
            'files',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('FK-logs-file_id', 'logs');
        $this->dropTable('logs');
    }
}
