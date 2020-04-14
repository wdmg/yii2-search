<?php

namespace wdmg\search\commands;

use wdmg\search\models\LiveSearch;
use wdmg\search\models\Search;
use wdmg\search\models\SearchIndex;
use wdmg\search\models\SearchKeywords;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class InitController extends Controller
{
    /**
     * @inheritdoc
     */
    public $choice = null;

    /**
     * @inheritdoc
     */
    public $defaultAction = 'index';

    public function options($actionID)
    {
        return ['choice', 'color', 'interactive', 'help'];
    }

    public function actionIndex($params = null)
    {
        $module = Yii::$app->controller->module;
        $version = $module->version;
        $welcome =
            '╔════════════════════════════════════════════════╗'. "\n" .
            '║                                                ║'. "\n" .
            '║             SEARCH MODULE, v.'.$version.'             ║'. "\n" .
            '║          by Alexsander Vyshnyvetskyy           ║'. "\n" .
            '║         (c) 2020 W.D.M.Group, Ukraine          ║'. "\n" .
            '║                                                ║'. "\n" .
            '╚════════════════════════════════════════════════╝';
        echo $name = $this->ansiFormat($welcome . "\n\n", Console::FG_GREEN);
        echo "Select the operation you want to perform:\n";
        echo "  1) Apply all module migrations\n";
        echo "  2) Revert all module migrations\n";
        echo "  3) Flush live search cache\n";
        echo "  4) Rebuild search index\n";
        echo "  5) Drop search index\n";
        echo "Your choice: ";

        if(!is_null($this->choice))
            $selected = $this->choice;
        else
            $selected = trim(fgets(STDIN));

        if ($selected == "1") {
            Yii::$app->runAction('migrate/up', ['migrationPath' => '@vendor/wdmg/yii2-search/migrations', 'interactive' => true]);
        } else if ($selected == "2") {
            Yii::$app->runAction('migrate/down', ['migrationPath' => '@vendor/wdmg/yii2-search/migrations', 'interactive' => true]);
        } else if ($selected == "3") {
            if (is_int($resp = LiveSearch::flushCache())) {
                if ($resp === 1) {
                    echo $this->ansiFormat("\nOK! Live search cache successfully cleaned.\n", Console::FG_GREEN);
                } elseif ($resp === 0) {
                    echo $this->ansiFormat("\nAn error occurred while cleaning a live search cache.\n", Console::FG_RED);
                }
            } else {
                echo $this->ansiFormat("\nError! Cache component not configured in the application.\n", Console::FG_RED);
            }
        } else if ($selected == "4") {

            // Checking UrlManager component
            if ($urlManager = Yii::$app->getUrlManager()) {
                if (!($urlManager->hostInfo))
                    echo $this->ansiFormat("\nError! `hostInfo` must be configured in the UrlManager component.\n", Console::FG_RED);

                if (!($urlManager->baseUrl))
                    echo $this->ansiFormat("\nError! `baseUrl` must be configured in the UrlManager component.\n", Console::FG_RED);

            } else {
                echo $this->ansiFormat("\nError! UrlManager component not configured in the application.\n", Console::FG_RED);
            }

            $count = 0;
            $isOk = true;
            echo $this->ansiFormat("\nStart rebuild index...\n");

            if (is_null($max_execution_time = $this->module->indexingOptions['max_execution_time']))
                $max_execution_time = 30;

            ini_set('max_execution_time', intval($max_execution_time));

            if (!is_null($memory_limit = $this->module->indexingOptions['memory_limit'])) {
                ini_set('memory_limit', (intval($memory_limit) - 1) . "M");
            }

            // Indexing process
            if (is_array($models = $module->supportModels)) {
                foreach ($models as $context => $support) {
                    if (isset($support['class']) && isset($support['options'])) {

                        $class = $support['class'];
                        $options = $support['options'];

                        // If class of model exist
                        if (class_exists($class)) {

                            $model = new $class();

                            // If module is loaded
                            if ($model->getModule()) {

                                // Create model query
                                $query = $model->find();

                                // Checking, the model may not meet the conditions that allow displaying in search results
                                if (isset($options['conditions'])) {
                                    $query->andWhere($options['conditions']);
                                }

                                $search = new Search();
                                if ($items = $query->limit(100)->all()) {
                                    foreach ($items as $item) {

                                        $time = time();
                                        echo $this->ansiFormat("    - indexing `$item->title` ($context)", Console::FG_YELLOW);
                                        $code = $search->indexing($item, $context, $options);
                                        if ($code == 1) {
                                            $time = time() - $time;
                                            echo $this->ansiFormat(" - ok, code: $code, time: $time sec.\n", Console::FG_GREEN);
                                            $count++;
                                        } elseif ($code == 2 || $code == 0) {
                                            $time = time() - $time;
                                            echo $this->ansiFormat(" - skip, code: $code, time: $time sec.\n", Console::FG_YELLOW);
                                        } else {
                                            $time = time() - $time;
                                            echo $this->ansiFormat(" - fail, code: $code, time: $time sec.\n", Console::FG_RED);
                                            $isOk = false;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($isOk) {
                echo $this->ansiFormat("\nOK! Search index successfully rebuild. Added $count items.\n", Console::FG_GREEN);
            } else {
                echo $this->ansiFormat("\nAn error occurred while rebuild a search index.\n", Console::FG_RED);
            }

        } else if ($selected == "5") {

            $isOk = true;
            if (!(Search::deleteAll()))
                $isOk = false;

            if ($isOk) {
                echo $this->ansiFormat("\nOK! Search index successfully dropped.\n", Console::FG_GREEN);
            } else {
                echo $this->ansiFormat("\nAn error occurred while dropping a search index.\n", Console::FG_RED);
            }

        } else {
            echo $this->ansiFormat("\nError! Your selection has not been recognized.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "\n";
        return ExitCode::OK;
    }
}
