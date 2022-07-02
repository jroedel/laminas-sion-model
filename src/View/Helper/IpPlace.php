<?php

declare(strict_types=1);

// JUser/View/Helper/Email.php

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;

use function sprintf;

class IpPlace extends AbstractHelper
{
    public function __invoke(string $ipAddress): string
    {
        $record = $this->view->geoIp2City($ipAddress);
        $return = '';
        if ($record?->city?->name) {
            $return .= $this->view->escapeHtml($record->city->name) . ", ";
        }
        if ($record?->country?->name) {
            $return .= $this->view->escapeHtml($record->country->name);
        }
        if ('' === $return) {
            return $this->view->escapeHtml($ipAddress);
        }
        $tooltip = '<span data-toggle="tooltip" title="%s">%s</span>';
        return sprintf($tooltip, $this->view->escapeHtmlAttr($ipAddress), $return);
    }
}
