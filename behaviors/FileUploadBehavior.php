<?php
namespace bttree\smyupload\behaviors;

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
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if(empty($this->uploadManager)) {
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
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
        ];
    }

    public function afterFind($event)
    {
        $this->currentFile = $this->owner->{$this->attribute};
    }

    public function beforeValidate($event)
    {
        $model = $this->owner;
        if (!$this->uploadedFile) {
            $this->uploadedFile = ($this->fileInstanceName === null ? UploadedFile::getInstance($model, $this->attribute) : UploadedFile::getInstanceByName($this->fileInstanceName));
        }

        if (!$this->uploadedFile && $model->{$this->attribute} instanceof UploadedFile) {
            $this->uploadedFile = $model->{$this->attribute};
        }

        if ($this->uploadedFile instanceof UploadedFile) {
            $model->{$this->attribute} = $this->uploadedFile;
            $validator = Validator::createValidator(
                'file',
                $model,
                $this->attribute,
                [
                    'extensions' => $this->extensions,
                    'minSize' => $this->minSize,
                    'maxSize' => $this->maxSize,
                ]
            );
            $validator->validateAttribute($model, $this->attribute);
        }

        if($this->uploadedFile && $this->uploadedFile->name && $this->base_name){
            $model->{$this->base_name} = $this->uploadedFile->name;
        }
    }

    public function beforeSave($event)
    {
        if (!(Yii::$app instanceof \yii\web\Application)) {
            return;
        }

        if ($delete = Yii::$app->request->post($this->deleteFileParam)) {
            if (in_array($this->owner->{$this->attribute}, (array)$delete)) {
                $this->removeFile();
                $this->owner->{$this->attribute} = null;
            }
        }

        if ($this->uploadedFile instanceof UploadedFile) {
            $this->removeFile();
            $this->saveFile();
        }
    }

    public function removeFile()
    {
        $this->uploadManager->removeFile($this->currentFile);
    }

    protected function saveFile()
    {
        $path = $this->getNewFilePath($this->uploadedFile);
        $this->uploadManager->saveFile($this->uploadedFile, $path);
        $this->owner->{$this->attribute} = $path;
    }

    protected function getNewFilePath(UploadedFile $uploadedFile)
    {
        $uid = md5(uniqid($this->getUploadPath()));
        $path = $this->getUploadPath() . '/' . substr($uid, 0, 2) . '/' . substr($uid, 2, 2) . '/' . substr($uid, 4) . '.' . $uploadedFile->getExtension();
        $path = FileHelper::normalizePath($path, '/');
        return $path;
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
}
