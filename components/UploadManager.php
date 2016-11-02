<?php
namespace bttree\smyupload\components;

use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;
use yii\web\HttpException;
use yii\web\UploadedFile;

class UploadManager extends Component
{
    /**
     * @var string - Путь к корневой директории для загрузки
     */
    public $uploadPath = '@static';

    /**
     * @var string - Url к корневой папке загрузок
     */
    public $uploadUrl = '@staticUrl';

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
