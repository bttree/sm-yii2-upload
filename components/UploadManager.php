<?php
namespace bttree\smyupload\components;

use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;
use yii\web\HttpException;
use yii\web\UploadedFile;

class UploadManager extends Component
{
    const CHUNK_SAVE_ERROR      = 0;
    const CHUNK_SAVE_PROCESSING = 1;
    const CHUNK_SAVE_DONE       = 2;

    /**
     * @var string - Путь к корневой директории для загрузки
     */
    public $uploadPath = '';

    /**
     * @var string - Url к корневой папке загрузок
     */
    public $uploadUrl = '';

    public function init()
    {
        parent::init();
    }

    /**
     * @param UploadedFile $fileInstance
     * @param $fileName - Полный путь в папке загрузок, например, /uploads/module/model/000000.png
     * @return bool
     * @throws HttpException
     */
    public function saveFile(UploadedFile $fileInstance, $fileName)
    {
        $path = $this->getFilePath($fileName);

        $dir = dirname($path);
        if (!FileHelper::createDirectory($dir)) {
            throw new HttpException(500, sprintf('Не удалось создать папку «%s»', $dir));
        }

        return $fileInstance->saveAs($path);
    }

    /**
     * @param UploadedFile $chunkfileInstance
     * @param $fileName - Полный путь в папке загрузок, например, /uploads/module/model/000000.png
     * @return bool
     * @throws HttpException
     */
    public function saveFileByChunk(UploadedFile $chunkfileInstance, $filePath, $chunkPath)
    {
        $filePath = $this->getFilePath($filePath);

        $chunkPathDir = dirname($chunkPath);
        if (!FileHelper::createDirectory($chunkPathDir)) {
            throw new HttpException(500, sprintf('Не удалось создать папку «%s»', $chunkPathDir));
        }
        $filePathDir  = dirname($filePath);
        if (!FileHelper::createDirectory($filePathDir)) {
            throw new HttpException(500, sprintf('Не удалось создать папку «%s»', $filePathDir));
        }

        $chunk = $chunkfileInstance->tempName;
        $chunkSize = $chunkfileInstance->size;

        if(is_file($chunkPath)) {
            $fileSize = filesize($chunkPath);
        } else {
            $fileSize = 0;
        }

        $done      = false;
        $totalSize = Yii::$app->getRequest()->getBodyParam('dztotalfilesize');
        if ($fileSize < $totalSize) {
            file_put_contents(
                $chunkPath,
                fopen($chunk, 'r'),
                FILE_APPEND
            );

            if (filesize($chunkPath) >= $totalSize) {
                $done = true;
            }
        } else {
            $done = true;
        }

        if ($done) {
            rename($chunkPath, $filePath);

            return self::CHUNK_SAVE_DONE;
        } else {
            return self::CHUNK_SAVE_PROCESSING;
        }

        return self::CHUNK_SAVE_ERROR;

//        return $fileInstance->saveAs($path);
    }

    /**
     * @param $path - Полный путь внутри папок для загрузок, например, /uploads/module/model/000000.png
     */
    public function removeFile($path)
    {
        $fullPath = \Yii::getAlias($this->uploadPath) . DIRECTORY_SEPARATOR . ltrim($path, '\\/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * @param $name
     * @return string
     */
    public function getFilePath($name)
    {
        return \Yii::getAlias($this->uploadPath) . DIRECTORY_SEPARATOR . ltrim($name, '\\/');
    }

    /**
     * @param $name
     * @return string
     */
    public function getFileUrl($name)
    {
        return rtrim(Yii::getAlias($this->uploadUrl), '/') . '/' . ltrim(str_replace('\\', '/', $name), '/');
    }
}
