<?php

declare(strict_types=1);

namespace app\commands\actions;

use app\components\NginxLogLineParser;
use app\models\File;
use app\models\UploadForm;
use Throwable;
use Yii;
use yii\base\Action;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\redis\Connection as Redis;

/**
 * Standalone-action воркера импорта логов nginx
 *
 * Демон: блокирующе читает идентификаторы файлов из очереди Redis (LIST) и построчно
 * импортирует их в таблицу logs батчами. Прогресс пишется в files и в Redis для
 * отображения на странице.
 *
 * @property Controller $controller
 */
class ImportListenAction extends Action
{
    /**
     * @var NginxLogLineParser
     */
    private NginxLogLineParser $parser;

    /**
     * ImportListenAction constructor.
     *
     * @param $id
     * @param $controller
     * @param NginxLogLineParser $nginxLogLineParser
     * @param $config
     */
    public function __construct($id, $controller, NginxLogLineParser $nginxLogLineParser, $config = [])
    {
        parent::__construct($id, $controller, $config);

        $this->parser = $nginxLogLineParser;
    }

    /**
     * Демон: блокирующе читает очередь и обрабатывает файлы по мере поступления.
     *
     * @return int
     */
    public function run(): int
    {
        $controller = $this->controller; /* @var $controller \app\commands\ImportController */
        $controller->stdout('Воркер запущен!' . PHP_EOL);

        for (
            $redis = Yii::$app->get('redis'); /* @var $redis Redis */
            is_array($job = $redis->brpop(UploadForm::QUEUE_KEY, $controller->timeout));
            $job && $this->importFile((int)$job[1])
        );

        return ExitCode::OK;
    }

    /**
     * Импортирует один файл. Идемпотентно: при повторном запуске продолжает
     * с уже обработанной строки (processed_lines), не создавая дублей.
     *
     * @param int $fileId
     * @return void
     */
    private function importFile(int $fileId): void
    {
        $controller = $this->controller; /* @var $controller \app\commands\ImportController */

        $file = File::findOne($fileId);
        if ($file === null) {
            $controller->stderr("Файл #{$fileId} не найден!" . PHP_EOL);
            return;
        }

        if ($file->status === 'done') {
            $controller->stdout("Файл #{$fileId} уже импортирован!" . PHP_EOL);
            return;
        }

        if (!is_file($file->path)) {
            $this->markFailed($file, "файл не найден на диске: {$file->path}");
            return;
        }

        $fh = fopen($file->path, 'rb');
        if ($fh === false) {
            $this->markFailed($file, 'не удалось открыть файл');
            return;
        }

        $file->status = 'processing';
        $file->save(false, ['status']);

        $offset = $file->processed_lines; // уже обработанных строк
        $controller->stdout("Импорт файла #{$fileId} ({$file->total_lines} строк), старт со строки {$offset}." . PHP_EOL);

        try {
            $this->importFromHandle($file, $fh, $offset);

            $file->status = 'done';
            $file->save(false, ['status']);
            $this->writeProgress($file);
            $controller->stdout("Файл #{$fileId} импортирован: {$file->processed_lines} строк." . PHP_EOL);
        } catch (Throwable $e) {
            $this->markFailed($file, $e->getMessage());
        } finally {
            fclose($fh);
        }
    }

    /**
     * Построчно читает файл и вставляет распарсенные строки батчами.
     *
     * @param File $file
     * @param $fh
     * @param int $offset
     * @return void
     * @throws Throwable
     */
    private function importFromHandle(File $file, $fh, int $offset): void
    {
        $columns = ['file_id', 'ip', 'datetime', 'url', 'status', 'user_agent', 'os', 'arch', 'browser', 'is_bot'];

        $parser = $this->parser;
        $controller = $this->controller;

        $lineNo = 0;
        $batch = [];

        while (($line = fgets($fh)) !== false) {
            ++$lineNo;

            # Возобновляем: пропускаем уже обработанные в прошлом запуске строки
            if ($lineNo <= $offset) {
                continue;
            }

            $parsedLine = $parser->parse($line);
            if ($parsedLine !== null) {
                $batch[] = [
                    $file->id,
                    $parsedLine->ip,
                    $parsedLine->datetime,
                    $parsedLine->url,
                    $parsedLine->status,
                    $parsedLine->userAgent,
                    $parsedLine->os,
                    $parsedLine->architecture,
                    $parsedLine->browser,
                    (int) $parsedLine->isBot,
                ];
            }

            if (count($batch) >= $controller->batchSize) {
                $this->flushBatch($file, $columns, $batch, $lineNo);
                $batch = [];
            }
        }

        # Остаток + финал
        $this->flushBatch($file, $columns, $batch, $lineNo);
    }

    /**
     * Атомарно вставляет батч и обновляет прогресс в той же транзакции,
     * чтобы processed_lines всегда соответствовал зафиксированным данным.
     *
     * @param File $file
     * @param array $columns
     * @param array $batch
     * @param int $lineNo
     * @return int новое значение processed_lines
     * @throws Throwable
     */
    private function flushBatch(File $file, array $columns, array $batch, int $lineNo): int
    {
        $db = Yii::$app->getDb();
        $transaction = $db->beginTransaction();
        try {
            if (!empty($batch)) {
                $db->createCommand()->batchInsert('logs', $columns, $batch)->execute();
            }

            $file->processed_lines = $lineNo;
            $file->save(false, ['processed_lines']);

            $transaction->commit();
        } catch (Throwable $t) {
            $transaction->rollBack();
            throw $t;
        }

        $this->writeProgress($file);

        return $lineNo;
    }

    /**
     * Дублирует прогресс в Redis для быстрого отображения на странице
     *
     * @param File $file
     * @return void
     */
    private function writeProgress(File $file): void
    {
        $redis = Yii::$app->get('redis'); /* @var $redis Redis */

        $redis->set("import:progress:{$file->id}", json_encode([
            'status' => $file->status,
            'total' => $file->total_lines,
            'processed' => $file->processed_lines
        ]), 'EX', 50 * 60);
    }

    /**
     * @param File $file
     * @param string $reason
     * @return void
     */
    private function markFailed(File $file, string $reason): void
    {
        $file->status = 'failed';
        $file->save(false, ['status']);
        $this->writeProgress($file);
        $this->controller->stderr("Ошибка импорта файла #{$file->id}: {$reason}" . PHP_EOL);

        Yii::error("Импорт файла #{$file->id} провален: {$reason}", __METHOD__);
    }
}
