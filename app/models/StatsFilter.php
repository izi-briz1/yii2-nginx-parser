<?php

declare(strict_types=1);

namespace app\models;

use DateTime;
use yii\base\Model;
use yii\db\Query;

/**
 * Модель фильтра для графиков и таблицы статистики.
 *
 * Фильтрация по диапазону дат (не более 1 года), ОС, архитектуре и режиму ботов.
 */
class StatsFilter extends Model
{
    public const BOTS_ALL = 'all';
    public const BOTS_HUMANS = 'humans';
    public const BOTS_ONLY = 'bots';

    /**
     * @var string
     */
    public string $dateFrom = '';

    /**
     * @var string
     */
    public string $dateTo = '';

    /**
     * @var string операционная система ('' - все)
     */
    public string $os = '';

    /**
     * @var string операционная система ('' - все)
     */
    public string $arch = '';

    /**
     * @var string режим ботов: all | humans | bots
     */
    public string $bots = self::BOTS_ALL;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['dateFrom'], 'default', 'value' => date('Y-m-d', strtotime('-1 month'))],
            [['dateTo'], 'default', 'value' => date('Y-m-d')],
            [['dateFrom', 'dateTo'], 'date', 'format' => 'php:Y-m-d'],
            [['dateTo'], function ($attribute) {
                $from = DateTime::createFromFormat('Y-m-d', $this->dateFrom);
                $to = DateTime::createFromFormat('Y-m-d', $this->dateTo);

                if ($from > $to) {
                    return (bool)$this->addError($attribute, 'Дата «по» не может быть раньше даты «с».');
                }

                if ($from->modify('+1 year') < $to) {
                    return (bool)$this->addError($attribute, 'Диапазон дат не может превышать 1 год.');
                }

                return true;
            }],
            [['os'], 'in', 'range' => array_keys($this->osOptions()), 'skipOnEmpty' => true],
            [['arch'], 'in', 'range' => array_keys($this->archOptions()), 'skipOnEmpty' => true],
            [['bots'], 'in', 'range' => array_keys($this->botsOptions()), 'skipOnEmpty' => true],
            [['bots'], 'default', 'value' => self::BOTS_ALL],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'dateFrom' => 'Дата с',
            'dateTo' => 'Дата по',
            'os' => 'ОС',
            'arch' => 'Архитектура',
            'bots' => 'Боты',
        ];
    }

    /**
     * Применяет условия фильтра к запросу
     */
    public function applyTo(Query $query): Query
    {
        if ($this->dateFrom !== '') {
            $query->andWhere(['>=', 'datetime', $this->dateFrom . ' 00:00:00']);
        }

        if ($this->dateTo !== '') {
            $end = (new DateTime($this->dateTo))->modify('+1 day')->format('Y-m-d 00:00:00');
            $query->andWhere(['<', 'datetime', $end]);
        }

        if ($this->os !== '') {
            $query->andWhere(['os' => $this->os]);
        }

        if ($this->arch !== '') {
            $query->andWhere(['arch' => $this->arch]);
        }

        if ($this->bots === self::BOTS_HUMANS) {
            $query->andWhere(['is_bot' => 0]);
        } elseif ($this->bots === self::BOTS_ONLY) {
            $query->andWhere(['is_bot' => 1]);
        }

        return $query;
    }

    /**
     * Список ОС для выпадающего списка (из реальных данных)
     *
     * @return array<string,string>
     */
    public function osOptions(): array
    {
        $values = (new Query())->select('os')->from('logs')->distinct()->column();

        if (empty($values)) {
            return $values;
        }

        sort($values, SORT_STRING);

        return array_combine($values, $values);
    }

    /**
     * @return array<string,string>
     */
    public function archOptions(): array
    {
        return [
            'x86' => 'x86',
            'x64' => 'x64',
            'unknown' => 'unknown'
        ];
    }

    /**
     * @return array<string,string>
     */
    public function botsOptions(): array
    {
        return [
            self::BOTS_ALL => 'Все',
            self::BOTS_HUMANS => 'Только люди',
            self::BOTS_ONLY => 'Только боты',
        ];
    }

    /**
     * Нормализованный ключ для кэша, зависящий от всех параметров фильтра
     */
    public function cacheKey(string $prefix): string
    {
        return "stats:{$prefix}:" . md5(serialize([
            $this->dateFrom,
            $this->dateTo,
            $this->os,
            $this->arch,
            $this->bots,
        ]));
    }
}
