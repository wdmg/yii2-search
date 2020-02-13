<?php

namespace wdmg\search\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use wdmg\search\models\SearchIgnored;
use wdmg\search\models\SearchIgnoredCommon;

/**
 * IgnoredController implements the CRUD actions for SearchIgnored model.
 */
class IgnoredController extends Controller
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
                    'index' => ['get', 'post'],
                    'create' => ['get', 'post'],
                    'update' => ['get', 'post'],
                    'delete' => ['post'],
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
     * Lists of all Ignored models.
     * @return mixed
     */
    public function actionIndex()
    {
        if (Yii::$app->request->isAjax) {
            if (Yii::$app->request->get('change') == "status") {
                if (Yii::$app->request->post('id', null)) {
                    $id = Yii::$app->request->post('id');
                    $status = Yii::$app->request->post('value', 0);
                    $model = $this->findModel(intval($id));
                    if ($model) {
                        $model->status = intval($status);
                        if ($model->update())
                            return true;
                        else
                            return false;
                    }
                }
            }
        }

        $searchModel = new SearchIgnoredCommon();
        $dataProvider = $searchModel->filter(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'module' => $this->module
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {

            if ($model->save())
                Yii::$app->getSession()->setFlash(
                    'success',
                    Yii::t('app/modules/search', 'Pattern has been successfully updated!')
                );
            else
                Yii::$app->getSession()->setFlash(
                    'danger',
                    Yii::t('app/modules/search', 'An error occurred while updating the pattern.')
                );

            return $this->redirect(['index']);
        }

        return $this->renderAjax('_form', [
            'model' => $model,
            'module' => $this->module
        ]);
    }

    public function actionCreate()
    {
        $model = new SearchIgnored();

        if ($model->load(Yii::$app->request->post())) {

            if ($model->save())
                Yii::$app->getSession()->setFlash(
                    'success',
                    Yii::t('app/modules/search', 'Pattern has been successfully added!')
                );
            else
                Yii::$app->getSession()->setFlash(
                    'danger',
                    Yii::t('app/modules/search', 'An error occurred while add the pattern.')
                );

            return $this->redirect(['index']);
        }

        return $this->renderAjax('_form', [
            'model' => $model,
            'module' => $this->module
        ]);
    }

    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete())
            Yii::$app->getSession()->setFlash(
                'success',
                Yii::t('app/modules/search', 'Pattern has been successfully deleted!')
            );
        else
            Yii::$app->getSession()->setFlash(
                'danger',
                Yii::t('app/modules/search', 'An error occurred while deleting the pattern.')
            );

        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = SearchIgnored::findOne($id)) !== null)
            return $model;

        throw new NotFoundHttpException(Yii::t('app/modules/search', 'The requested pattern does not exist.'));
    }
}
