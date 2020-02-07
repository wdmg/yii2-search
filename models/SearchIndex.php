<?php

namespace wdmg\search\models;

use Yii;
use yii\db\Expression;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%search_index}}".
 *
 * @property int $id
 * @property int $item_id
 * @property int $keyword_id
 * @property int $weight
 */
class SearchIndex extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%search_index}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules = [
            [['item_id', 'keyword_id'], 'required'],
            [['item_id', 'keyword_id'], 'integer'],
            ['weight', 'double', 'max' => 100]
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
            'item_id' => Yii::t('app/modules/search', 'Item ID'),
            'keyword_id' => Yii::t('app/modules/search', 'Keyword ID'),
            'weight' => Yii::t('app/modules/search', 'Weight'),
        ];
    }
}
