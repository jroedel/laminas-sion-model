<?php

/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Problem\EntityProblem;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class ProblemServiceFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('SionModel\Config');
        if (! isset($config['problem_specifications']) || empty($config['problem_specifications'])) {
            throw new \Exception('Please set specify potential problems in app config under key [\'sion_model\'][\'problem_specifications\'].');
        }

        /**
         * @var EntitiesService $entities
         */
        $entities = $container->get('SionModel\Service\EntitiesService');

        $problemPrototype = new EntityProblem($entities->getEntities(), $config['problem_specifications']);

        /**
         * @var ProblemTable $problemTable
         */
        $problemTable = $container->get('SionModel\Problem\ProblemTable');

        $problemService = new ProblemService($container, $problemTable, $config['problem_providers'], $problemPrototype);

        return $problemService;
    }
}
