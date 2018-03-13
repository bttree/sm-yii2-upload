<?php

namespace bttree\smyupload\behaviors;

use bttree\smyupload\components\UploadManager;
use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\validators\Validator;
use yii\web\UploadedFile;

class FileUploadBehavior extends Behavior
{
    public $attribute = 'file';

    public $base_name = false;

    /**
     *
     * @var string the name of the file input field, used to get instance by name.
     * Optional. If not set get instance by model attribute will be used.
     */
    public $fileInstanceName;

    /**
     * @var int minimum file size.
     */
    public $minSize = 0;

    /**
     * @var int maximum file size.
     */
    public $maxSize = 5368709120;

    /**
     * @var string allowed file types.
     */
    public $extensions = null;

    /**
     * @var string Путь внутри папки static
     */
    public $uploadPath = 'uploads';
    /**
     * @var string Имя параметра, в котором передаются значения удаляемых атрибутов
     */
    public $deleteFileParam = 'delete-the-file';
    /**
     * @var string
     */
    protected $currentFile;
    /**
     * @var null|UploadedFile
     */
    protected $uploadedFile = null;
    /**
     * @var UploadManager
     */
    public $uploadManager;

    /**
     * @var string
     */
    public $chunk = false;

    /**
     * @var string
     */
    public $chunkComplite = false;

    /**
     * @var string
     */
    public $tmpChunkDir = 'file_chunk_tmp';

    /**
     * @var string
     */
    public $chunkSizeVar = 'dzchunksize';

    /**
     * @var string
     */
    public $chunkTotalFileSizeVar = 'dztotalfilesize';

    /**
     * @var string
     */
    public $chunkIdVar = 'dzuuid';

    /**
     * @var string
     */
    public $chunkTotalCountVar = 'dztotalchunkcount';

    /**
     * @var string
     */
    public $chunkIndexVar = 'dzchunkindex';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (empty($this->uploadManager)) {
            $this->uploadManager = Yii::$app->upload;
        }
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ActiveRecord::EVENT_BEFORE_INSERT   => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE   => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE   => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_FIND      => 'afterFind',
        ];
    }

    public function afterFind($event)
    {
        $this->currentFile = $this->owner->{$this->attribute};
    }

    public function beforeValidate($event)
    {
        if (!$this->chunk) {
            $this->initFileInstance();
        }
    }

    /**
     *
     */
    protected function initFileInstance()
    {
        $model = $this->owner;
        if (!$this->uploadedFile) {
            $this->uploadedFile = ($this->fileInstanceName === null ?
                UploadedFile::getInstance($model, $this->attribute) :
                UploadedFile::getInstanceByName($this->fileInstanceName));
        }

        if (!$this->uploadedFile && $model->{$this->attribute} instanceof UploadedFile) {
            $this->uploadedFile = $model->{$this->attribute};
        }

        if ($this->uploadedFile instanceof UploadedFile) {
            $model->{$this->attribute} = $this->uploadedFile;
            $validator                 = Validator::createValidator(
                'file',
                $model,
                $this->attribute,
                [
                    'extensions' => $this->extensions,
                    'minSize'    => $this->minSize,
                    'maxSize'    => $this->maxSize,
                ]
            );
            $validator->validateAttribute($model, $this->attribute);
        }

        if ($this->uploadedFile && $this->uploadedFile->name && $this->base_name) {
            $model->{$this->base_name} = $this->uploadedFile->name;
        }
    }

    public function beforeSave($event)
    {
        if (!(Yii::$app instanceof \yii\web\Application)) {
            return;
        }

        if (!$this->chunk) {
            $this->deleteByPost();

            if ($this->uploadedFile instanceof UploadedFile) {
                $this->removeFile();
                $this->saveFile();
            }
        }
    }

    /**
     *
     */
    protected function deleteByPost()
    {
        if ($delete = Yii::$app->request->post($this->deleteFileParam)) {
            if (in_array($this->owner->{$this->attribute}, (array)$delete)) {
                $this->removeFile();
                $this->owner->{$this->attribute} = null;
            }
        }
    }

    public function removeFile()
    {
        $this->uploadManager->removeFile($this->currentFile);
    }

    /**
     * @throws \yii\web\HttpException
     */
    protected function saveFile()
    {
        $path = $this->getNewFilePath($this->uploadedFile);

        $this->uploadManager->saveFile($this->uploadedFile, $path);
        $this->owner->{$this->attribute} = $path;
    }

    /**
     * @return int
     * @throws \yii\base\Exception
     * @throws \yii\web\HttpException
     */
    protected function saveFileByChunk()
    {
        $path      = $this->getNewFilePath($this->uploadedFile);
        $chunkPath = $this->getChunkTmpPath($this->uploadedFile);

        $chunkParams = [];

        $chunkParams['totalSize']  = Yii::$app->getRequest()->getBodyParam($this->chunkTotalFileSizeVar);
        $chunkParams['size']       = Yii::$app->getRequest()->getBodyParam($this->chunkSizeVar);
        $chunkParams['totalCount'] = Yii::$app->getRequest()->getBodyParam($this->chunkTotalCountVar);
        $chunkParams['index']      = Yii::$app->getRequest()->getBodyParam($this->chunkIndexVar);
        array_filter($chunkParams);
        if (count($chunkParams) < 3) {
            return UploadManager::CHUNK_SAVE_ERROR;
        }

        $result = $this->uploadManager->saveFileByChunk($this->uploadedFile, $path, $chunkPath, $chunkParams);

        if ($result === UploadManager::CHUNK_SAVE_DONE) {
            $this->chunkComplite             = true;
            $this->owner->{$this->attribute} = $path;
        } else {
            $this->owner->{$this->attribute} = '';
        }

        return $result;
    }

    protected function getNewFilePath(UploadedFile $uploadedFile)
    {
        $basePath = $this->getUploadPath();

        $uid  = md5(uniqid($basePath));
        $dir1 = substr($uid, 0, 3);
        $ext  = $uploadedFile->getExtension();

        $path = "{$basePath}/{$dir1}/{$uid}.{$ext}";

        $path = FileHelper::normalizePath($path, '/');

        return $path;
    }

    /**
     * @return string
     */
    protected function getChunkTmpPath(UploadedFile $uploadedFile)
    {
        $runtimePath = Yii::$app->runtimePath;
        $tmpDir      = $this->tmpChunkDir;
        $fileName    = md5(Yii::$app->getRequest()->getBodyParam($this->chunkIdVar));
        $ext         = $uploadedFile->getExtension();
        $tmpDirPath  = "{$runtimePath}/{$tmpDir}";

        if (!FileHelper::createDirectory($tmpDirPath)) {
            throw new HttpException(500, sprintf('Не удалось создать папку «%s»', $tmpPath));
        }

        $tmpPath = "{$tmpDirPath}/{$fileName}.{$ext}";

        return $tmpPath;
    }

    protected function getUploadPath()
    {
        return $this->uploadPath;
    }

    public function beforeDelete($event)
    {
        $this->removeFile();
    }

    /**
     * @param $name string the name of the file input field.
     */
    public function setFileInstanceName($name)
    {
        $this->fileInstanceName = $name;
    }

    public function getFileUrl()
    {
        if ($this->owner->{$this->attribute}) {
            return $this->uploadManager->getFileUrl($this->owner->{$this->attribute});
        }

        return null;
    }

    public function getFilePath()
    {
        return $this->uploadManager->getFilePath($this->owner->{$this->attribute});
    }

    public function setUploadedFile(UploadedFile $file)
    {
        $this->uploadedFile = $file;
    }

    /**
     * @return boolean
     * @throws \yii\base\Exception
     * @throws \yii\web\HttpException
     */
    public function saveWithChunk()
    {
        $this->chunk = true;

        $this->initFileInstance();

        $this->deleteByPost();

        $result = true;
        if ($this->uploadedFile instanceof UploadedFile) {

            $chunkResult = $this->saveFileByChunk();

            switch ($chunkResult) {
                case UploadManager::CHUNK_SAVE_DONE:

                    $result = $this->owner->save();
                    break;
                case UploadManager::CHUNK_SAVE_PROCESSING:

                    $result = true;
                    break;
                case UploadManager::CHUNK_SAVE_ERROR:
                    $this->owner->addError($this->attribute, 'Error save file chunk!');

                    $result = false;
                    break;
                default:
                    throw new InvalidArgumentException("Wrong chunk status: {$result}");
            }
        }

        return $result;
    }
}