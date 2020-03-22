<?php

use yii\db\Migration;

/**
 * Class m200202_222306_search
 */
class m200202_222306_search extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%search}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'url' => $this->string(1024)->notNull(),
            'context' => $this->string(24)->null(),
            'hash' => $this->string(32)->notNull(),
            'snippets' => $this->binary(16777215),
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->datetime()->defaultExpression('CURRENT_TIMESTAMP')
        ], $tableOptions);

        $this->createIndex('{{%idx-search}}', '{{%search}}', ['title', 'url(255)', 'hash', 'context', 'updated_at']);
        $this->createIndex('{{%idx-search-hash}}', '{{%search}}', ['hash']);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('{{%idx-search}}', '{{%search}}');
        $this->dropIndex('{{%idx-search-hash}}', '{{%search}}');
        $this->truncateTable('{{%search}}');
        $this->dropTable('{{%search}}');
    }
}
