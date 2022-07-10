<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Problem\EntityProblem;

class EntityProblemFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config   = $container->get('config');
        $entities = $container->get(EntitiesService::class);
        return new EntityProblem($entities->getEntities(), $config['sion_model']['problem_specifications'] ?? []);
    }
}
