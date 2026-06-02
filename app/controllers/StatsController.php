<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\StatsFilter;
use app\models\StatsRepository;
use yii\data\ArrayDataProvider;
use yii\web\Controller;

/**
 * Страница статистики: графики, фильтры и таблица по логам nginx.
 */
class StatsController extends Controller
{
    public function actionIndex(): string
    {
        $filter = new StatsFilter();
        $filter->load($this->request->getQueryParams());
        $filter->validate();

        $repository = new StatsRepository($filter);

        $dataProvider = new ArrayDataProvider([
            'allModels' => $repository->tableByDay(),
            'sort' => [
                'attributes' => ['date', 'cnt', 'top_url', 'top_browser'],
                'defaultOrder' => [
                    'date' => SORT_ASC
                ],
            ],
            'pagination' => ['pageSize' => 31],
        ]);

        return $this->render('index', [
            'filter' => $filter,
            'requestsChart' => $repository->requestsByDay(),
            'browsersChart' => $repository->topBrowsersShareByDay(),
            'dataProvider' => $dataProvider,
        ]);
    }
}
