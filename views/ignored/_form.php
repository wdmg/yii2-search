<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use wdmg\widgets\SelectInput;

/* @var $this yii\web\View */
/* @var $model wdmg\search\models\SearchIgnored */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="ignored-pattern-form">
    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'pattern')->textInput() ?>

    <?= $form->field($model, 'status')->widget(SelectInput::class, [
        'items' => $model->getStatusesList(),
        'options' => [
            'class' => 'form-control'
        ]
    ]); ?>
    <div class="modal-footer">
        <?= Html::submitButton(Yii::t('app/modules/search', 'Save'), ['class' => 'btn btn-success pull-right']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
