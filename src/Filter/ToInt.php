<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function is_scalar;

/**
 * A scalar as an integer; anything else untouched.
 *
 * The untouched half is the important half: a field that posts an array reaches the
 * database as an array rather than as `1`, which is a visible failure instead of a silent
 * wrong row.
 */
final class ToInt extends AbstractFilter
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

        return (int) $value;
    }
}
