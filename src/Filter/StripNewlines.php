<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function array_map;
use function is_array;
use function is_scalar;
use function str_replace;

/**
 * Carriage returns and line feeds removed, with nothing put in their place.
 *
 * Note that it **joins** rather than separates: "line one\nline two" becomes
 * "line oneline two". That is laminas' behaviour and 93 specifications were written against
 * it, mostly on single-line fields where a newline only ever arrives by paste.
 *
 * Scalars are cast to string first and an array is filtered element-wise, which is
 * `AbstractFilter::applyFilterOnlyToStringableValuesAndStringableArrayValues()` — reproduced
 * inline here, because it was one shared helper for a handful of filters and reading it
 * where it is used is worth more than sharing it.
 */
final class StripNewlines extends AbstractFilter
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        if (is_array($value)) {
            //Scalars become strings and everything else is left alone — and then
            //`str_replace` is handed the **whole array**, which is where the odd part is:
            //it casts an element that is still an array to the string "Array" and raises a
            //warning doing it. That is laminas' behaviour, recorded, and reproducing it is
            //what stops a nested array arriving at a column as a literal "Array" in one
            //release and as an array in the next.
            return str_replace(["\n", "\r"], '', array_map(
                static fn(mixed $item): mixed => is_scalar($item) ? (string) $item : $item,
                $value
            ));
        }

        if (! is_scalar($value)) {
            return $value;
        }

        return str_replace(["\n", "\r"], '', (string) $value);
    }
}
