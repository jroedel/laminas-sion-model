<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Problem\EntityProblem;
use SionModel\Problem\ProblemTable;
use SionModel\Service\EntitiesService;

class ProblemServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('SionModel\Config');
        if (empty($config['problem_specifications'])) {
            throw new Exception(
                'Please set specify potential problems in app config under key [\'sion_model\'][\'problem_specifications\'].'
            );
        }

        /**
         * @var EntitiesService $entities
         */
        $entities = $container->get(EntitiesService::class);

        $problemPrototype = new EntityProblem($entities->getEntities(), $config['problem_specifications']);

        /**
         * @var ProblemTable $problemTable
         */
        $problemTable = $container->get(ProblemTable::class);

        return new ProblemService($container, $problemTable, $config['problem_providers'], $problemPrototype);
    }
}
