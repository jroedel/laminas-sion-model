<?php

declare(strict_types=1);

// SionModel/View/Helper/FormatUrlObject.php

namespace SionModel\View\Helper;

use InvalidArgumentException;
use Laminas\View\Helper\AbstractHelper;

use function array_key_exists;
use function sprintf;

class FormatUrlObject extends AbstractHelper
{
    public function __invoke(array $url, bool $openInNewTab = true): string
    {
        if (! isset($url)) {
            return '';
        }
        if (
            ! array_key_exists('url', $url) || ! array_key_exists('label', $url) ||
            ! isset($url['url']) || ! isset($url['label'])
        ) {
            throw new InvalidArgumentException('Please pass a URL object created by SionTable::filterUrl().');
        }

        if ($openInNewTab) {
            $format = "<a href=\"%s\" target=\"_blank\">%s</a>";
        } else {
            $format = "<a href=\"%s\">%s</a>";
        }
        return sprintf($format, $url['url'], $this->view->escapeHtml($url['label']));
    }
}
