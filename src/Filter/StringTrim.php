<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function is_string;
use function preg_replace;

/**
 * Whitespace off both ends, Unicode-aware.
 *
 * The 197 uses in the specifications all pass no options, so the `charlist` option is not
 * reproduced and {@see AbstractFilter} will throw if one asks for it.
 *
 * `preg_replace` with `/u` rather than `trim()`, which is laminas' own choice and not a
 * flourish: `trim()` works on bytes, so a non-breaking space — what a visitor gets from
 * pasting out of Word — survives it, and the value reaches the column with an invisible
 * character on the front. `\s` under `/u` covers it.
 *
 * A non-string is returned untouched, which is what makes `StringTrim` safe to put in front
 * of a field that sometimes posts an array.
 */
final class StringTrim extends AbstractFilter
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        //`preg_replace` returns **null** when the subject is not valid UTF-8, and that null
        //is the answer: a value with a broken byte sequence reaches the column as NULL
        //rather than as a broken string. Coalescing it back to the input would be a kinder
        //design and a changed one — three corpus values prove it, and every `url`, `email`
        //and free-text field in the application is trimmed.
        return preg_replace('/^[\s]+|[\s]+$/usSD', '', $value);
    }
}
