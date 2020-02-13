<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;
use wdmg\widgets\SelectInput;
use yii\bootstrap\Modal;
use yii\helpers\Url;

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
    <?php Pjax::begin([
        'id' => "searchIgnoredAjax",
        'timeout' => 5000
    ]); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'layout' => '{summary}<br\/>{items}<br\/>{summary}<br\/><div class="text-center">{pager}</div>',
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'pattern',
            [
                'attribute' => 'status',
                'format' => 'raw',
                'filter' => SelectInput::widget([
                    'model' => $searchModel,
                    'attribute' => 'status',
                    'items' => $searchModel->getStatusesList(true),
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
                    if ($data->status == $data::PATTERN_STATUS_ACTIVE) {
                        return '<div id="switcher-' . $data->id . '" data-value-current="' . $data->status . '" data-id="' . $data->id . '" data-toggle="button-switcher" class="btn-group btn-toggle"><button data-value="0" class="btn btn-xs btn-default">OFF</button><button data-value="1" class="btn btn-xs btn-primary">ON</button></div>';
                    } else {
                        return '<div id="switcher-' . $data->id . '" data-value-current="' . $data->status . '" data-id="' . $data->id . '" data-toggle="button-switcher" class="btn-group btn-toggle"><button data-value="0" class="btn btn-xs btn-danger">OFF</button><button data-value="1" class="btn btn-xs btn-default">ON</button></div>';
                    }
                }
            ],
            'created_at:datetime',
            'created_by',
            'updated_at:datetime',
            'updated_by',

            [
                'class' => 'yii\grid\ActionColumn',
                'header' => Yii::t('app/modules/search', 'Actions'),
                'contentOptions' => [
                    'class' => 'text-center'
                ],
                'visibleButtons' => [
                    'update' => true,
                    'delete' => true,
                    'view' => false,
                ],
                'buttons'=> [
                    'update' => function($url, $data, $key) {
                        return Html::a('<span class="glyphicon glyphicon-pencil"></span>',
                            Url::toRoute(['ignored/update', 'id' => $data['id']]), [
                                'title' => Yii::t('app/modules/search', 'Update pattern'),
                                'class' => 'add-ingored-pattern',
                                'data-toggle' => 'addIngoredForm',
                                'data-id' => $key,
                                'data-pjax' => '1'
                        ]);
                    },
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
    <div>
        <?= Html::a(Yii::t('app/modules/search', 'Add pattern'), ['ignored/create'], [
            'class' => 'btn btn-success pull-right add-ingored-pattern',
        ]) ?>
    </div>
    <?php Pjax::end(); ?>
</div>

<?php $this->registerJs(
    'var $container = $("#searchIgnoredAjax");
    var requestURL = window.location.href;
    if ($container.length > 0) {
        $container.delegate(\'[data-toggle="button-switcher"] button\', \'click\', function() {
            var id = $(this).parent(\'.btn-group\').data(\'id\');
            var value = $(this).data(\'value\');
             $.ajax({
                type: "POST",
                url: requestURL + \'?change=status\',
                dataType: \'json\',
                data: {\'id\': id, \'value\': value},
                complete: function(data) {
                    $.pjax.reload({type:\'POST\', container:\'#searchIgnoredAjax\'});
                }
             });
        });
    }
    ', \yii\web\View::POS_READY
); ?>

<?php $this->registerJs(<<< JS
    $('body').delegate('.add-ingored-pattern', 'click', function(event) {
        event.preventDefault();
        $.get(
            $(this).attr('href'),
            function (data) {
                $('#addIngoredForm .modal-body').html(data);
                $('#addIngoredForm').modal();
            }
        );
    });
JS
); ?>

<?php Modal::begin([
    'id' => 'addIngoredForm',
    'header' => '<h4 class="modal-title">'.Yii::t('app/modules/search', 'Add new pattern').'</h4>',
    'clientOptions' => [
        'show' => false
    ]
]); ?>
<?php Modal::end(); ?>

<?php echo $this->render('../_debug'); ?>
