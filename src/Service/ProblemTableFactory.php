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

        $user = $container->get('JUser\AuthService')->getIdentity();
        $userId = $user ? $user->id : null;
//      $userTable = $serviceLocator->get('JUser\Model\UserTable');
        $table = new ProblemTable($dbAdapter, $container, $userId);
        return $table;
    }
}
