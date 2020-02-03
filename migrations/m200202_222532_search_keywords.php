<?php

use yii\db\Migration;

/**
 * Class m200202_222532_search_keywords
 */
class m200202_222532_search_keywords extends Migration
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

        $this->createTable('{{%search_keywords}}', [

            'id' => $this->bigPrimaryKey(),
            'keyword' => $this->string(255)->null(),

        ], $tableOptions);

        $this->createIndex('{{%idx-search_keywords-keywords}}', '{{%search_keywords}}', ['keyword']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('{{%idx-search_keywords-keywords}}', '{{%search_keywords}}');
        $this->truncateTable('{{%search_keywords}}');
        $this->dropTable('{{%search_keywords}}');
    }
}
