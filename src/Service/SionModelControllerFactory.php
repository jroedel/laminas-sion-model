<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Db\Model\FilesTable;
use Zend\Db\Adapter\Adapter;
use SionModel\Controller\SionModelController;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class SionModelControllerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $sionModelConfig = $container->get ( 'SionModel\Config' );
        $serviceNames = $sionModelConfig['sion_controller_services'];
        if (isset($sionModelConfig['changes_model'])) {
            $serviceNames[] = $sionModelConfig['changes_model'];
        }
        $services = [];
        foreach ($serviceNames as $value) {
            if ($container->has($value)) {
                $services[$value] = $container->get($value);
            }
        }
        
        $controller = new SionModelController($services);
        
        return $controller;
    }
}
