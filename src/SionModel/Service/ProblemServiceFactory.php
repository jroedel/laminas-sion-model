<?php
/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\Problem\ProblemTable;
use SionModel\Problem\EntityProblem;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ProblemServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get ( 'SionModel\Config' );
        if (!isset($config['problem_specifications']) || empty($config['problem_specifications'])) {
            throw new \Exception('Please set specify potential problems in app config under key [\'sion_model\'][\'problem_specifications\'].');
        }

        /**
         * @var EntitiesService $entities
         */
        $entities = $serviceLocator->get ( 'SionModel\Service\EntitiesService' );

        $problemPrototype = new EntityProblem($entities->getEntities(), $config['problem_specifications']);

        /**
         * @var ProblemTable $problemTable
         */
		$problemTable = $serviceLocator->get('SionModel\Problem\ProblemTable');

        $problemService = new ProblemService($serviceLocator, $problemTable, $config['problem_providers'], $problemPrototype);

		return $problemService;
    }
}
