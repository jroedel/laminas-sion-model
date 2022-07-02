<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class Tooltip extends AbstractHelper
{
    public function __invoke(string $text, ?string $tooltipText, bool $escape = true, string $placement = 'bottom')
    {
        if ($text === '') {
            return '';
        }
        if (! isset($tooltipText) || $tooltipText === '') {
            return '<span>' . ($escape ? $this->view->escapeHtml($text) : $text) . '</span>';
        }
        return '<span class="tooltip" data-toggle="tooltip" data-placement="' . $placement . '" title="'
            . ($escape ? $this->view->escapeHtmlAttr($tooltipText) : $tooltipText)
            . '">' . ($escape ? $this->view->escapeHtml($text) : $text) . '</span>';
    }
}
