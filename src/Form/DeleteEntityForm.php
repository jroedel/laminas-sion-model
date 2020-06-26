<?php

namespace SionModel\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class DeleteEntityForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('entity_delete');

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
                'value' => 'Delete',
                'id' => 'submit',
                'class' => 'btn-danger'
            ],
        ]);
        $this->add([
            'name' => 'cancel',
//          'type' => 'Submit',
            'attributes' => [
                'value' => 'Cancel',
                'id' => 'submit',
                'data-dismiss' => 'modal'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [];
    }
}
