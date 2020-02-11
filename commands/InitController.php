<?php

namespace wdmg\search\commands;

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
            if ($cache = Yii::$app->getCache()) {
                if ($cache->delete(md5('live-search'))) {
                    echo $this->ansiFormat("OK! Live search cache successfully cleaned.\n\n", Console::FG_GREEN);
                } else {
                    echo $this->ansiFormat("An error occurred while cleaning a live search cache.\n\n", Console::FG_RED);
                }
            } else {
                echo $this->ansiFormat("Error! Cache component not configured in application.\n\n", Console::FG_RED);
            }
        } else if ($selected == "4") {

            $count = 0;
            $isOk = true;
            echo $this->ansiFormat("\n\nStart reindex...\n");

            if (is_array($models = $module->supportModels)) {
                foreach ($models as $context => $support) {
                    if (isset($support['class']) && isset($support['options'])) {

                        $class = $support['class'];
                        $options = $support['options'];

                        if (class_exists($class)) {
                            if ($model = new $class()) {
                                if ($model instanceof \yii\db\ActiveRecord) {

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
            }

            if ($isOk) {
                echo $this->ansiFormat("OK! Search index successfully rebuilding. Added $count documents.\n\n", Console::FG_GREEN);
            } else {
                echo $this->ansiFormat("An error occurred while rebuild a search index.\n\n", Console::FG_RED);
            }

        } else if ($selected == "5") {

            $isOk = true;
            $searchIndex = new SearchIndex();
            if (!($searchIndex->deleteAll()))
                $isOk = false;

            $searchKeywords = new SearchKeywords();
            if (!($searchKeywords->deleteAll()))
                $isOk = false;

            $search = new Search();
            if (!($search->deleteAll()))
                $isOk = false;

            if ($isOk) {
                echo $this->ansiFormat("OK! Search index successfully dropped.\n\n", Console::FG_GREEN);
            } else {
                echo $this->ansiFormat("An error occurred while dropping a search index.\n\n", Console::FG_RED);
            }

        } else {
            echo $this->ansiFormat("Error! Your selection has not been recognized.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "\n";
        return ExitCode::OK;
    }
}
