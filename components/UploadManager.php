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
     * @param string       $filePath
     * @param string       $chunkPath
     * @param array        $chunkParams
     * @return integer
     * @throws HttpException
     * @throws \yii\base\Exception
     */
    public function saveFileByChunk(UploadedFile $chunkfileInstance, $filePath, $chunkPath, $chunkParams)
    {
        $totalSize = self::fixIntegerOverflow($chunkParams['totalSize']);
        $filePath  = $this->getFilePath($filePath);

        $chunkPathDir = dirname($chunkPath);
        if (!FileHelper::createDirectory($chunkPathDir)) {
            throw new HttpException(500, sprintf('Не удалось создать папку «%s»', $chunkPathDir));
        }

        $chunk     = $chunkfileInstance->tempName;
        $chunkSize = $chunkfileInstance->size;


        $fileSize = 0;
        if (is_file($chunkPath)) {
            if($chunkParams['index'] > 0) {
                $fileSize = self::getFileSize($chunkPath);
            } else {
                unlink($chunkPath);
            }
        }

        $done = false;

        if (($fileSize < $totalSize) && ($chunkParams['index'] < $chunkParams['totalCount'])) {
            file_put_contents(
                $chunkPath,
                fopen($chunk, 'r'),
                FILE_APPEND
            );

            if (self::getFileSize($chunkPath) >= $totalSize) {
                $done = true;
            }
        } else {
            $done = true;
        }

        if ($done) {

            $filePathDir = dirname($filePath);
            if (!FileHelper::createDirectory($filePathDir)) {
                throw new HttpException(500, sprintf('Не удалось создать папку «%s»', $filePathDir));
            }

            rename($chunkPath, $filePath);

            return self::CHUNK_SAVE_DONE;
        } else {
            return self::CHUNK_SAVE_PROCESSING;
        }

        return self::CHUNK_SAVE_ERROR;
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

    /**
     * @param string  $filePath
     * @param boolean $clearStatCache
     * @return float
     */
    public static function getFileSize($filePath, $clearStatCache = true)
    {
        if ($clearStatCache) {
            if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
                clearstatcache(true, $filePath);
            } else {
                clearstatcache();
            }
        }

        return self::fixIntegerOverflow(filesize($filePath));
    }

    /**
     * @param $size
     * @return float
     */
    protected static function fixIntegerOverflow($size)
    {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }

        return $size;
    }
}