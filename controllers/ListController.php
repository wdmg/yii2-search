<?php

namespace wdmg\search\controllers;

use wdmg\search\models\LiveSearch;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use wdmg\search\models\Search;
use wdmg\search\models\SearchCommon;
use wdmg\search\models\SearchIndex;
use wdmg\search\models\SearchKeywords;

/**
 * ListController implements the CRUD actions for Search model.
 */
class ListController extends Controller
{

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['get'],
                    'rebuild' => ['post'],
                    'delete' => ['post']
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'roles' => ['admin'],
                        'allow' => true
                    ],
                ],
            ],
        ];

        // If auth manager not configured use default access control
        if(!Yii::$app->authManager) {
            $behaviors['access'] = [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'roles' => ['@'],
                        'allow' => true
                    ],
                ]
            ];
        }

        return $behaviors;
    }

    /**
     * Lists of all Search models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SearchCommon();
        $dataProvider = $searchModel->filter(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'module' => $this->module
        ]);
    }

    /**
     * Deletes an existing Search model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model->delete())
            Yii::$app->getSession()->setFlash(
                'success',
                Yii::t(
                    'app/modules/search',
                    'OK! Search item `{name}` successfully deleted.',
                    [
                        'name' => $model->title
                    ]
                )
            );
        else
            Yii::$app->getSession()->setFlash(
                'danger',
                Yii::t(
                    'app/modules/search',
                    'An error occurred while deleting a search item `{name}`.',
                    [
                        'name' => $model->title
                    ]
                )
            );

        return $this->redirect(['index']);
    }

    public function actionDrop()
    {
        if (Search::deleteAll())
            Yii::$app->getSession()->setFlash(
                'success',
                Yii::t('app/modules/search', 'OK! Search index successfully dropped.')
            );
        else
            Yii::$app->getSession()->setFlash(
                'danger',
                Yii::t('app/modules/search', 'An error occurred while dropping a search index.')
            );

        return $this->redirect(['index']);
    }

    public function actionClear()
    {
        if (is_int($resp = LiveSearch::flushCache())) {
            if ($resp === 1)
                Yii::$app->getSession()->setFlash(
                    'success',
                    Yii::t('app/modules/search', 'OK! Live search cache successfully cleaned.')
                );
            elseif ($resp === 0)
                Yii::$app->getSession()->setFlash(
                    'danger',
                    Yii::t('app/modules/search', 'An error occurred while cleaning a live search cache.')
                );
        } else {
            Yii::$app->getSession()->setFlash(
                'danger',
                Yii::t('app/modules/search', 'Error! Cache component not configured in the application.')
            );
        }

        return $this->redirect(['index']);
    }

    public function actionRebuild()
    {
        $count = 0;
        //$reports = [];
        $isOk = true;

        if (is_null($max_execution_time = $this->module->indexingOptions['max_execution_time']))
            $max_execution_time = 30;

        ini_set('max_execution_time', intval($max_execution_time));

        if (!is_null($memory_limit = $this->module->indexingOptions['memory_limit'])) {
            ini_set('memory_limit', (intval($memory_limit) - 1) . "M");
        }

        // Ignore user canceled page loading
        ignore_user_abort();

        // We say that the connection must be closed
        Yii::$app->getResponse()->getHeaders()->add('Connection', 'close');

        // Indexing process
        if (is_array($models = $this->module->supportModels)) {
            $i = 0;
            $report[$i][] = "Start rebuild index...";
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
                                        $i++;
                                        $time = time();
                                        //$reports[] = "    - indexing `$item->title` ($context)";
                                        $code = $search->indexing($item, $context, $options);
                                        if ($code == 1) {
                                            $time = time() - $time;
                                            //$reports[] = " - ok, code: $code, time: $time sec.\n";
                                            $count++;
                                        } elseif ($code == 2 || $code == 0) {
                                            $time = time() - $time;
                                            //$reports[] = " - skip, code: $code, time: $time sec.\n";
                                        } else {
                                            $time = time() - $time;
                                            //$reports[] = " - fail, code: $code, time: $time sec.\n";
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

        //return json_encode($reports);

        if ($isOk) {
            Yii::$app->getSession()->setFlash(
                'success',
                Yii::t('app/modules/search', 'OK! Search index successfully rebuild. Added {count} items.', [
                    'count' => $count
                ])
            );
        } else {
            Yii::$app->getSession()->setFlash(
                'danger',
                Yii::t('app/modules/search', 'An error occurred while rebuild a search index.')
            );
        }

        return $this->redirect(['index']);
    }


    /**
     * Finds the Search model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return search model item
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Search::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException(Yii::t('app/modules/search', 'The requested search item does not exist.'));
    }
}
