<?php

use yii\db\Migration;

/**
 * Class m200202_223527_live_search
 */
class m200202_223527_live_search extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        if (class_exists('\wdmg\pages\models\Pages')) {
            $userTable = \wdmg\pages\models\Pages::tableName();
            $this->execute("ALTER TABLE $userTable ADD FULLTEXT INDEX {{%idx-pages-search}} (
              `title`,
              `description`,
              `keywords`,
              `content`
            )");
        }

        if (class_exists('\wdmg\news\models\News')) {
            $userTable = \wdmg\news\models\News::tableName();
            $this->execute("ALTER TABLE $userTable ADD FULLTEXT INDEX {{%idx-news-search}} (
              `title`,
              `description`,
              `keywords`,
              `content`
            )");
        }

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

        if (class_exists('\wdmg\pages\models\Pages')) {
            $userTable = \wdmg\pages\models\Pages::tableName();
            $this->dropIndex('{{%idx-pages-search}}', $userTable);

        }

        if (class_exists('\wdmg\news\models\News')) {
            $userTable = \wdmg\news\models\News::tableName();
            $this->dropIndex('{{%idx-news-search}}', $userTable);
        }

    }
}
