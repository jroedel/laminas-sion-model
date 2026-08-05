<?php

namespace SionModel\Form;

use Laminas\Form\Form;
use Laminas\Filter\ToNull;
use Laminas\Validator\StringLength;
use SionModel\Validator\Phone;
use Laminas\Filter\StripTags;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Csrf;

class SionForm extends Form
{
    protected $filterSpec;
    protected $phoneInputFilterSpec;
    protected $phoneLabelInputFilterSpec;

    protected $isMultiPersonUser = false;
    
    /**
     * Db adapter used for validators, optional
     * @var \Laminas\Db\Adapter\AdapterInterface $adapter
     */
    protected $adapter;

    public function __construct($name)
    {
        parent::__construct($name);

        $this->add([
            'name' => 'security',
            'type' => Csrf::class,
            'options' => [
                'csrf_options' => [
                    'timeout' => 900,
                ],
            ],
        ]);

        $this->phoneInputFilterSpec = [
            'required' => false,
            'filters'  => [
                ['name' => ToNull::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'max' => 50,
                    ],
                ],
                ['name' => Phone::class],
            ],
        ];

        $this->phoneLabelInputFilterSpec = [
            'required' => false,
            'filters' => [
                ['name' => StripTags::class],
                ['name' => StripNewlines::class],
                ['name' => StringTrim::class],
                ['name' => ToNull::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'max' => 50,
                    ],
                ],
            ],
        ];
    }

    public function setInputFilterSpecification($spec)
    {
        $this->filterSpec = $spec;
    }

    /**
     * Normally setData is called on an edit action. This will automatically decode html
     * entity fields to prevent entities from being double-encoded.
     * {@inheritDoc}
     * @see \Laminas\Form\Form::setData()
     * @todo I'm not positive this works 100%, it seemed to decode script tags well,
     * but not apostrophes. I ended up using StripTags instead.
     */
    public function setData($data)
    {
        $filterSpec = $this->getInputFilterSpecification();
        $htmlEntitiesElements = [];
        foreach ($filterSpec as $key => $value) {
            if (isset($value['filters'])) {
                foreach ($value['filters'] as $filterArray) {
                    if ($filterArray['name'] === 'HtmlEntities') {
                        $htmlEntitiesElements[] = $key;
                        break;
                    }
                }
            }
        }
        foreach ($htmlEntitiesElements as $element) {
            if (isset($data[$element]) && $this->has($element)) {
                $data[$element] = html_entity_decode($data[$element]);
            }
        }
        return parent::setData($data);
    }

    public function getIsMultiPersonUser()
    {
        return $this->isMultiPersonUser;
    }

    public function setIsMultiPersonUser($isMultiPersonUser)
    {
        $this->isMultiPersonUser = $isMultiPersonUser;
        return $this;
    }
    
    public function getAdapter()
    {
        if (! isset($this->adapter)) {
            throw new \Exception('A db adapter was requested (maybe for validation), but none was injected');
        }
        return $this->adapter;
    }
    
    public function setAdapter(\Laminas\Db\Adapter\AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }
}
