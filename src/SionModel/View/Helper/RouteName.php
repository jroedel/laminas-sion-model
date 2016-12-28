<?php
// SionModel/View/Helper/ControllerName.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;

class RouteName extends AbstractHelper
{

    protected $routeMatch;

    public function __construct($routeMatch)
    {
        $this->routeMatch = $routeMatch;
    }

    public function __invoke()
    {
        if ($this->routeMatch) {
            return $this->routeMatch->getMatchedRouteName();
        }
    }
}
