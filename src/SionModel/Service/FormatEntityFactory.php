<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\View\Helper\FormatEntity;

/**
 * Factory responsible of constructing the FormatEntity view helper
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class FormatEntityFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return FormatEntity
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $entityService = $serviceLocator->get ( 'SionModel\Service\EntitiesService' );

        $config = $serviceLocator->get('SionModel\Config');

        $routePermissionCheckingEnabled = isset($config['route_permission_checking_enabled']) ?
            (bool)$config['route_permission_checking_enabled'] : false;

        $viewHelper = new FormatEntity($entityService, $routePermissionCheckingEnabled);
		return $viewHelper;
    }
}
