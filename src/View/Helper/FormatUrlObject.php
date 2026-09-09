<?php

// SionModel/View/Helper/FormatUrlObject.php

namespace SionModel\View\Helper;

use SionModel\View\Escape;


class FormatUrlObject
{
    public function __invoke($url, $openInNewTab = true)
    {
        if (is_null($url)) {
            return '';
        }
        if (
            ! key_exists('url', $url) || ! key_exists('label', $url) ||
            is_null($url['url']) || is_null($url['label'])
        ) {
            throw new \InvalidArgumentException('Please pass a URL object created by SionTable::filterUrl().');
        }

        if ($openInNewTab) {
            $format = "<a href=\"%s\" target=\"_blank\">%s</a>";
        } else {
            $format = "<a href=\"%s\">%s</a>";
        }
        $return = sprintf($format, $url['url'], Escape::html((string) $url['label']));
        return $return;
    }
}
