<?php

// SionModel/I18n/View/Helper/DatePrecisionFormat.php

namespace SionModel\I18n\View\Helper;

use Laminas\View\Helper\AbstractHelper;

use function class_exists;
use function in_array;

/**
 * Render a date to the precision it is actually known to.
 *
 * Half of the dates in this database are not known to the day. The convention
 * has always been to store an unknown day as the 1st and an unknown month as
 * January, and until db6.5 there was nothing recording which values those were —
 * so "sometime in 1952" and "1 January 1952" were the same row, and both
 * rendered as "January 1, 1952". This helper is the reason the precision columns
 * exist: given the precision, it renders only what is known.
 *
 *     year   1952
 *     month  June 1952
 *     day    Jun 15, 1952      (unchanged from what the site rendered before)
 *
 * Three decisions worth keeping:
 *
 * **Day precision reproduces IntlDateFormatter::MEDIUM exactly**, because that is
 * what the 27 existing `dateFormat(...)` call sites emit. Swapping those to this
 * helper must not change a single full date on the site; only the year-only and
 * month-only ones are supposed to look different.
 *
 * **The precision string never reaches the formatter.** It comes out of a
 * varchar(20) column, so it is untrusted in the same way any other stored value
 * is; it selects a hardcoded pattern through a whitelist and an unrecognised
 * value falls back to 'day'. Passing it to IntlDateFormatter as a pattern would
 * let a stored string decide the format.
 *
 * **The formatter is pinned to UTC.** These are date-only values that
 * SionModel\Filter\ToDateTime constructed at midnight UTC. Formatting midnight
 * in a timezone behind UTC moves the date to the previous day — a birthday of
 * 15 June would render as 14 June for a viewer in New York — so the timezone has
 * to be the one the value was built in, not the viewer's.
 */
class DatePrecisionFormat extends AbstractHelper
{
    public const YEAR  = 'year';
    public const MONTH = 'month';
    public const DAY   = 'day';

    /**
     * The values events.StartDatePrecision has held since it was introduced, and
     * the values db6.5 gives the seven new columns.
     */
    public const PRECISIONS = [self::YEAR, self::MONTH, self::DAY];

    /**
     * @param \DateTimeInterface|mixed $date
     * @param string|null $precision One of PRECISIONS; anything else means 'day'.
     * @param int $dayFormat The IntlDateFormatter constant to use when the date
     *        *is* known to the day. Passed per call site rather than fixed,
     *        because the existing pages do not agree: the person page renders
     *        these dates MEDIUM and the association page renders the foundation
     *        date LONG. Flattening them to one constant would be a visible
     *        change to full dates, which this helper is specifically not for.
     * @return string
     */
    public function __invoke($date, $precision = self::DAY, $dayFormat = \IntlDateFormatter::MEDIUM)
    {
        //Deliberately not an exception. Every caller sits in a template, most
        //behind an is_object() guard already, and a helper that throws while
        //rendering takes the whole page down to say "this person has no death
        //date" — which is the normal case for most of them.
        if (! $date instanceof \DateTimeInterface) {
            return '';
        }

        if (! in_array($precision, self::PRECISIONS, true)) {
            $precision = self::DAY;
        }

        if (self::DAY === $precision) {
            return $this->format($dayFormat, null, $date);
        }

        //A skeleton, not a literal pattern: 'MMMM y' is right for English and
        //wrong for locales that order or punctuate it differently.
        //IntlDatePatternGenerator turns the skeleton into the pattern the locale
        //actually prefers. It arrived in PHP 8.0, and this capsule can still be
        //switched down to 7.4, hence the fallback.
        $skeleton = self::YEAR === $precision ? 'y' : 'yMMMM';
        $pattern  = $skeleton;
        if (class_exists('IntlDatePatternGenerator')) {
            $generator = new \IntlDatePatternGenerator($this->locale());
            $best      = $generator->getBestPattern($skeleton);
            if (false !== $best && '' !== $best) {
                $pattern = $best;
            }
        } elseif (self::MONTH === $precision) {
            $pattern = 'MMMM y';
        }

        return $this->format(\IntlDateFormatter::NONE, $pattern, $date);
    }

    /**
     * @param int $dateType
     * @param string|null $pattern
     * @param \DateTimeInterface $date
     * @return string
     */
    private function format($dateType, $pattern, \DateTimeInterface $date)
    {
        $formatter = new \IntlDateFormatter(
            $this->locale(),
            $dateType,
            \IntlDateFormatter::NONE,
            'UTC',
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );

        $formatted = $formatter->format($date);

        //IntlDateFormatter::format() returns false on failure. Returning the
        //ISO date beats returning "false" into the page.
        return false === $formatted ? $date->format('Y-m-d') : $formatted;
    }

    /**
     * @return string
     */
    private function locale()
    {
        return \Locale::getDefault();
    }
}
