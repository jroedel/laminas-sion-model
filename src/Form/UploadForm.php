<?php

namespace SionModel\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

class UploadForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('upload-form');

        $this->add([
            'name' => 'fileUpload',
            'type' => 'File',
            'options' => [
                'label' => 'File upload',
                'required' => true,
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'fileupload' => [
                'required' => true,
            ],
        ];
    }
}
