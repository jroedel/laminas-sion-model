<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Service\EntitiesService;
use SionModel\View\Helper\TouchButton;

class TouchButtonFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /**
         * @var EntitiesService $entities
         */
        $entities = $container->get(EntitiesService::class);
        return new TouchButton($entities->getEntities());
    }
}
