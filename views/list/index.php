<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;
use wdmg\widgets\SelectInput;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app/modules/search', 'Search index');
$this->params['breadcrumbs'][] = $this->title;

if (isset(Yii::$app->translations) && class_exists('\wdmg\translations\FlagsAsset')) {
    $bundle = \wdmg\translations\FlagsAsset::register(Yii::$app->view);
} else {
    $bundle = false;
}

?>
<div class="page-header">
    <h1>
        <?= Html::encode($this->title) ?> <small class="text-muted pull-right">[v.<?= $module->version ?>]</small>
    </h1>
</div>
<div class="search-index">
    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'layout' => '{summary}<br\/>{items}<br\/>{summary}<br\/><div class="text-center">{pager}</div>',
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'title',
                'format' => 'raw',
                'label' => Yii::t('app/modules/search', 'Index item'),
                'value' => function($model) {
                    $output = Html::tag('strong', $model->title);
                    if ($model->url) {
                        $output .= '<br/>' . Html::a($model->url, $model->url, [
                            'target' => '_blank',
                            'data-pjax' => 0
                        ]);
                    }
                    return $output;
                }
            ],
            [
                'attribute' => 'context',
                'format' => 'html',
                'filter' => SelectInput::widget([
                    'model' => $searchModel,
                    'attribute' => 'context',
                    'items' => $searchModel->getContextsList(true),
                    'options' => [
                        'class' => 'form-control'
                    ]
                ]),
                'headerOptions' => [
                    'class' => 'text-center'
                ],
                'contentOptions' => [
                    'class' => 'text-center'
                ],
                'value' => function($data) {
                    return $data->context;
                }
            ],
            [
                'label' => Yii::t('app/modules/search', 'Info'),
                'format' => 'raw',
                'value' => function($model) {
                    $output = '<ul class="list-unstyled">';
                    $output .= '<li><em>Keywords:&nbsp;</em>'.$model->getKeywordsCount().'</li>';
                    $output .= '<li><em>Snippets:&nbsp;</em>'.$model->getSnippetsCount().'</li>';
                    $output .= '</ul>';
                    return $output;
                }
            ],
            [
                'attribute' => 'locale',
                'label' => Yii::t('app/modules/search','Language'),
                'format' => 'raw',
                'filter' => false,
                'headerOptions' => [
                    'class' => 'text-center',
                    'style' => 'min-width:96px;'
                ],
                'contentOptions' => [
                    'class' => 'text-center'
                ],
                'value' => function($data) use ($bundle) {

                    if (isset(Yii::$app->translations)) {

                        $locale = Yii::$app->translations->parseLocale($data->locale, Yii::$app->language);

                        if (!($country = $locale['domain']))
                            $country = '_unknown';

                        return \yii\helpers\Html::img($bundle->baseUrl . '/flags-iso/flat/24/' . $country . '.png', [
                            'alt' => $locale['name']
                        ]);

                    } else {

                        if (extension_loaded('intl'))
                            return mb_convert_case(trim(\Locale::getDisplayLanguage($data->locale, Yii::$app->language)), MB_CASE_TITLE, "UTF-8");
                        else
                            return $data->locale;

                    }

                    return null;
                }
            ],
            'created_at',
            'updated_at',
            [
                'class' => 'yii\grid\ActionColumn',
                'header' => Yii::t('app/modules/search', 'Actions'),
                'contentOptions' => [
                    'class' => 'text-center'
                ],
                'visibleButtons' => [
                    'update' => false,
                    'view' => false,
                    'delete' => true
                ],
                'urlCreator' => function ($action, $model, $key, $index) {

                    if ($action === 'delete')
                        return \yii\helpers\Url::toRoute(['list/delete', 'id' => $key]);

                }
            ],
        ],
        'pager' => [
            'options' => [
                'class' => 'pagination',
            ],
            'maxButtonCount' => 5,
            'activePageCssClass' => 'active',
            'prevPageCssClass' => '',
            'nextPageCssClass' => '',
            'firstPageCssClass' => 'previous',
            'lastPageCssClass' => 'next',
            'firstPageLabel' => Yii::t('app/modules/search', 'First page'),
            'lastPageLabel'  => Yii::t('app/modules/search', 'Last page'),
            'prevPageLabel'  => Yii::t('app/modules/search', '&larr; Prev page'),
            'nextPageLabel'  => Yii::t('app/modules/search', 'Next page &rarr;')
        ],
    ]); ?>
    <hr/>
    <div class="btn-group">
        <?= Html::a(Yii::t('app/modules/search', 'Drop index'), ['list/drop'], [
            'class' => 'btn btn-danger',
            'data-confirm' => Yii::t('app/modules/search', 'This action will irreversibly and completely clear the search index. Still want to continue?')
        ]) ?>
        <?= Html::a(Yii::t('app/modules/search', 'Clear cache'), ['list/clear'], ['class' => 'btn btn-warning']) ?>
        <?= Html::a(Yii::t('app/modules/search', 'Rebuild index'), ['list/rebuild'], [
            'class' => 'btn btn-info',
            'data-method' => 'POST',
            'data-confirm' => Yii::t('app/modules/search', 'We strongly recommend that you build your index through a console. Still want to continue?')
        ]) ?>
    </div>
    <?php Pjax::end(); ?>
</div>

<?php echo $this->render('../_debug'); ?>
