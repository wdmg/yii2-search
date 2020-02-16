<?php

namespace wdmg\search;

/**
 * Yii2 Search
 *
 * @category        Module
 * @version         1.0.5
 * @author          Alexsander Vyshnyvetskyy <alex.vyshnyvetskyy@gmail.com>
 * @link            https://github.com/wdmg/yii2-search
 * @copyright       Copyright (c) 2020 W.D.M.Group, Ukraine
 * @license         https://opensource.org/licenses/MIT Massachusetts Institute of Technology (MIT) License
 *
 */

use wdmg\search\models\Search;
use Yii;
use wdmg\base\BaseModule;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;

/**
 * RSS-feed module definition class
 */
class Module extends BaseModule
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'wdmg\search\controllers';

    /**
     * {@inheritdoc}
     */
    public $defaultRoute = "list/index";

    /**
     * @var string, the name of module
     */
    public $name = "Search";

    /**
     * @var string, the description of module
     */
    public $description = "Site search";

    /**
     * @var array list of supported models for live search or/and indexation
     */
    public $supportModels = [
        'news' => [
            'class' => 'wdmg\news\models\News',
            'indexing' => [
                'on_insert' => true,
                'on_update' => true,
                'on_delete' => true
            ],
            'options' => [
                'title' => 'title',
                'url' => 'url',
                'fields' => [
                    'title',
                    'keywords',
                    'description',
                    'content'
                ],
                'conditions' => [
                    'status' => 1
                ]
            ]
        ],
        'pages' => [
            'class' => 'wdmg\pages\models\Pages',
            'indexing' => [
                'on_insert' => true,
                'on_update' => true,
                'on_delete' => true
            ],
            'options' => [
                'title' => 'title',
                'url' => 'url',
                'fields' => [
                    'title',
                    'keywords',
                    'description',
                    'content'
                ],
                'conditions' => [
                    'status' => 1
                ]
            ]
        ],
    ];

    /**
     * @var int live search cache lifetime, `0` - for not use cache
     */
    public $cacheExpire = 86400; // 86400 1 day.

    /**
     * @var array indexation options
     */
    public $indexingOptions = [
        'processing' => 'phpMorphy', //  Set `phpMorphy` or `LinguaStem` (not realized et)
        'language' => 'ru-RU', // Support 'ru-RU', 'uk-UA', 'de-DE'
        'analyze_by' => 'relevance',
        'max_execution_time' => 0, // max execution time in sec. for indexing process
        'memory_limit' => null, // max operating memory in Mb for indexing process
        'max_words' => 50,
    ];

    /**
     * @var array text analyzer options, see \wdmg\helpers\TextAnalyzer
     */
    public $analyzerOptions = [
        'min_length' => 3,
        'stop_words' => [],
        'weights' => []
    ];

    /**
     * @var array build search snippet options
     */
    public $snippetOptions = [
        'max_words_before' => 6,
        'max_words_after' => 4,
        'bolder_tag' => 'strong',
        'max_length' => 255,
        'delimiter' => '…'
    ];

    /**
     * @var int search accuracy
     */
    public $searchAccuracy = 90;

    /**
     * @var string the module version
     */
    private $version = "1.0.5";

    /**
     * @var integer, priority of initialization
     */
    private $priority = 5;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // Set version of current module
        $this->setVersion($this->version);

        // Set priority of current module
        $this->setPriority($this->priority);

    }

    /**
     * {@inheritdoc}
     */
    public function dashboardNavItems($createLink = false)
    {
        $items = [
            'label' => $this->name,
            'url' => '#',
            'icon' => 'fa-search',
            'active' => in_array(\Yii::$app->controller->module->id, [$this->id]),
            'items' => [
                [
                    'label' => Yii::t('app/modules/search', 'Search index'),
                    'url' => [$this->routePrefix . '/search/list/'],
                    'active' => (in_array(\Yii::$app->controller->module->id, ['search']) &&  Yii::$app->controller->id == 'list'),
                ],
                [
                    'label' => Yii::t('app/modules/search', 'Ignored list'),
                    'url' => [$this->routePrefix . '/search/ignored/'],
                    'active' => (in_array(\Yii::$app->controller->module->id, ['search']) &&  Yii::$app->controller->id == 'ignored'),
                ],
            ]
        ];

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap($app)
    {
        parent::bootstrap($app);

        if (isset(Yii::$app->params["search.supportModels"]))
            $this->supportModels = Yii::$app->params["search.supportModels"];

        if (isset(Yii::$app->params["search.cacheExpire"]))
            $this->cacheExpire = Yii::$app->params["search.cacheExpire"];

        if (!isset($this->supportModels))
            throw new InvalidConfigException("Required module property `supportModels` isn't set.");

        if (!isset($this->cacheExpire))
            throw new InvalidConfigException("Required module property `cacheExpire` isn't set.");

        if (!is_array($this->supportModels))
            throw new InvalidConfigException("Module property `supportModels` must be array.");

        if (!is_integer($this->cacheExpire))
            throw new InvalidConfigException("Module property `cacheExpire` must be integer.");

        // Attach to events of create/change/remove of models for the search indexing
        if (!($app instanceof \yii\console\Application)) {
            if (is_array($models = $this->supportModels)) {
                foreach ($models as $context => $support) {
                    if (isset($support['class']) && isset($support['options']) && isset($support['indexing'])) {

                        $class = $support['class'];
                        $options = $support['options'];
                        $indexing = $support['indexing'];

                        if (class_exists($class)) {

                            $model = new $class();
                            $search = new Search();

                            if ($indexing['on_insert']) {
                                \yii\base\Event::on($class, $model::EVENT_AFTER_INSERT, function ($event) use ($search, $context, $options) {
                                    $search->indexing($event->sender, $context, $options, 1);
                                });
                            }

                            if ($indexing['on_update']) {
                                \yii\base\Event::on($class, $model::EVENT_AFTER_UPDATE, function ($event) use ($search, $context, $options) {
                                    $search->indexing($event->sender, $context, $options, 2);
                                });
                            }

                            if ($indexing['on_delete']) {
                                \yii\base\Event::on($class, $model::EVENT_AFTER_DELETE, function ($event) use ($search, $context, $options) {
                                    $search->indexing($event->sender, $context, $options, 3);
                                });
                            }

                        }

                    }
                }
            }
        }
    }

    public function generateSnippets($content = null, $keywords = null, $options = [], $withBase = true)
    {

        $snippets = [];
        if (!empty($content) && is_array($keywords)) {

            foreach ($keywords as $keyword) {
                $count_in = 0;

                $min_words_before = 2;
                $max_words_before = (isset($options['before'])) ? $options['before'] : 6;
                $words_before = $min_words_before . ',' . intval($max_words_before);

                $min_words_after = 2;
                $max_words_after = (isset($options['after'])) ? $options['after'] : 4;
                $words_after = $min_words_after . ',' . intval($max_words_after);

                preg_match_all('/(?:\w+){' . $words_before . '}\s+((?:\b' . $keyword . '*?)\w+)\s+(?:\w+){' . $words_after . '}/iu', $content, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches as $key => $match) {


                    foreach ($match as $part) {

                        if ($count_in >= 3)
                            continue;

                        if ($tag = $options['tag'])
                            $snippet = preg_replace('/(?:\b' . $keyword . '*?)\w+/iu', Html::tag($tag, '$0'), $part[0]);

                        //$snippet = preg_replace('/(?:\b' . $keyword . '*?)\w+/iu', Html::tag($tag, '$0', (isset($options['tag_options'])) ? $options['tag_options'] : false), $part[0]);

                        if (!empty($snippet)) {
                            if (empty($snippets)) {
                                
                                if ($withBase)
                                    $snippets[$keyword][] = mb_convert_case(mb_substr(trim($snippet), 0, 1), MB_CASE_TITLE) . mb_substr(trim($snippet), 1);
                                else
                                    $snippets[] = mb_convert_case(mb_substr(trim($snippet), 0, 1), MB_CASE_TITLE) . mb_substr(trim($snippet), 1);

                            } else {

                                if ($withBase)
                                    $snippets[$keyword][] = trim($snippet);
                                else
                                    $snippets[] = trim($snippet);

                            }

                            $count_in++;
                        }

                    }
                }
            }
        }

        return $snippets;
    }
}