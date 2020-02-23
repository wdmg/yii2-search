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

    public function search($request = null, $noCache = false)
    {

        if (is_null($request))
            return false;

        $keywords = [];
        $request = preg_replace('/[^\w\s\-\+\"]/u', ' ', $request);
        $expanded = preg_split('/\s+/', $request);

        // Составим список ключевых слов с длинной более 3-х символов
        foreach ($expanded as $key => $keyword) {
            if (mb_strlen($keyword) >= 3) {
                $keywords[] = $keyword;
            }
        }

        // Collect the search query into a string
        $request = implode(" ", $keywords);

        // Add the query itself as a keyword
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

        if ($noCache)
            $this->module->cacheExpire = 0;

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

                                    // Check the selection parameters with a real collection of model attributes
                                    $fields = array_uintersect($options['fields'], array_keys($attributes), 'strcasecmp');

                                    $result = null;

                                    // Take the results from the cache, if any
                                    if ($this->module->cacheExpire !== 0 && ($cache = Yii::$app->getCache())) {
                                        if ($cache->exists(md5('live-search'))) {
                                            $cached = $cache->get(md5('live-search'));
                                            if (isset($cached[$request][$context]))
                                                $result = $cached[$request][$context];

                                        }
                                    }

                                    // If there was no data in the cache, execute a search query
                                    if (is_null($result)) {

                                        $query = $model::find()
                                            ->select('*, MATCH (' . implode(', ', $fields) . ') AGAINST (\'' . $request . '\' IN BOOLEAN MODE) as REL')
                                            ->where('MATCH (' . implode(', ', $fields) . ') AGAINST (\'' . $request . '\' IN BOOLEAN MODE)');

                                        // Checking, the model may not meet the conditions that allow displaying in search results
                                        if (isset($options['conditions'])) {
                                            $query->andWhere($options['conditions']);
                                        }

                                        $query->orderBy(['REL' => SORT_DESC])->limit(100);
                                        // var_dump($query->createCommand()->getRawSql());die();
                                        $matches = $query->all();

                                        // Escape the values to use in regex patterns
                                        array_walk($keywords, function (&$item, $key) {
                                            $item = addcslashes($item, "\\+-.?*!^:");
                                        });

                                        $module = $this->module;
                                        $result = \yii\helpers\ArrayHelper::toArray($matches, [
                                            $support['class'] => [
                                                'id' => function ($matches) {

                                                    if (isset($matches['id']))
                                                        return $matches['id'];
                                                    else
                                                        return null;

                                                },
                                                'context' => function ($matches) use ($context) {
                                                    return $context;
                                                },
                                                'title' => function ($matches) use ($options, $fields, $keywords) {

                                                    if (isset($options['title'])) {
                                                        if (!is_string($options['title']) && is_callable($options['title'])) {
                                                            $title = $options['title']($matches);
                                                        } else {
                                                            $title = $matches[$options['title']];
                                                        }
                                                    } else {
                                                        return false;
                                                    }

                                                    $title = strip_tags($title);
                                                    $title = preg_replace('/(' . implode("|", $keywords) . ')/iu', '<strong>$0</strong>', $title);
                                                    return $title;
                                                },
                                                'snippet' => function ($matches) use ($fields, $keywords, $module, $snippetsOptions) {

                                                    $content = '';

                                                    // Glue all available model content
                                                    foreach ($fields as $field) {
                                                        if (!empty($content))
                                                            $content .= " " . $matches->$field;
                                                        else
                                                            $content .= $matches->$field;
                                                    }

                                                    // We clear the content from html tags
                                                    $content = strip_tags(html_entity_decode($content));

                                                    // Generate a key relevant snippet
                                                    $snippets = $module->generateSnippets($content, $keywords, $snippetsOptions, false);

                                                    $delimiter = $snippetsOptions['delimiter'];
                                                    if (!empty($snippets)) {
                                                        return implode(" " . $delimiter . " ", $snippets) . $delimiter;
                                                    } else {
                                                        return false;
                                                    }
                                                },
                                                'url' => function ($matches) use ($options) {
                                                    if (isset($options['url'])) {
                                                        if (!is_string($options['url']) && is_callable($options['url'])) {
                                                            return $options['url']($matches);
                                                        } else {
                                                            return $matches[$options['url']];
                                                        }
                                                    } else {
                                                        return false;
                                                    }
                                                },
                                                'status' => function ($matches) {
                                                    if (isset($matches['status'])) {
                                                        return $matches['status'];
                                                    } else {
                                                        return false;
                                                    }
                                                }
                                            ]
                                        ]);

                                        // var_dump($result);die();

                                        // Write the search results to the cache
                                        if ($result && $cache = Yii::$app->getCache()) {
                                            $cache->set(md5('live-search'), [
                                                $request => [
                                                    $context => $result
                                                ]
                                            ], intval($this->module->cacheExpire));
                                        }

                                    }

                                    // Add the search results to the collection
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

        $output = [];
        if (is_array($results)) {
            foreach ($results as $result) {
                $result = array_values ($result);
                $output = ArrayHelper::crossMerging($output, $result);
            }
        }

        return $output;

    }

    /**
     * @return int
     */
    public static function flushCache() {
        if ($cache = Yii::$app->getCache()) {
            if ($cache->delete(md5('live-search'))) {
                return 1;
            } else {
                return 0;
            }
        } else {
            return -1;
        }
    }
}
