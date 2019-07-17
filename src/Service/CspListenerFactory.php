<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Mvc\CspListener;

/**
 * Factory responsible of constructing the central collection of Entity's
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class CspListenerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $cspListener = new CspListener($config);
        return $cspListener;
    }
}
