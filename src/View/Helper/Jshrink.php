<?php

// SionModel/View/Helper/Jshrink.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;
use JShrink\Minifier;

class Jshrink extends AbstractHelper
{
    /**
     *
     * @param string $scope
     * @param int $id
     */
    public function __invoke($script)
    {
        return Minifier::minify($script);
    }
}
