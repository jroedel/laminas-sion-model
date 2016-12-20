<?php
/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of constructing the central collection of Entity's
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class EntitiesServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get ( 'Config' )['sion_model'];
        if (!isset($config['entities']) || empty($config['entities'])) {
            throw new \Exception('Please set specify entities in app config under key [\'sion_model\'][\'entities\'].');
        }
        $entityService = new EntitiesService($config['entities']);
		return $entityService;
    }
}
