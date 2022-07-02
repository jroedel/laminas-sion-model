<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Controller\SionModelController;

class SionModelControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $sionModelConfig = $container->get('SionModel\Config');
        $serviceNames    = $sionModelConfig['sion_controller_services'];
        if (isset($sionModelConfig['changes_model'])) {
            $serviceNames[] = $sionModelConfig['changes_model'];
        }
        $services = [];
        foreach ($serviceNames as $value) {
            if ($container->has($value)) {
                $services[$value] = $container->get($value);
            }
        }

        return new SionModelController($services);
    }
}
