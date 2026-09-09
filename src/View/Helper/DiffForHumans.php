<?php

// SionModel/View/Helper/DiffForHumans.php

namespace SionModel\View\Helper;

use Carbon\Carbon;
use Closure;

class DiffForHumans
{
    protected $firstInvocation = true;

    /**
     * @param Closure(mixed, int, int): (string|false) $dateFormat the `dateFormat` view
     *        helper, injected because this class no longer has a renderer to reach it
     *        through. It builds the `title` attribute; Carbon builds the body.
     */
    public function __construct(private readonly Closure $dateFormat)
    {
    }

    public function __invoke($date)
    {
        if (! $date instanceof \DateTime) {
            throw new \InvalidArgumentException('Diff for humans only accepts DateTime objects.');
        }

        if ($this->firstInvocation) {
            Carbon::setLocale(\Locale::getDefault());
            $this->firstInvocation = false;
        }

        $carbonDate = Carbon::instance($date);
        $format = '<abbr title="%s">%s</abbr>';
        $args = [
            ($this->dateFormat)($date, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT),
            $carbonDate->diffForHumans(),
        ];
        return vsprintf($format, $args);
    }
}
