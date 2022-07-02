<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Service\User;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\PredicatesTable;

class PredicatesTableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);

        /** @var  User $userService **/
        $userService  = $container->get('lmcuser_user_service');
        $user         = $userService->getAuthService()->getIdentity();
        $actingUserId = $user ? (int) $user->id : null;

        return new PredicatesTable($dbAdapter, $container, $actingUserId);
    }
}
