<?php
// SionModel/View/Helper/ShortDateRange.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;

class ShortDateRange extends AbstractHelper
{
    protected $firstInvocation = true;
    
    public function __invoke($date1, $date2)
    {
//         var_dump($date1);
//         var_dump($date2);
        if (!$date1 instanceof \DateTime && !$date2 instanceof \DateTime) {
            return '';
        }
        $fullLeaderDate = '(';
        $shortLeaderDate = '(';
        $firstYear = null;
        $secondYear = null;
        if ($date1 instanceof \DateTime) {
            $firstYear = $date1->format('Y');
            $fullLeaderDate .= $this->view->translate("from").' '.
                $this->view->dateFormat($date1, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE).' ';
            $shortLeaderDate .= $firstYear;
        }
        if ($date2 instanceof \DateTime) {
            $secondYear = $date2->format('Y');
            $fullLeaderDate .= $this->view->translate("until").' '.
                $this->view->dateFormat($date2, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
            if (!is_null($firstYear) && $firstYear !== $secondYear) {
                //if it's not the same year, add it to the string
                $shortLeaderDate .= '-'. $secondYear;
            } elseif (is_null($firstYear)) {
                $shortLeaderDate .= $secondYear;
            }
        }
        $fullLeaderDate .= ')';
        $shortLeaderDate .= ')';
		$tooltip = '<span data-toggle="tooltip" title="%s">%s</span>';
        return sprintf($tooltip, $fullLeaderDate, $shortLeaderDate);
    }
}
