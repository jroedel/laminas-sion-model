<?php

// SionModel/View/Helper/Tooltip.php

namespace SionModel\View\Helper;

use SionModel\View\Escape;


class Tooltip
{
    public function __invoke($text, $tooltipText, $escape = true, $placement = 'bottom')
    {
        if (is_null($text) || $text == '') {
            return '';
        }
        if (is_null($tooltipText) || $tooltipText == '') {
            return '<span>' . ($escape ? Escape::html((string) $text) : $text) . '</span>';
        }
        return '<span class="tooltip" data-toggle="tooltip" data-placement="' . $placement . '" title="' .
            ($escape ? Escape::htmlAttr((string) $tooltipText) : $tooltipText) .
            '">' . ($escape ? Escape::html((string) $text) : $text) . '</span>';
    }
}
