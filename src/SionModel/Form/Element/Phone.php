<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Form\Element;

use Zend\Filter\StringTrim;
use Zend\Filter\StripNewlines;
use Zend\Form\Element;
use Zend\InputFilter\InputProviderInterface;
use SionModel\Validator\Phone as PhoneValidator;
use Zend\Validator\ValidatorInterface;
use Zend\Form\Element\Tel;
use Zend\Filter\ToNull;

class Phone extends Tel implements InputProviderInterface
{
    /**
     * Get validator
     *
     * @return ValidatorInterface
     */
    protected function getValidator()
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
    public function getInputSpecification()
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
