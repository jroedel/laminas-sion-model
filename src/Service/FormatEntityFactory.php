<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\View\Helper\FormatEntity;

class FormatEntityFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $entityService = $container->get(EntitiesService::class);

        $config = $container->get('SionModel\Config');

        $routePermissionCheckingEnabled = isset($config['route_permission_checking_enabled'])
            && $config['route_permission_checking_enabled'];

        return new FormatEntity($entityService, $routePermissionCheckingEnabled);
    }
}
