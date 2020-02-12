<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;
use wdmg\widgets\SelectInput;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app/modules/search', 'Ignored list');
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="page-header">
    <h1>
        <?= Html::encode($this->title) ?> <small class="text-muted pull-right">[v.<?= $module->version ?>]</small>
    </h1>
</div>
<div class="search-ignored">
    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'layout' => '{summary}<br\/>{items}<br\/>{summary}<br\/><div class="text-center">{pager}</div>',
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'pattern',
            'status',
            'created_at:datetime',
            'created_by',
            'updated_at:datetime',
            'updated_by',

            [
                'class' => 'yii\grid\ActionColumn',
                'header' => Yii::t('app/modules/search', 'Actions'),
                'contentOptions' => [
                    'class' => 'text-center'
                ]
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

    </div>
    <?php Pjax::end(); ?>
</div>

<?php echo $this->render('../_debug'); ?>
