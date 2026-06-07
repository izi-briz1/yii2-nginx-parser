<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\db\Expression;
use yii\db\Query;

/**
 * Агрегирующие запросы для страницы статистики
 *
 * Каждый результат кэшируется в Redis на 5 минут; ключ зависит от параметров фильтра
 */
class StatsRepository
{
    /**
     * @var int Время жизни кеша
     */
    private const CACHE_TTL = 300; # 5 минут

    /**
     * @var StatsFilter
     */
    private readonly StatsFilter $filter;

    /**
     * @param StatsFilter $filter
     */
    public function __construct(StatsFilter $filter)
    {
        $this->filter = $filter;
    }

    /**
     * График 1: по дням число запросов отдельно для людей и ботов
     *
     * @return array{labels:string[],humans:int[],bots:int[]}
     */
    public function requestsByDay(): array
    {
        if ($this->filter->hasErrors()) {
            return [
                'labels' => [],
                'humans' => [],
                'bots' => []
            ];
        }

        return $this->cached('requestsByDay', function (): array {
            $query = (new Query())
                ->select([
                    'd' => new Expression('DATE(datetime)'),
                    'humans' => new Expression('SUM(is_bot = 0)'),
                    'bots' => new Expression('SUM(is_bot = 1)'),
                ])
                ->from('logs')
                ->groupBy('d')
                ->orderBy('d');
            $this->filter->applyTo($query);

            $labels = $humans = $bots = [];
            foreach ($query->all() as $row) {
                $labels[] = $row['d'];
                $humans[] = (int) $row['humans'];
                $bots[] = (int) $row['bots'];
            }

            return ['labels' => $labels, 'humans' => $humans, 'bots' => $bots];
        });
    }

    /**
     * График 2: по дням доля (%) трёх самых популярных браузеров
     *
     * @return array{labels:string[],series:array<array{browser:string,data:float[]}>}
     */
    public function topBrowsersShareByDay(): array
    {
        if ($this->filter->hasErrors()) {
            return ['labels' => [], 'series' => []];
        }

        return $this->cached('topBrowsersShareByDay', function (): array {
            $topBrowsers = $this->topBrowsers(3);
            if (empty($topBrowsers)) {
                return ['labels' => [], 'series' => []];
            }

            # Всего запросов по дням (знаменатель)
            $totalsQuery = (new Query())
                ->select(['d' => new Expression('DATE(datetime)'), 'total' => new Expression('COUNT(*)')])
                ->from('logs')
                ->groupBy('d')
                ->orderBy('d');
            $this->filter->applyTo($totalsQuery);

            $totals = [];
            foreach ($totalsQuery->all() as $row) {
                $totals[$row['d']] = (int) $row['total'];
            }
            $labels = array_keys($totals);

            # Запросы по дням в разрезе топ-браузеров
            $byBrowserQuery = (new Query())
                ->select([
                    'd' => new Expression('DATE(datetime)'),
                    'browser',
                    'c' => new Expression('COUNT(*)'),
                ])
                ->from('logs')
                ->andWhere(['browser' => $topBrowsers])
                ->groupBy(['d', 'browser']);
            $this->filter->applyTo($byBrowserQuery);

            # counts[browser][date] = c
            $counts = [];
            foreach ($byBrowserQuery->all() as $row) {
                $counts[$row['browser']][$row['d']] = (int) $row['c'];
            }

            $series = [];
            foreach ($topBrowsers as $browser) {
                $data = [];
                foreach ($labels as $date) {
                    $total = $totals[$date] ?? 0;
                    $c = $counts[$browser][$date] ?? 0;
                    $data[] = $total > 0 ? round($c / $total * 100, 2) : 0.0;
                }
                $series[] = ['browser' => $browser, 'data' => $data];
            }

            return ['labels' => $labels, 'series' => $series];
        });
    }

    /**
     * Данные таблицы по дням: дата, число запросов, самый популярный URL и браузер
     *
     * @return array<array{date:string,cnt:int,top_url:string,top_browser:string}>
     */
    public function tableByDay(): array
    {
        if ($this->filter->hasErrors()) {
            return [];
        }

        return $this->cached('tableByDay', function (): array {
            # Число запросов по дням
            $countsQuery = (new Query())
                ->select(['d' => new Expression('DATE(datetime)'), 'cnt' => new Expression('COUNT(*)')])
                ->from('logs')
                ->groupBy('d');
            $this->filter->applyTo($countsQuery);

            $rows = [];
            foreach ($countsQuery->all() as $row) {
                $rows[$row['d']] = [
                    'date' => $row['d'],
                    'cnt' => (int) $row['cnt'],
                    'top_url' => '',
                    'top_browser' => '',
                ];
            }

            foreach ($this->topPerDay('url') as $date => $value) {
                if (isset($rows[$date])) {
                    $rows[$date]['top_url'] = $value;
                }
            }
            foreach ($this->topPerDay('browser') as $date => $value) {
                if (isset($rows[$date])) {
                    $rows[$date]['top_browser'] = $value;
                }
            }

            return array_values($rows);
        });
    }

    /**
     * Топ-N браузеров за период (по числу запросов)
     *
     * @return string[]
     */
    private function topBrowsers(int $limit): array
    {
        $query = (new Query())
            ->select('browser')
            ->from('logs')
            ->groupBy('browser')
            ->orderBy(['cnt' => SORT_DESC])
            ->addSelect(['cnt' => new Expression('COUNT(*)')])
            ->limit($limit);
        $this->filter->applyTo($query);

        return $query->column();
    }

    /**
     * Самое популярное значение колонки ($field) по каждому дню
     *
     * @return array<string,string> date => value
     */
    private function topPerDay(string $field): array
    {
        # Оконная функция: ранжируем значения внутри каждого дня по частоте
        $inner = (new Query())
            ->select([
                'd' => new Expression('DATE(datetime)'),
                'val' => $field,
                'rn' => new Expression(
                    "ROW_NUMBER() OVER (PARTITION BY DATE(datetime) ORDER BY COUNT(*) DESC, {$field})"
                ),
            ])
            ->from('logs')
            ->groupBy(['d', $field]);
        $this->filter->applyTo($inner);

        $query = (new Query())
            ->select(['d', 'val'])
            ->from(['t' => $inner])
            ->where(['rn' => 1]);

        $result = [];
        foreach ($query->all() as $row) {
            $result[$row['d']] = (string) $row['val'];
        }

        return $result;
    }

    /**
     * Оборачивает выборку в Redis с TTL 5 минут
     *
     * @param string $name
     * @param callable $fn
     * @return mixed
     */
    private function cached(string $name, callable $fn): mixed
    {
        # -- нативный кеш через redis ----------------------------------------------------------------------------------
        # $redis = Yii::$app->get('redis'); /* @var $redis \yii\redis\Connection */
        # $cacheKey = $this->filter->cacheKey($name);
        #
        # $data = $redis->get($cacheKey);
        #
        # if($data === null){
        #     $redis->set($cacheKey, serialize($fn()), 'EX', self::CACHE_TTL);
        # }else{
        #     $data = unserialize($data);
        # }
        #
        # return $data;
        # --------------------------------------------------------------------------------------------------------------

        return Yii::$app->getCache()->getOrSet($this->filter->cacheKey($name), $fn, self::CACHE_TTL);
    }
}
