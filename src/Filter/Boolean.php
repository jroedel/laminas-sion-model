<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * A submitted value as a boolean.
 *
 * ## What `TYPE_PHP` with casting means, which is not what it sounds like
 *
 * Both uses take the defaults — type `TYPE_PHP` (127), casting on — and under those, this
 * is PHP's own truthiness with one addition: `null` is false. So `'0'` is false, `''` is
 * false, `[]` is false, `0` is false, `0.0` is false, and **`'   '` is true**, and
 * `'false'` is true.
 *
 * That last pair is not a bug to fix here. `Schoenstatt\Form\SearchForm`'s `showPhotos`
 * depends on the shape of it — an unchecked box filters to `false`, which is not empty by
 * the input filter's narrow definition, so it reaches the injected not-empty check and an
 * optional checkbox reports "Value is required and can't be empty". Reproducing that is
 * what this library is for; changing it is a decision about a form.
 *
 * `TYPE_FALSE_STRING` and `TYPE_LOCALIZED` are not reproduced: neither is in `TYPE_PHP`,
 * and no specification asks for a type at all.
 */
final class Boolean extends AbstractFilter
{
    public const TYPE_BOOLEAN     = 1;
    public const TYPE_INTEGER     = 2;
    public const TYPE_FLOAT       = 4;
    public const TYPE_STRING      = 8;
    public const TYPE_ZERO_STRING = 16;
    public const TYPE_EMPTY_ARRAY = 32;
    public const TYPE_NULL        = 64;
    public const TYPE_PHP         = 127;

    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        if (null === $value) {
            return false;
        }

        if (is_array($value)) {
            return [] !== $value;
        }

        if (is_string($value)) {
            return '' !== $value && '0' !== $value;
        }

        if (is_float($value)) {
            return 0.0 !== $value;
        }

        if (is_int($value)) {
            return 0 !== $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        //`$casting` is on, so anything else — an object, a resource — is true. laminas
        //returns the value unchanged when casting is off; nothing here turns it off.
        return true;
    }
}
