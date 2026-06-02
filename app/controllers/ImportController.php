<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\File;
use app\models\UploadForm;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Загрузка лог-файлов nginx и отображение прогресса импорта
 */
class ImportController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'upload' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Страница загрузки файла.
     */
    public function actionIndex(): Response|string
    {
        $model = new UploadForm();

        if ($this->request->isPost) {
            $model->logFile = UploadedFile::getInstance($model, 'logFile');

            if ($model->validate()) {
                $file = $model->upload();

                if ($file !== null) {
                    Yii::$app->session->setFlash('success', 'Файл принят и поставлен в очередь на импорт.',);

                    return $this->redirect(['index', 'fileId' => $file->id]);
                }
            }
        }

        $fileId = (int) $this->request->get('fileId');
        $file = $fileId > 0 ? File::findOne($fileId) : null;

        return $this->render('index', [
            'model' => $model,
            'file' => $file,
        ]);
    }

    /**
     * Прогресс импорта в формате JSON для AJAX-опроса
     */
    public function actionProgress(int $id): array
    {
        $this->response->format = Response::FORMAT_JSON;

        $redis = Yii::$app->get('redis'); /* @var $redis \yii\redis\Connection */

        $progress = $redis->get("import:progress:{$id}");
        if ($progress !== null) {
            return json_decode($progress, true);
        }

        $file = File::findOne($id);
        if ($file === null) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return [
            'id' => $file->id,
            'status' => $file->status,
            'total' => $file->total_lines,
            'processed' => 0
        ];
    }
}
