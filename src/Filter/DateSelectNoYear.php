<?php

declare(strict_types=1);

namespace SionModel\Filter;

use DateTime;
use DateTimeZone;
use Laminas\Filter\AbstractFilter;
use Laminas\Validator\Regex;

use function is_null;

class DateSelectNoYear extends AbstractFilter
{
    /**
     * Defined by Laminas\Filter\FilterInterface
     *
     * Returns (int) $value
     *
     * If the value provided is non-scalar, the value will remain unfiltered
     *
     * @param  string $value
     * @return int|mixed
     */
    public function filter($value)
    {
        static $tz;
        static $validator;
        if (! $validator) {
            $validator = new Regex("/\\d{4}-\\d{2}-\\d{2}/");
        }
        if (! $tz) {
            $tz = new DateTimeZone('UTC');
        }
        if ($value instanceof DateTime) {
            return $value;
        }

        if (! isset($value) || $value === '' || $value === '1900--') {
            return null;
        }
        if ($validator->isValid($value)) {
            $value = new DateTime($value, $tz);
        }
        return $value;
    }
}
