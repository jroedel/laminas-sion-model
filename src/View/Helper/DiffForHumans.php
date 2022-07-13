<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use Carbon\Carbon;
use DateTimeInterface;
use IntlDateFormatter;
use Laminas\View\Helper\AbstractHelper;
use Locale;

use function vsprintf;

class DiffForHumans extends AbstractHelper
{
    public function __construct()
    {
        Carbon::setLocale(Locale::getDefault());
    }

    public function __invoke(DateTimeInterface $date)
    {
        $carbonDate = Carbon::instance($date);
        $format     = '<abbr title="%s">%s</abbr>';
        $args       = [
            $this->view->dateFormat($date, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT),
            $carbonDate->diffForHumans(),
        ];
        return vsprintf($format, $args);
    }
}
