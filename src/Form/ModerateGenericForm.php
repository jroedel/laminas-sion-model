<?php

namespace SionModel\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

class ModerateGenericForm extends Form implements InputFilterProviderInterface
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('moderate_generic');
//      $this->setAttribute('method', 'GET');
        //@todo get rid of this style attr, not allowed by CSP
        $this->setAttribute('style', 'display: inline;');
        $this->add([
            'name' => 'suggestionId',
            'type' => 'Hidden',
        ]);
        $this->add([
            'name' => 'suggestionResponse',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Notes to the reviewer of your suggestion',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'rows' => 10,
            ],
        ]);
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
            'name' => 'accept',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Accept',
                'class' => 'btn-success'
            ],
        ]);
        $this->add([
            'name' => 'deny',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Deny',
                'class' => 'btn-danger'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'suggestionResponse' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull'],
                ],
            ],
            'suggestionId' => [
                'required' => false,
                'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
            ],
        ];
    }
}
