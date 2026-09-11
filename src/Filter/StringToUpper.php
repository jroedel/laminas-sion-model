<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function is_scalar;
use function mb_strtoupper;

/** Uppercase, in the internal encoding. The mirror of {@see StringToLower}. */
final class StringToUpper extends AbstractFilter
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        if (! is_scalar($value)) {
            return $value;
        }

        return mb_strtoupper((string) $value);
    }
}
