<?php

namespace SionModel\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class TouchForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('touch_entity');

        $this->add([
            'name' => 'security',
            'type' => 'csrf',
            'options' => [
                'csrf_options' => [
                    'timeout' => 900,
                ],
            ],
        ]);
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
        $this->add([
            'name' => 'cancel',
//          'type' => 'Submit',
            'attributes' => [
                'value' => 'Cancel',
                'id' => 'submit',
                'data-dismiss' => 'modal'
//              'class' => 'btn-danger'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
        ];
    }
}
