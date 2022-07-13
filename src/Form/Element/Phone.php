<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\ToNull;
use Laminas\Form\Element\Tel;
use Laminas\InputFilter\InputProviderInterface;
use Laminas\Validator\ValidatorInterface;
use SionModel\Validator\Phone as PhoneValidator;

class Phone extends Tel implements InputProviderInterface
{
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
            'name'       => $this->getName(),
            'required'   => false,
            'filters'    => [
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
