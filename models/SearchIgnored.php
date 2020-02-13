<?php

namespace wdmg\search\models;

use Yii;
use yii\db\Expression;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\BlameableBehavior;

/**
 * This is the model class for table "{{%search_ignored}}".
 *
 * @property int $id
 * @property int $pattern
 * @property int $status
 * @property int $created_at
 * @property int $created_by
 * @property int $updated_at
 * @property int $updated_by
 */
class SearchIgnored extends ActiveRecord
{

    const PATTERN_STATUS_ACTIVE = 1;
    const PATTERN_STATUS_DISABLED = 0;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%search_ignored}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
                ],
                'value' => new Expression('NOW()'),
            ],

        ];

        if (class_exists('\wdmg\users\models\Users')) {
            $behaviors[] = [
                'class' => BlameableBehavior::class,
                'createdByAttribute' => 'created_by',
                'updatedByAttribute' => 'updated_by',
            ];
        }

        return $behaviors;
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules = [
            [['pattern'], 'required'],
            [['pattern'], 'string', 'max' => 255],
            [['status'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
        ];

        if (class_exists('\wdmg\users\models\Users')) {
            $rules[] = [['created_by', 'updated_by'], 'safe'];
        }

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app/modules/search', 'ID'),
            'pattern' => Yii::t('app/modules/search', 'Pattern'),
            'status' => Yii::t('app/modules/search', 'Status'),
            'created_at' => Yii::t('app/modules/search', 'Created at'),
            'created_by' => Yii::t('app/modules/search', 'Created by'),
            'updated_at' => Yii::t('app/modules/search', 'Updated at'),
            'updated_by' => Yii::t('app/modules/search', 'Updated by')
        ];
    }

    /**
     * @return array of list
     */
    public function getStatusesList($allStatuses = false)
    {
        if($allStatuses)
            $list[] = [
                '*' => Yii::t('app/modules/search', 'All statuses')
            ];

        $list[] = [
            self::PATTERN_STATUS_DISABLED => Yii::t('app/modules/search', 'Disabled'),
            self::PATTERN_STATUS_ACTIVE => Yii::t('app/modules/search', 'Active')
        ];

        return $list;
    }
}
