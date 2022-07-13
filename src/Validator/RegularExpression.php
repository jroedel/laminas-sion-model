<?php

declare(strict_types=1);

namespace SionModel\Validator;

use Exception;
use Laminas\Validator\AbstractValidator;
use Laminas\Validator\Regex;

class RegularExpression extends AbstractValidator
{
    public function isValid(mixed $value): bool
    {
        try {
            $validator = new Regex($value);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
}
