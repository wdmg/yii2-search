<?php

use yii\db\Migration;

/**
 * Class m200307_214824_live_search
 */
class m200307_214824_live_search extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        if (class_exists('\wdmg\pages\models\Pages')) {
            $tableName = \wdmg\pages\models\Pages::tableName();
            $this->execute("ALTER TABLE $tableName ADD FULLTEXT INDEX {{%idx-pages-search}} (
              `title`,
              `description`,
              `keywords`,
              `content`
            )");
        }

        if (class_exists('\wdmg\news\models\News')) {
            $tableName = \wdmg\news\models\News::tableName();
            $this->execute("ALTER TABLE $tableName ADD FULLTEXT INDEX {{%idx-news-search}} (
              `title`,
              `description`,
              `keywords`,
              `content`
            )");
        }

        if (class_exists('\wdmg\blog\models\Posts')) {
            $tableName = \wdmg\blog\models\Posts::tableName();
            $this->execute("ALTER TABLE $tableName ADD FULLTEXT INDEX {{%idx-blog-search}} (
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
            $tableName = \wdmg\pages\models\Pages::tableName();
            $this->dropIndex('{{%idx-pages-search}}', $tableName);

        }

        if (class_exists('\wdmg\news\models\News')) {
            $tableName = \wdmg\news\models\News::tableName();
            $this->dropIndex('{{%idx-news-search}}', $tableName);
        }

        if (class_exists('\wdmg\blog\models\Posts')) {
            $tableName = \wdmg\blog\models\Posts::tableName();
            $this->dropIndex('{{%idx-blog-search}}', $tableName);
        }

    }
}
