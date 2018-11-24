<?php
/**
 * SionModel Module
 *
 */

namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Db\Model\PredicatesTable;
use Zend\Db\Adapter\Adapter;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
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

        /** @var  User $userService **/
        $userService = $container->get('zfcuser_user_service');
        $user = $userService->getAuthService()->getIdentity();
        $actingUserId = $user ? $user->id : null;

        $table = new PredicatesTable($dbAdapter, $container, $actingUserId);

        return $table;
    }
}
