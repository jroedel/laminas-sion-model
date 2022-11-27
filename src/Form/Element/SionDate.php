<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use Laminas\Form\Element\Date;
use SionModel\Filter\ToDateTime;

class SionDate extends Date
{
    public function getInputSpecification(): array
    {
        $spec            = parent::getInputSpecification();
        $spec['filters'] = [['name' => ToDateTime::class]];
        //Date always requires this field, so let's give ourselves the option
        if (isset($this->attributes['required']) && ! $this->attributes['required']) {
            $spec['required'] = false;
        }
        return $spec;
    }
}
