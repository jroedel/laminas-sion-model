<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\View\Helper\FormatEntity;

class FormatEntityFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $entityService = $container->get(EntitiesService::class);
        return new FormatEntity($entityService);
    }
}
