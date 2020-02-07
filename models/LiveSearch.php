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

        // Составим список ключевых слов с длинной более 3-х символов
        foreach ($expanded as $key => $keyword) {
            if (strlen($keyword) >= 3) {
                $keywords[] = $keyword;
            }
        }

        // Соберём запрос поиска в строку
        $request = implode(" ", $keywords);

        // Добавим и сам запрос в качестве ключевого слова
        $keywords[] = $request;

        // Default snippets options
        $snippetsOptions = [
            'before' => 6,
            'after' => 4,
            'tag' => false,
            'length' => 255,
            'delimiter' => '…'
        ];

        // Load snippets options from module config
        if (!is_null($this->module->snippetOptions)) {

            if (isset($this->module->snippetOptions['max_words_before']))
                $snippetsOptions['before'] = $this->module->snippetOptions['max_words_before'];

            if (isset($this->module->snippetOptions['max_words_after']))
                $snippetsOptions['after'] = $this->module->snippetOptions['max_words_after'];

            if (isset($this->module->snippetOptions['bolder_tag']))
                $snippetsOptions['tag'] = $this->module->snippetOptions['bolder_tag'];

            if (isset($this->module->snippetOptions['max_length']))
                $snippetsOptions['length'] = $this->module->snippetOptions['max_length'];

            if (isset($this->module->snippetOptions['delimiter']))
                $snippetsOptions['delimiter'] = $this->module->snippetOptions['delimiter'];

        }

        $results = null;
        if (!empty($request) && is_array($this->module->supportModels)) {

            foreach ($this->module->supportModels as $context => $support) {
                if (isset($support['class']) && isset($support['options'])) {

                    $class = $support['class'];
                    $options = $support['options'];

                    if (class_exists($class)) {
                        if ($model = new $class()) {
                            if ($model instanceof \yii\db\ActiveRecord) {

                                $attributes = $model->getAttributes();
                                if (is_array($options['fields'])) {

                                    // Сверим параметры выборки с реальной коллекцией аттрибутов модели
                                    $fields = array_uintersect($options['fields'], array_keys($attributes), 'strcasecmp');

                                    $result = null;

                                    // Берем результаты из кеша, если они там есть
                                    if ($this->module->cacheExpire !== 0 && ($cache = Yii::$app->getCache())) {
                                        if ($cache->exists('live-search')) {
                                            $cached = $cache->get('live-search');
                                            if (isset($cached[$request]))
                                                $result = $cached[$request];

                                        }
                                    }

                                    // Если данных в кеше не оказалось, выполним запрос поиска
                                    if (is_null($result)) {

                                        $query = $model::find()
                                            ->select('*, MATCH (' . implode(', ', $fields) . ') AGAINST (\'' . $request . '\' IN BOOLEAN MODE) as REL')
                                            ->where('MATCH (' . implode(', ', $fields) . ') AGAINST (\'' . $request . '\' IN BOOLEAN MODE)');

                                        if (null !== $model::STATUS_PUBLISHED)
                                            $query->andWhere(['status' => $model::STATUS_PUBLISHED]);

                                        $matches = $query->orderBy(['REL' => SORT_DESC])->limit(100)->all();

                                        // Экранируем значения для использования в regex-паттернах
                                        array_walk($keywords, function (&$item, $key) {
                                            $item = addcslashes($item, "\\+-.?*!^:");
                                        });

                                        $module = $this->module;
                                        $result = \yii\helpers\ArrayHelper::toArray($matches, [
                                            $support['class'] => [
                                                'id',
                                                'context' => function ($matches) use ($context) {
                                                    return $context;
                                                },
                                                'title' => function ($matches) use ($options, $fields, $keywords) {

                                                    if (isset($options['title']))
                                                        $title = $matches[$options['title']];
                                                    else
                                                        return false;

                                                    $title = strip_tags($title);
                                                    $title = preg_replace('/(' . implode("|", $keywords) . ')/iu', '<strong>$0</strong>', $title);
                                                    return $title;
                                                },
                                                'snippet' => function ($matches) use ($fields, $keywords, $module, $snippetsOptions) {

                                                    $content = '';

                                                    // Склеиваем весь доступный контент модели
                                                    foreach ($fields as $field) {
                                                        if (!empty($content))
                                                            $content .= " " . $matches->$field;
                                                        else
                                                            $content .= $matches->$field;
                                                    }

                                                    // Очищаем контент от html-тегов
                                                    $content = strip_tags(html_entity_decode($content));

                                                    // Формируем сниппет релевантный ключу
                                                    $snippets = $module->generateSnippets($content, $keywords, $snippetsOptions, false);

                                                    $delimiter = $snippetsOptions['delimiter'];
                                                    if (!empty($snippets))
                                                        return implode(" " . $delimiter . " ", $snippets) . $delimiter;
                                                    elseif (!empty($content))
                                                        return mb_substr($content, 0, intval($snippetsOptions['length'])) . $delimiter;

                                                    return "";
                                                },
                                                'url' => function ($matches) use ($options) {

                                                    if (isset($options['url']))
                                                        return $matches[$options['url']];
                                                    else
                                                        return false;

                                                }
                                            ]
                                        ]);

                                        // Запишем в кеш результаты поиска
                                        if ($result && $cache = Yii::$app->getCache()) {
                                            $cache->add('live-search', [
                                                $request => $result
                                            ], intval($this->module->cacheExpire));
                                        }

                                    }

                                    // Добавим результаты поиска в коллекцию
                                    if (!empty($result)) {
                                        $results[$context] = $result;
                                    }

                                }
                            }
                        }
                    }


                }
            }
        }

        // Подготавим общий результат поиска по всем моделям и возвратим его
        $output = [];
        if (is_array($results)) {
            foreach ($results as $result) {
                $output = ArrayHelper::merge($output, $result);
            }
        }

        return $output;

    }

}
