<?php

namespace wdmg\search;

/**
 * Yii2 Search
 *
 * @category        Module
 * @version         1.0.0
 * @author          Alexsander Vyshnyvetskyy <alex.vyshnyvetskyy@gmail.com>
 * @link            https://github.com/wdmg/yii2-search
 * @copyright       Copyright (c) 2020 W.D.M.Group, Ukraine
 * @license         https://opensource.org/licenses/MIT Massachusetts Institute of Technology (MIT) License
 *
 */

use Yii;
use wdmg\base\BaseModule;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

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
     * @var array list of supported models for live search and indexation
     */
    public $supportModels = [
        'news' => [
            'class' => 'wdmg\news\models\News',
            'fields' => [
                'title',
                'keywords',
                'description',
                'content'
            ],
        ],
        'pages' => [
            'class' => 'wdmg\pages\models\Pages',
            'fields' => [
                'title',
                'keywords',
                'description',
                'content'
            ]
        ],
    ];

    /**
     * @var int live search cache lifetime, `0` - for not use cache
     */
    public $cacheExpire = 86400; // 1 day.

    /**
     * @var string the module version
     */
    private $version = "1.0.0";

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
            'icon' => 'fa-search',
            'url' => [$this->routePrefix . '/'. $this->id],
            'active' => (in_array(\Yii::$app->controller->module->id, [$this->id]) &&  Yii::$app->controller->id == 'list'),
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
        /*if (!($app instanceof \yii\console\Application)) {
            if ($cache = $app->getCache()) {
                if (is_array($models = $this->supportModels)) {
                    foreach ($models as $name => $class) {
                        if (class_exists($class)) {
                            $model = new $class();
                            \yii\base\Event::on($class, $model::EVENT_AFTER_INSERT, function ($event) use ($cache) {
                                $cache->delete(md5('rss-feed'));
                            });
                            \yii\base\Event::on($class, $model::EVENT_AFTER_UPDATE, function ($event) use ($cache) {
                                $cache->delete(md5('rss-feed'));
                            });
                            \yii\base\Event::on($class, $model::EVENT_AFTER_DELETE, function ($event) use ($cache) {
                                $cache->delete(md5('rss-feed'));
                            });
                        }
                    }
                }
            }
        }*/
    }

}