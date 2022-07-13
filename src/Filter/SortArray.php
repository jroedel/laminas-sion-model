<?php

declare(strict_types=1);

namespace SionModel\Filter;

use Laminas\Filter\AbstractFilter;

use function is_array;
use function sort;

class SortArray extends AbstractFilter
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
        if (! is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return null;
        }
        sort($value);

        return $value;
    }
}
