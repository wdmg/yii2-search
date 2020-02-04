<?php

namespace wdmg\search\models;

use wdmg\helpers\ArrayHelper;
use Yii;

class LiveSearch extends \yii\base\Model
{

    public $query;
    public $module;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if (!($this->module = Yii::$app->getModule('admin/search')))
            $this->module = Yii::$app->getModule('search');

    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules = [
            ['query', 'string', 'max' => 128],
        ];

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'query' => Yii::t('app/modules/search', 'Search query'),
        ];
    }

    public function search($request = null)
    {
        if (is_null($request))
            return false;

        $keywords = [];
        $request = preg_replace('/[^\w\s\-\+\"]/u', ' ', $request);
        $expanded = preg_split('/\s+/', $request);
        //$expanded = explode(' ', $request);
        foreach ($expanded as $key => $keyword){
            if (strlen($keyword) >= 3) {
                $keywords[] = $keyword;
            }
        }
        $request = implode(" ", $keywords);

        $count = 0;
        $results = null;
        if (!empty($request) && is_array($this->module->supportModels)) {

            //$query = new \yii\db\Query();
            foreach ($this->module->supportModels as $section => $support) {
                if (isset($support['class']) && isset($support['fields'])) {
                    if ($model = new $support['class']) {
                        if ($model instanceof \yii\db\ActiveRecord) {
                            //$tableName = $model::tableName();
                            $attributes = $model->getAttributes();
                            if (is_array($support['fields'])) {
                                $fields = array_uintersect($support['fields'], array_keys($attributes), 'strcasecmp');

                                $query = $model::find()
                                    ->select('*, MATCH (' . implode(', ', $fields) . ') AGAINST (\'' . $request . '\' IN BOOLEAN MODE) as REL')
                                    ->where('MATCH (' . implode(', ', $fields) . ') AGAINST (\'' . $request . '\' IN BOOLEAN MODE)');

                                if (null !== $model::STATUS_PUBLISHED)
                                    $query->andWhere(['status' => $model::STATUS_PUBLISHED]);

                                $matches = $query->orderBy(['REL' => SORT_DESC])->limit(10)->all();

                                $results[$section] = \yii\helpers\ArrayHelper::toArray($matches, [
                                    $support['class'] => [
                                        'id',
                                        'section' => function ($matches) use ($section) {
                                            return $section;
                                        },
                                        'title' => function ($matches) use ($fields, $keywords) {
                                            $title = $matches->title;
                                            $title = strip_tags($title);
                                            $title = preg_replace('/(' . implode("|", $keywords) . ')/iu', '<strong>$0</strong>', $title);
                                            return $title;
                                        },
                                        'snippet' => function ($matches) use ($fields, $keywords) {

                                            $content = '';

                                            // Склеиваем весь доступный контент модели
                                            foreach ($fields as $field) {
                                                $content .= $matches->$field;
                                            }

                                            // Очищаем контент от html-тегов
                                            $content = strip_tags(html_entity_decode($content));

                                            // Формируем сниппет релевантный ключу
                                            $span = 3;
                                            $snippets = [];
                                            preg_match_all('/[\w-]+/iu', $content, $matches, PREG_OFFSET_CAPTURE);

                                            foreach ($keywords as $keyword) {

                                                $count_in = 0;
                                                for ($i = 0, $n = count($matches[0]); $i < $n; ++$i) {
                                                    if ($count_in >= 1)
                                                        continue;

                                                    $match = $matches[0][$i];
                                                    if (strcasecmp($keyword, $match[0]) === 0) {
                                                        $start = $matches[0][max(0, $i - $span)][1];
                                                        $end = $matches[0][min($n - 1, $i + $span + 1)][1];
                                                        $snippet = substr($content, $start, $end - $start);
                                                        $snippet = preg_replace('/(' . $keyword . ')/iu', '<strong>$0</strong>', $snippet);
                                                        $snippets[] = $snippet;
                                                        $count_in++;
                                                    }
                                                }
                                            }

                                            return implode(' … ', $snippets) . ' … ';

                                        },
                                        'url' => function ($matches) {
                                            return $matches->url;
                                        },
                                        'created' => 'created_at'
                                    ]
                                ]);
                                $count += count($matches);
                            }
                        }
                    }
                }
            }
        }

        // Подготавливаем результат поиска и возвращаем его
        $output = [];
        foreach ($results as $result) {
            $output = ArrayHelper::merge($output, $result);
        }
        return $output;

    }

}
