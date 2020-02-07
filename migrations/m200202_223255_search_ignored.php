<?php

use yii\db\Migration;

/**
 * Class m200202_223255_search_ignored
 */
class m200202_223255_search_ignored extends Migration
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

        $this->createTable('{{%search_ignored}}', [

            'id' => $this->primaryKey(),

            'pattern' => $this->string(255)->notNull(),
            'status' => $this->tinyInteger(1)->null()->defaultValue(1),

            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
            'created_by' => $this->integer(11)->notNull()->defaultValue(0),
            'updated_at' => $this->datetime()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_by' => $this->integer(11)->notNull()->defaultValue(0),

        ], $tableOptions);

        $this->createIndex('{{%idx-search_ignored-pattern}}', '{{%search_ignored}}', ['pattern']);
        $this->createIndex('{{%idx-search_ignored-author}}','{{%search_ignored}}', ['created_by', 'updated_by'],false);

        // If exist module `Users` set foreign key `created_by`, `updated_by` to `users.id`
        if (class_exists('\wdmg\users\models\Users')) {
            $userTable = \wdmg\users\models\Users::tableName();
            $this->addForeignKey(
                'fk_search_ignored_to_users1',
                '{{%search_ignored}}',
                'created_by',
                $userTable,
                'id',
                'NO ACTION',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_search_ignored_to_users2',
                '{{%search_ignored}}',
                'updated_by',
                $userTable,
                'id',
                'NO ACTION',
                'CASCADE'
            );
        }

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

        $this->dropIndex('{{%idx-search_ignored-pattern}}', '{{%search_ignored}}');
        $this->dropIndex('{{%idx-search_ignored-author}}', '{{%search_ignored}}');

        if (class_exists('\wdmg\users\models\Users')) {
            $userTable = \wdmg\users\models\Users::tableName();
            if (!(Yii::$app->db->getTableSchema($userTable, true) === null)) {
                $this->dropForeignKey(
                    'fk_search_ignored_to_users1',
                    '{{%search_ignored}}'
                );
                $this->dropForeignKey(
                    'fk_search_ignored_to_users2',
                    '{{%search_ignored}}'
                );
            }
        }

        $this->truncateTable('{{%search_ignored}}');
        $this->dropTable('{{%search_ignored}}');
    }
}
