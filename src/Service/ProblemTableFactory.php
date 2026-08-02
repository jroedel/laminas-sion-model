<?php

/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Problem\ProblemTable;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class ProblemTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $dbAdapter = $container->get('Laminas\Db\Adapter\Adapter');

        // The provider defers identity resolution to write time, which is what keeps
        // the UserTable/ProblemService/ProblemTable/AuthService loop from re-forming.
        $actingUserProvider = $container->has(ActingUserProviderInterface::class)
            ? $container->get(ActingUserProviderInterface::class)
            : null;

        $table = new ProblemTable($dbAdapter, $container, $actingUserProvider);
        return $table;
    }
}
