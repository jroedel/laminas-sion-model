<?php

declare(strict_types=1);

namespace SionModel\Filter;

use Laminas\Filter\Word\SeparatorToCamelCase;

use function lcfirst;

class MixedCase extends SeparatorToCamelCase
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
        $value = parent::filter($value);
        return lcfirst($value);
    }
}
