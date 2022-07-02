<?php

declare(strict_types=1);

namespace SionModel\Form;

use Laminas\InputFilter\InputFilterProviderInterface;

class SuggestForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * These are the possible values for the haystack
     *
     * @var array
     */
    protected $entityHaystack = [];

    public function __construct(array $entityHaystack)
    {
        // we want to ignore the name passed
        parent::__construct('suggest');
        $this->setAttribute('data-loading-text', 'Please wait...');
        $this->setEntityHaystack($entityHaystack);

        $this->add([
            'name' => 'entity',
            'type' => 'Hidden',
        ]);
        $this->add([
            'name' => 'entityId',
            'type' => 'Hidden',
        ]);
        $this->add([
            'name'       => 'submit',
            'type'       => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id'    => 'submit',
                'class' => 'btn-primary',
            ],
        ]);
        $this->add([
            'name'       => 'cancel',
            'type'       => 'Button',
            'attributes' => [
                'value'        => 'Cancel',
                'id'           => 'submit',
                'data-dismiss' => 'modal',
//              'class' => 'btn-danger'
            ],
        ]);
    }

    /**
     * Set the haystack of acceptable values for the entity field
     */
    public function getEntityHaystack(): array
    {
        return $this->entityHaystack;
    }

    public function setEntityHaystack(array $entityHaystack): static
    {
        $this->entityHaystack = $entityHaystack;
        $this->filterSpec     = []; //invalidate spec
        return $this;
    }

    public function getInputFilterSpecification(): array
    {
        if ($this->filterSpec) {
            return $this->filterSpec;
        }
        return $this->filterSpec = [
            'entity'   => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => $this->entityHaystack,
                        ],
                    ],
                ],
                'filters'    => [
                    ['name' => 'ToNull'],
                ],
            ],
            'entityId' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
                ],
            ],
        ];
    }
}
