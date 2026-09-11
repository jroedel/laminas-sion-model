<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function is_scalar;
use function mb_strtolower;

/**
 * Lowercase, in the internal encoding — which is UTF-8 everywhere this runs.
 *
 * The eight uses pass no encoding, so the `encoding` option is not reproduced. `mb_` rather
 * than `strtolower`, or "Ä" is left alone while "A" is not.
 */
final class StringToLower extends AbstractFilter
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

        return mb_strtolower((string) $value);
    }
}
