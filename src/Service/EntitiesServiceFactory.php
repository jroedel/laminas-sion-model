<?php
/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory responsible of constructing the central collection of Entity's
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class EntitiesServiceFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('SionModel\Config');
        if (!isset($config['entities']) || empty($config['entities'])) {
            throw new \Exception('Please set specify entities in app config under key [\'sion_model\'][\'entities\'].');
        }
        $entityService = new EntitiesService($config['entities']);
        return $entityService;
    }
}
