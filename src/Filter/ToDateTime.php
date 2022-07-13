<?php

declare(strict_types=1);

namespace SionModel\Filter;

use DateTime;
use DateTimeZone;
use Exception;
use Laminas\Filter\AbstractFilter;

use function is_null;

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
     * @return int|mixed
     * @throws Exception
     */
    public function filter($value)
    {
        static $tz;
        if (! $tz) {
            $tz = new DateTimeZone('UTC');
        }
        if ($value instanceof DateTime) {
            return $value;
        }
        if (is_null($value) || $value === '') {
            return null;
        }
        $value = new DateTime($value, $tz);

        return $value;
    }
}
