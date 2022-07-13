<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use Laminas\Router\RouteMatch;
use Laminas\View\Helper\AbstractHelper;

class RouteName extends AbstractHelper
{
    public function __construct(private RouteMatch $routeMatch)
    {
    }

    public function __invoke(): string
    {
        return $this->routeMatch->getMatchedRouteName();
    }
}
