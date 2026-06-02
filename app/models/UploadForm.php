<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Форма загрузки лог-файла nginx
 *
 * Сохраняет файл в runtime/uploads, считает md5 (защита от повторной загрузки),
 * создаёт запись в таблице files и ставит задание на импорт в очередь Redis (LIST)
 */
class UploadForm extends Model
{
    /**
     * Ключ Redis-списка с заданиями на импорт. Из него читает консольный воркер.
     */
    public const QUEUE_KEY = "nginx-logs:queue";

    /**
     * @var UploadedFile|null загружаемый лог-файл
     */
    public ?UploadedFile $logFile = null;

    /**
     * @var string|null
     */
    public ?string $md5 = null;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['logFile'],
                'required'
            ],
            [['logFile'],
                'file',
                'extensions' => ['log', 'txt'],
                'maxSize' => 1024 * 1024 * 256, # 256 МБ
                'tooBig' => 'Файл слишком большой (максимум 256МБ).',
                'wrongExtension' => 'Допустимы только файлы .log и .txt.',
                'skipOnEmpty' => false,
            ],
            [['md5'],
                function(string $attribute){
                    if($this->hasErrors('logFile')){
                        return;
                    }

                    $this->{$attribute} = md5_file($this->logFile->tempName);

                    if(File::find()->where([
                        'md5' => $this->{$attribute}
                    ])->exists()){
                        $this->addError('logFile', 'Этот файл уже был загружен.');
                    }
                },
                'skipOnEmpty' => false
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'logFile' => "Лог-файл nginx"
        ];
    }

    /**
     * Обрабатывает загрузку: проверяет дубликат, сохраняет файл и ставит в очередь
     *
     * @return File|null
     */
    public function upload(): ?File
    {
        $uploadDir = Yii::getAlias('@app/runtime/uploads');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->addError('logFile', 'Не удалось создать каталог для загрузок.');
            return null;
        }

        $path = $uploadDir. DIRECTORY_SEPARATOR. $this->md5. ".". $this->logFile->extension;
        if (!$this->logFile->saveAs($path)) {
            $this->addError('logFile', 'Не удалось сохранить файл на диск.');
            return null;
        }

        $file = new File();
        $file->path = $path;
        $file->md5 = $this->md5;
        $file->status = 'pending';
        $file->total_lines = $this->countLines($path);
        $file->processed_lines = 0;

        if (!$file->save()) {
            @unlink($path);
            $this->addError('logFile', 'Не удалось сохранить запись о файле.');
            return null;
        }

        // Ставим задание на импорт в очередь Redis (LIST), воркер обработает асинхронно
        Yii::$app->get("redis")->lpush(self::QUEUE_KEY, (string) $file->id);

        return $file;
    }

    /**
     * Считам число строк в файле
     */
    private function countLines(string $path): int
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return 0;
        }

        $lines = 0;

        while(fgets($fh) !== false){
            ++$lines;
        }

        fclose($fh);

        return $lines;
    }
}
