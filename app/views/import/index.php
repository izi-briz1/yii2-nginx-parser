<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\UploadForm $model */
/** @var app\models\File|null $file */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Загрузка лога nginx';
$this->params['breadcrumbs'][] = $this->title;

$JS = <<<'JS'
(function(box, url, bar, statusEl, processedEl, totalEl, timer){
    box = document.getElementById('import-progress');

    if(!box){
        return;
    }

    url = box.getAttribute('data-url');
    bar = box.querySelector('.js-bar');
    statusEl = box.querySelector('.js-status');
    processedEl = box.querySelector('.js-processed');
    totalEl = box.querySelector('.js-total');
    timer = null;

    function render(d, percent){
        percent = Math.round(d.processed / d.total * 100);

        bar.style.width = percent + '%';
        bar.textContent = percent + '%';
        statusEl.textContent = d.status;
        processedEl.textContent = d.processed;
        totalEl.textContent = d.total;

        if(d.status === 'done'){
            bar.classList.add('bg-success');
            statusEl.className = 'js-status badge bg-success';
            clearInterval(timer);
        }else if(d.status === 'failed'){
            bar.classList.add('bg-danger');
            statusEl.className = 'js-status badge bg-danger';
            clearInterval(timer);
        }
    }

    function poll(){
        fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function(r){
                return r.json();
            })
            .then(render)
            .catch(function(reason){
                console.error(reason);
            });
    }

    poll();
    timer = setInterval(poll, 1000);
})();
JS;

$this->registerJs($JS, View::POS_END);

?>
<div class="import-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data'], 'action' => ['index']]); ?>
        <?= $form->field($model, 'logFile')->fileInput()->hint('Допустимы файлы .log и .txt, до 256 МБ.') ?>

        <div class="form-group">
            <?= Html::submitButton('Загрузить', ['class' => 'btn btn-primary']) ?>
        </div>
    <?php ActiveForm::end(); ?>

    <?php if ($file): ?>
        <hr>
        <h4>Прогресс импорта</h4>
        <div id="import-progress"
             data-url="<?= Url::to(['progress', 'id' => $file->id]) ?>"
             data-status="<?= Html::encode($file->status) ?>">
            <p class="mb-1">
                Файл #<?= $file->id ?> - <span class="js-status badge bg-secondary"><?= Html::encode($file->status) ?></span>
            </p>
            <div class="progress" style="height: 24px">
                <div class="progress-bar js-bar" style="width: 0">0%</div>
            </div>
            <p class="mt-1 text-muted">
                <span class="js-processed">0</span> из
                <span class="js-total"><?= (int) $file->total_lines ?></span> строк
            </p>
        </div>
    <?php endif; ?>
</div>
