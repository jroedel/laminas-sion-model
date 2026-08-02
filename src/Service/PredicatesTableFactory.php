<?php

/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Db\Model\PredicatesTable;
use Laminas\Db\Adapter\Adapter;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class PredicatesTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);

        $actingUserProvider = $container->has(ActingUserProviderInterface::class)
            ? $container->get(ActingUserProviderInterface::class)
            : null;

        $table = new PredicatesTable($dbAdapter, $container, $actingUserProvider);

        return $table;
    }
}
