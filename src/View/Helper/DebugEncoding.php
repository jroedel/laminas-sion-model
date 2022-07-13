<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;

use function mb_convert_encoding;
use function mb_list_encodings;

class DebugEncoding extends AbstractHelper
{
    /**
     * This view helper will display the string converted into many encodings.
     * Viewing the list, you can tell which encodings are the correct ones.
     */
    public function __invoke(string $string): string
    {
        $return = '';
        foreach (mb_list_encodings() as $chr) {
            $return .= '<span>'
                . $this->view->escapeHtml(mb_convert_encoding($string, 'UTF-8', $chr))
                . " : "
                . $chr
                . "</span><br>";
        }
        return $return;
    }
}
