<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\View\Helper\ControllerName;

/**
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ControllerNameFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $routeMatch = $container->get('application')->getMvcEvent()->getRouteMatch();
        $viewHelper = new ControllerName($routeMatch);
        return $viewHelper;
    }
}
