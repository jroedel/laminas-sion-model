<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Service\EntitiesService;
use SionModel\View\Helper\EditPencil;

class EditPencilFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $entityService = $container->get(EntitiesService::class);

        return new EditPencil($entityService);
    }
}
