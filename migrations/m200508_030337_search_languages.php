<?php

use yii\db\Migration;

/**
 * Class m200508_030337_search_languages
 */
class m200508_030337_search_languages extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $defaultLocale = null;
        if (isset(Yii::$app->sourceLanguage))
            $defaultLocale = Yii::$app->sourceLanguage;

        if (is_null($this->getDb()->getSchema()->getTableSchema('{{%search}}')->getColumn('locale'))) {

            $this->addColumn('{{%search}}', 'locale', $this->string(10)->defaultValue($defaultLocale)->after('snippets'));
            $this->createIndex('{{%idx-search-locale}}', '{{%search}}', ['locale']);

            // If module `Translations` exist setup foreign key `locale` to `trans_langs.locale`
            if (class_exists('\wdmg\translations\models\Languages')) {
                $langsTable = \wdmg\translations\models\Languages::tableName();
                $this->addForeignKey(
                    'fk_search_to_langs',
                    '{{%search}}',
                    'locale',
                    $langsTable,
                    'locale',
                    'NO ACTION',
                    'CASCADE'
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

        if (!is_null($this->getDb()->getSchema()->getTableSchema('{{%search}}')->getColumn('locale'))) {
            $this->dropIndex('{{%idx-search-locale}}', '{{%search}}');
            $this->dropColumn('{{%search}}', 'locale');

            if (class_exists('\wdmg\translations\models\Languages')) {
                $langsTable = \wdmg\translations\models\Languages::tableName();
                if (!(Yii::$app->db->getTableSchema($langsTable, true) === null)) {
                    $this->dropForeignKey(
                        'fk_search_to_langs',
                        '{{%search}}'
                    );
                }
            }
        }
    }
}
