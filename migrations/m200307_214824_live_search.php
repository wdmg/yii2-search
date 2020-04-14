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
        if ($this->db->driverName === 'mysql') {
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
        } else if ($this->db->driverName === 'pgsql') {
            if (class_exists('\wdmg\pages\models\Pages')) {
                $tableName = \wdmg\pages\models\Pages::tableName();
                $this->execute("ALTER TABLE $tableName ADD COLUMN pages_live_search tsvector;");
                $this->execute("UPDATE $tableName SET pages_live_search = to_tsvector(
                    coalesce(title,'') ||
                    coalesce(description,'') ||
                    coalesce(keywords,'') ||
                    coalesce(content,'')
                );");
                $this->execute("CREATE INDEX {{%idx-pages-search}} ON $tableName USING GIN (pages_live_search);");
            }
            if (class_exists('\wdmg\news\models\News')) {
                $tableName = \wdmg\news\models\News::tableName();
                $this->execute("ALTER TABLE $tableName ADD COLUMN news_live_search tsvector;");
                $this->execute("UPDATE $tableName SET news_live_search = to_tsvector(
                    coalesce(title,'') ||
                    coalesce(description,'') ||
                    coalesce(keywords,'') ||
                    coalesce(content,'')
                );");
                $this->execute("CREATE INDEX {{%idx-news-search}} ON $tableName USING GIN (news_live_search);");
            }
            if (class_exists('\wdmg\blog\models\Posts')) {
                $tableName = \wdmg\blog\models\Posts::tableName();
                $this->execute("ALTER TABLE $tableName ADD COLUMN blog_live_search tsvector;");
                $this->execute("UPDATE $tableName SET blog_live_search = to_tsvector(
                    coalesce(title,'') ||
                    coalesce(description,'') ||
                    coalesce(keywords,'') ||
                    coalesce(content,'')
                );");
                $this->execute("CREATE INDEX {{%idx-blog-search}} ON $tableName USING GIN (blog_live_search);");
            }
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
