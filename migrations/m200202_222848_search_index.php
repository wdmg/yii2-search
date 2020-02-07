<?php

use yii\db\Migration;

/**
 * Class m200202_222848_search_index
 */
class m200202_222848_search_index extends Migration
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

        $this->createTable('{{%search_index}}', [
            'id' => $this->bigPrimaryKey(),
            'item_id' => $this->integer()->notNull(),
            'keyword_id' => $this->bigInteger()->notNull(),
            'weight' => $this->double(2)
        ], $tableOptions);

        $this->addForeignKey(
            'fk_search_index_to_search',
            '{{%search_index}}',
            'item_id',
            '{{%search}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_search_index_to_keywords',
            '{{%search_index}}',
            'keyword_id',
            '{{%search_keywords}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('{{%idx-search_index-items}}', '{{%search_index}}', ['item_id', 'keyword_id']);
        $this->createIndex('{{%idx-search_index-keywords}}', '{{%search_index}}', ['keyword_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('{{%idx-search_index-items}}', '{{%search_index}}');
        $this->dropIndex('{{%idx-search_index-keywords}}', '{{%search_index}}');

        $this->dropForeignKey(
            'fk_search_index_to_search',
            '{{%search_index}}'
        );
        $this->dropForeignKey(
            'fk_search_index_to_keywords',
            '{{%search_index}}'
        );

        $this->truncateTable('{{%search_index}}');
        $this->dropTable('{{%search_index}}');
    }
}
