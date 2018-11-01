<?php
// SionModel/View/Helper/Tooltip.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;

class Tooltip extends AbstractHelper
{
    public function __invoke($text, $tooltipText, $escape = true, $placement = 'bottom')
    {
        if (is_null($text) || $text == '') {
            return '';
        }
        if (is_null($tooltipText) || $tooltipText == '') {
            return '<span>'.($escape ? $this->escapeHtml($text) : $text) . '</span>';
        }
        return '<span class="tooltip" data-toggle="tooltip" data-placement="'.$placement.'" title="'.
            ($escape ? $this->view->escapeHtmlAttr($tooltipText) : $tooltipText).
            '">'.($escape ? $this->view->escapeHtml($text) : $text) . '</span>';
    }
}
