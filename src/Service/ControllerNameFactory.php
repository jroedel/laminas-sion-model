<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\View\Helper\ControllerName;

class ControllerNameFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $routeMatch = $container->get('application')->getMvcEvent()->getRouteMatch();
        return new ControllerName($routeMatch);
    }
}
