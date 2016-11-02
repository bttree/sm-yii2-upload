<?php

namespace bttree\smyupload\widgets;

use kartik\file\FileInput as BaseFileInput;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\InputWidget;

class FileInput extends InputWidget
{
    public $pluginOptions = [];

    public function run()
    {
        $this->options = ArrayHelper::merge(
            [
                'multiple' => false,
                'data-file-field' => true,
                'data-delete-flag' => '#delete-file-' . $this->id,
                'data-delete-flag-value' => $this->model->{$this->attribute},
            ],
            $this->options
        );
        $this->pluginOptions = ArrayHelper::merge(
            [
                'initialCaption' => $this->model->{$this->attribute},
                'showUpload' => false,
            ],
            $this->pluginOptions
        );

        echo Html::hiddenInput((new FileUploadBehavior())->deleteFileParam . '[]', null, ['id' => 'delete-file-' . $this->id]);
        echo BaseFileInput::widget([
            'id' => $this->id,
            'model' => $this->model,
            'attribute' => $this->attribute,
            'options' => $this->options,
            'pluginOptions' => $this->pluginOptions,
        ]);
        $this->registerJs();
    }

    public function registerJs()
    {
        $js = <<<'JS'
$('[data-file-field]').on('fileclear', function(){
    var $this = $(this);
    $($this.data('delete-flag')).val($this.data('delete-flag-value'));
})
JS;
        $this->getView()->registerJs($js);
    }
}
