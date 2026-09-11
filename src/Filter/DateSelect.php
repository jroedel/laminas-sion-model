<?php

declare(strict_types=1);

namespace SionModel\Filter;

use SionModel\Filter\Exception\RuntimeException;

use function count;
use function is_array;
use function ksort;
use function sprintf;
use function vsprintf;

/**
 * The three parts a date-select element posts, joined into `Y-m-d`.
 *
 * A `<select>` trio posts `['year' => …, 'month' => …, 'day' => …]`, and a DATE column wants
 * one string. `ksort()` before the join is what makes the order right and is easy to
 * misread as tidying: the keys sort to day, month, year, and the format string
 * `%3$s-%2$s-%1$s` reverses them. Sorting by key rather than trusting the browser's order
 * is what makes it independent of how the form was assembled.
 *
 * `null_on_empty` and `null_on_all_empty` are laminas options; neither is used and neither
 * is reproduced, so an incomplete trio raises rather than silently storing a partial date.
 * {@see DateSelectNoYear} is the two-part variant and is ours already.
 */
final class DateSelect extends AbstractFilter
{
    private const FORMAT          = '%3$s-%2$s-%1$s';
    private const EXPECTED_INPUTS = 3;

    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        if (count($value) !== self::EXPECTED_INPUTS) {
            throw new RuntimeException(sprintf(
                'There are not enough values in the array to filter this date (Required: %d, Received: %d)',
                self::EXPECTED_INPUTS,
                count($value)
            ));
        }

        ksort($value);

        return vsprintf(self::FORMAT, $value);
    }
}
