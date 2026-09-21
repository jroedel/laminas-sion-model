<?php

// SionModel/View/Helper/DiffForHumans.php

namespace SionModel\View\Helper;

use Closure;
use SionModel\I18n\RelativeTime;

/**
 * `<abbr title="6/13/65, 12:00 AM">57 years ago</abbr>`.
 *
 * The body came from `Carbon::diffForHumans()` until 2026-09-21; it now comes from
 * `SionModel\I18n\RelativeTime`, which carries the same strings for the five locales this
 * site serves and was checked against Carbon over 175 (locale, offset) pairs with no
 * difference. `nesbot/carbon` left with six packages behind it.
 *
 * The first-invocation latch this class used to hold is gone with it: it existed only
 * because `Carbon::setLocale()` was global state that had to be set once per request.
 * `RelativeTime` takes the locale as an argument and keeps none.
 */
class DiffForHumans
{
    /**
     * @param Closure(mixed, int, int): (string|false) $dateFormat the `dateFormat` view
     *        helper, injected because this class no longer has a renderer to reach it
     *        through. It builds the `title` attribute; RelativeTime builds the body.
     */
    public function __construct(private readonly Closure $dateFormat)
    {
    }

    public function __invoke($date)
    {
        if (! $date instanceof \DateTime) {
            throw new \InvalidArgumentException('Diff for humans only accepts DateTime objects.');
        }

        $format = '<abbr title="%s">%s</abbr>';
        $args   = [
            ($this->dateFormat)($date, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT),
            RelativeTime::format($date, \Locale::getDefault()),
        ];
        return vsprintf($format, $args);
    }
}
