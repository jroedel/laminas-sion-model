<?php

namespace SionModel\Filter;

use function is_scalar;
use function is_string;
use function str_contains;
use function str_replace;
use function trim;

/**
 * The single decision about whether a submitted value is a storable date.
 *
 * SionModel\Filter\ToDateTime and SionModel\Validator\ParseableDate are two
 * halves of one rule — the filter converts, the validator reports what the
 * filter could not convert — and they are only correct if they agree exactly.
 * When each implemented the rule itself they did not: the validator retried the
 * parse with plain `new \DateTime($string)` and so accepted three families of
 * value the filter had no business storing. That is why the decision lives here
 * and nowhere else.
 *
 * What \DateTime accepts that a date column must not, all measured on PHP 8.4:
 *
 *  - **A NUL byte ends the parse.** `"\0"` and `"a\0b"` both yield *now*, so a
 *    NUL in a birth-date field silently stored today's date. This is the worst
 *    of the three because the value looks deliberate afterwards.
 *  - **Whitespace-only yields *now*** for the same reason: the parser reaches
 *    the end of input having found nothing, and empty input means "now".
 *  - **`0000-00-00` yields year -1** (specifically -0001-11-30, by overflow).
 *    MySQL's DATE range is 1000-01-01 to 9999-12-31, so the value cannot round
 *    trip: it either errors on write or lands as a zero date.
 *
 * Deliberately *not* decided here: whether a storable date is a *plausible*
 * one. `tomorrow`, `+500 years` and the year 9999 all pass, and `2020-02-30`
 * still becomes 2020-03-01 by \DateTime's own overflow rules. Bounding a date
 * is a per-field question about what this database records — a birth date and a
 * library checkout want different answers — so it belongs in that field's
 * specification. The line drawn here is only "can this be stored in a date
 * column at all", which is a property of the column, not a business rule.
 */
final class DateTimeParser
{
    /**
     * MySQL's DATE/DATETIME lower and upper bounds. A parsed value outside them
     * cannot round trip through the column, so it is treated as unstorable
     * rather than passed on for the database to reject.
     */
    public const MIN_YEAR = 1000;
    public const MAX_YEAR = 9999;

    /**
     * The value a caller should pass on when parse() has refused it.
     *
     * Refusing a value is not enough: it still travels down the rest of the
     * validator chain so that something can report it, and a NUL byte is not
     * safe to hand on. `Laminas\Validator\Date` — which the Date form element
     * contributes ahead of anything a form specification adds, so it cannot be
     * reordered from there — calls DateTime::createFromFormat() and PHP raises
     * `ValueError: must not contain any null bytes`. That escapes
     * InputFilter::isValid() as a 500, which is the exact failure this pair of
     * classes was written to remove; refusing to convert the value merely moved
     * the throw one layer down.
     *
     * So the NUL bytes come out and everything else is preserved. "a\0b"
     * becomes "ab", which is still not a date, so the chain reports it normally
     * and the user sees an error about the value they submitted rather than
     * losing the whole form. Returning null instead would be safe but silent,
     * and non-strings are returned untouched — an array cannot carry a NUL byte
     * into a string function.
     *
     * @param  mixed $value
     * @return mixed
     */
    public static function sanitizeRejected($value)
    {
        return is_string($value) ? str_replace("\0", '', $value) : $value;
    }

    /**
     * Three outcomes, because the callers need to tell them apart:
     *
     *  - a \DateTime — parsed and storable. Mutable, and that is not a
     *    preference: better than twenty places across the application test
     *    `$value instanceof \DateTime`, including LibraryTable's checkedOutOn
     *    handling, and \DateTimeImmutable does not satisfy that. Returning the
     *    immutable type here would quietly flip all of them to false and change
     *    both rendering and write behaviour.
     *  - null — the value means "nothing was entered". Emptiness is
     *    `required`/NotEmpty's business, so both callers treat this as fine.
     *  - false — not a storable date. The filter passes the value on (via
     *    sanitizeRejected()) so the validator can see it and report it;
     *    returning null there would be indistinguishable from a blank field and
     *    would discard bad input in silence.
     *
     * Never throws. A throw from either caller escapes
     * InputFilter::isValid() and becomes a 500 with the whole submission lost,
     * which is the bug this pair of classes exists to have fixed.
     *
     * @param  mixed $value
     * @return \DateTime|null|false
     */
    public static function parse($value)
    {
        if ($value instanceof \DateTime) {
            return $value;
        }

        //A DateTimeImmutable arriving here is converted rather than passed
        //through, for the instanceof reason above.
        if ($value instanceof \DateTimeInterface) {
            return \DateTime::createFromInterface($value);
        }

        if (null === $value) {
            return null;
        }

        //An array or object cannot be a date, and `name[]=x` in a POST is
        //enough to produce one. Reported rather than ignored: silently
        //accepting it is how a TypeError used to reach the user as a 500.
        if (! is_scalar($value)) {
            return false;
        }

        //Cast before testing emptiness: new \DateTime('') is *now*, not an
        //error, and false casts to ''. Trim before testing it so that a
        //whitespace-only value is "nothing entered" rather than today.
        $string = trim((string) $value);
        if ('' === $string) {
            return null;
        }

        //Checked explicitly because \DateTime would not fail on it — it stops
        //at the NUL and parses the empty remainder as now.
        if (str_contains($string, "\0")) {
            return false;
        }

        try {
            $parsed = new \DateTime($string, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            //\DateMalformedStringException on 8.3+, plain \Exception before it.
            //Caught by the base class so this keeps working on every PHP rung
            //the capsule can be switched to.
            return false;
        }

        //A four-digit year is not guaranteed: 0000-00-00 arrives here as year
        //-1, and \DateTime is happy to represent years outside the column's
        //range in either direction.
        $year = (int) $parsed->format('Y');
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            return false;
        }

        //Note what is deliberately still accepted: '2020-02-30' becomes
        //2020-03-01 by \DateTime's documented overflow, and 'tomorrow' or
        //'+3 days' resolve to real dates. Both are storable, so neither is this
        //class's call — see the plausibility note in the class docblock.
        return $parsed;
    }
}
