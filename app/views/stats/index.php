<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\StatsFilter $filter */
/** @var array $requestsChart */
/** @var array $browsersChart */
/** @var yii\data\ArrayDataProvider $dataProvider */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\grid\GridView;
use yii\helpers\Json;
use yii\web\View;

$this->title = 'Статистика логов nginx';
$this->params['breadcrumbs'][] = $this->title;

// Chart.js как внешний asset (CDN).
$this->registerJsFile(
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    ['position' => View::POS_HEAD]
);
?>
<div class="stats-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'action' => ['index'],
        'options' => ['class' => 'row g-3 align-items-end mb-4'],
    ]); ?>
        <div class="col-md-2">
            <?= $form->field($filter, 'dateFrom')->input('date') ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($filter, 'dateTo')->input('date') ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($filter, 'os')->dropDownList($filter->osOptions(), ['prompt' => 'Все']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($filter, 'arch')->dropDownList($filter->archOptions(), ['prompt' => 'Все']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($filter, 'bots')->dropDownList($filter->botsOptions()) ?>
        </div>
        <div class="col-md-2">
            <?= Html::submitButton('Применить', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Сброс', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
    <?php ActiveForm::end(); ?>

    <div class="row mb-4">
        <div class="col-lg-6">
            <h5>Число запросов по дням</h5>
            <canvas id="chart-requests" height="140"></canvas>
        </div>
        <div class="col-lg-6">
            <h5>Доля топ-3 браузеров, %</h5>
            <canvas id="chart-browsers" height="140"></canvas>
        </div>
    </div>

    <h5>Сводка по дням</h5>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-striped table-bordered'],
        'columns' => [
            [
                'attribute' => 'date',
                'label' => 'Дата',
            ],
            [
                'attribute' => 'cnt',
                'label' => 'Число запросов',
            ],
            [
                'attribute' => 'top_url',
                'label' => 'Самый популярный URL',
                'format' => 'ntext',
            ],
            [
                'attribute' => 'top_browser',
                'label' => 'Самый популярный браузер',
            ],
        ],
    ]); ?>
</div>

<?php
$requestsJson = Json::encode($requestsChart);
$browsersJson = Json::encode($browsersChart);

$js = <<<JS
(function () {
    var requests = {$requestsJson};
    var browsers = {$browsersJson};
    var palette = ['#0d6efd', '#dc3545', '#198754', '#ffc107', '#6f42c1'];

    var reqCanvas = document.getElementById('chart-requests');
    if (reqCanvas && requests.labels.length) {
        new Chart(reqCanvas, {
            type: 'line',
            data: {
                labels: requests.labels,
                datasets: [
                    {label: 'Люди', data: requests.humans, borderColor: palette[0], backgroundColor: palette[0], tension: 0.2},
                    {label: 'Боты', data: requests.bots, borderColor: palette[1], backgroundColor: palette[1], tension: 0.2}
                ]
            },
            options: {responsive: true, scales: {y: {beginAtZero: true}}}
        });
    }

    var brCanvas = document.getElementById('chart-browsers');
    if (brCanvas && browsers.labels.length) {
        var datasets = browsers.series.map(function (s, i) {
            var c = palette[i % palette.length];
            return {label: s.browser, data: s.data, borderColor: c, backgroundColor: c, tension: 0.2};
        });
        new Chart(brCanvas, {
            type: 'line',
            data: {labels: browsers.labels, datasets: datasets},
            options: {
                responsive: true,
                scales: {y: {beginAtZero: true, max: 100, ticks: {callback: function (v) { return v + '%'; }}}}
            }
        });
    }
})();
JS;
$this->registerJs($js, View::POS_END);
?>
