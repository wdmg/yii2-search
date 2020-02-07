<?php

namespace wdmg\search\models;

use Yii;
use yii\db\Expression;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%search_keywords}}".
 *
 * @property int $id
 * @property string $keyword
 */
class SearchKeywords extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%search_keywords}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules = [
            ['keyword', 'required'],
            ['keyword', 'string', 'max' => 64],
        ];

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app/modules/search', 'ID'),
            'keyword' => Yii::t('app/modules/search', 'Keyword'),
        ];
    }
}
