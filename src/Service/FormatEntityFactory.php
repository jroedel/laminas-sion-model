<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\View\Helper\FormatEntity;

/**
 * Factory responsible of constructing the FormatEntity view helper
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class FormatEntityFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $entityService = $container->get ( EntitiesService::class );

        $config = $container->get('SionModel\Config');

        $routePermissionCheckingEnabled = isset($config['route_permission_checking_enabled']) ?
            (bool)$config['route_permission_checking_enabled'] : false;

        $viewHelper = new FormatEntity($entityService, $routePermissionCheckingEnabled);
		return $viewHelper;
    }
}
