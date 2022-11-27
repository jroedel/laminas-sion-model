<?php

declare(strict_types=1);

namespace SionModel\Filter;

use DateTime;
use DateTimeZone;
use Exception;
use Laminas\Filter\AbstractFilter;

use function is_string;

class ToDateTime extends AbstractFilter
{
    /**
     * Defined by Laminas\Filter\FilterInterface
     *
     * Returns (int) $value
     *
     * If the value provided is non-scalar, the value will remain unfiltered
     *
     * @param string $value
     * @return mixed
     * @throws Exception
     */
    public function filter($value)
    {
        static $tz;
        if (! isset($value) || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            return $value;
        }
        if (! $tz) {
            $tz = new DateTimeZone('UTC');
        }
        try {
            $date = new DateTime($value, $tz);
        } catch (Exception) {
            return $value;
        }
        return $date;
    }
}
