<?php
// SionModel/View/Helper/DebugEncoding.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;

class DebugEncoding extends AbstractHelper
{
    /**
     * This view helper will display the string converted into many different encodings. 
     * Viewing the list, you can tell which encodings are the correct ones.
     * @param unknown $email
     * @param string $onlyGlyph
     * @return string
     */
    public function __invoke($string)
    {
        $return = '';
        foreach(mb_list_encodings() as $chr){
            $return .= '<span>'.mb_convert_encoding($string, 'UTF-8', $chr)." : ".$chr."</span><br>";
        }
    }
}
