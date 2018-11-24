<?php
namespace SionModel\Form;

use Zend\InputFilter\InputFilterProviderInterface;

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

    public function getInputFilterSpecification()
    {
        return [
            'text' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Zend\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
            ],
        ];
    }
}
