<?php

// SionModel/View/Helper/DiffForHumans.php

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Carbon\Carbon;

class DiffForHumans extends AbstractHelper
{
    protected $firstInvocation = true;

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
            $this->view->dateFormat($date, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT),
            $carbonDate->diffForHumans(),
        ];
        return vsprintf($format, $args);
    }
}
