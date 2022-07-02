<?php

namespace SionModel\Form;

use Laminas\Filter\ToNull;
use Laminas\InputFilter\InputFilterProviderInterface;

class CommentForm extends SionForm implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('comment');

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'comment',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Leave a comment',
            ],
            'attributes' => [
                'required' => false,
//                 'data-provide' => 'markdown',
//                 'data-parser' => 'CommonMark',
                'rows' => 3,
            ],
        ]);
        $this->add([
            'name' => 'redirect',
            'type' => 'Hidden',
        ]);
//We won't allow editing the legacy columns
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
    }

    public function getInputFilterSpecification(): array
    {
        if ($this->filterSpec) {
            return $this->filterSpec;
        }
        return $this->filterSpec = [
            'text' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
        ];
    }
}
