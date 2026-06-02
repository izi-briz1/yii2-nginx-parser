<?php

declare(strict_types=1);

namespace app\commands;

use app\commands\actions\ImportListenAction;
use yii\console\Controller;

/**
 * Консольный воркер импорта логов nginx.
 *
 * Вся логика в standalone-action {@see ImportListenAction}.
 *
 * Использование:
 *   yii import/listen - демон: блокирующе ждёт задания из очереди и обрабатывает их
 */
class ImportController extends Controller
{
    /**
     * Размер батча для вставки в БД.
     */
    public int $batchSize = 512;

    /**
     * Таймаут (сек) ожидания задания в brpop. 0 — ждать бесконечно.
     */
    public int $timeout = 5;

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'listen' => ImportListenAction::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['batchSize', 'timeout']);
    }
}
