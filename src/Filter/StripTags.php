<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function is_scalar;
use function preg_match_all;
use function str_replace;
use function strlen;
use function strpos;
use function substr;

/**
 * Every HTML tag and comment removed.
 *
 * 110 specification entries use this and **not one passes an option**, so the allowed-tag
 * and allowed-attribute machinery — two thirds of `SionModel\Filter\StripTags` — is not
 * reproduced. `AbstractFilter` throws on `allowTags` rather than accepting it and quietly
 * allowing nothing, which is the failure that would otherwise be invisible.
 *
 * ## Why not `strip_tags()`
 *
 * Because it answers differently, and the difference is recorded. `strip_tags()` on
 * `'<div onmouseover="x"'` — an unclosed tag, which is what a half-typed paste looks like —
 * returns the text after it; this returns the empty string, because an unterminated tag
 * consumes the rest of the input. Ten years of stored values were filtered the second way
 * and `test/Rules/rule-surface.php` records it.
 *
 * ## The two passes, transcribed
 *
 * Comments first, by scanning for `<!--` and `-->` rather than by regular expression: an
 * unterminated comment truncates the value from that point, which a regex would leave
 * alone. Then the input is matched as alternating "text, then tag" pairs; the text has any
 * stray `>` removed and the tag is dropped.
 */
final class StripTags extends AbstractFilter
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

        $value = self::withoutComments((string) $value);

        //`<?` is matched too, by `<?[^>]*>?`: the point is that everything from a `<` to
        //the next `>` is a tag, terminated or not.
        preg_match_all('/([^<]*)(<?[^>]*>?)/', $value, $matches);

        $filtered = '';
        foreach ($matches[1] as $text) {
            $filtered .= str_replace('>', '', $text);
        }

        return $filtered;
    }

    /** Everything from each `<!--` to its `-->`, or to the end if it has none. */
    private static function withoutComments(string $value): string
    {
        while (false !== ($start = strpos($value, '<!--'))) {
            $end = strpos($value, '-->', $start + 4);

            $value = false === $end
                ? substr($value, 0, $start)
                : substr($value, 0, $start) . substr($value, $end + 3);
        }

        return $value;
    }
}
