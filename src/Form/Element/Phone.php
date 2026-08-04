<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Form\Element;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\InputFilter\InputProviderInterface;
use SionModel\Validator\Phone as PhoneValidator;
use Laminas\Validator\ValidatorInterface;
use Laminas\Form\Element;
use Laminas\Filter\ToNull;

/**
 * A telephone input backed by SionModel's own phone-number validator.
 *
 * Extends Laminas\Form\Element rather than Laminas\Form\Element\Tel, which
 * laminas marked `@final`. Tel contributed nothing but the type="tel"
 * attribute — this element already replaced Tel's input specification and
 * validator wholesale — so that attribute is simply declared here.
 */
class Phone extends Element implements InputProviderInterface
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'type' => 'tel',
    ];

    /**
     * The memoized validator. Declared here because it is no longer inherited;
     * getInputSpecification() is called on every form bind and must not build a
     * new validator each time.
     *
     * @var ValidatorInterface|null
     */
    protected $validator;

    /**
     * Get validator
     *
     * @return ValidatorInterface
     */
    protected function getValidator(): ValidatorInterface
    {
        if (null === $this->validator) {
            $this->validator = new PhoneValidator();
        }
        return $this->validator;
    }

    /**
     * Provide default input rules for this element
     *
     * @return array
     */
    public function getInputSpecification(): array
    {
        return [
            'name' => $this->getName(),
            'required' => false,
            'filters' => [
                ['name' => StringTrim::class],
                ['name' => StripNewlines::class],
                ['name' => ToNull::class],
            ],
            'validators' => [
                $this->getValidator(),
            ],
        ];
    }
}
