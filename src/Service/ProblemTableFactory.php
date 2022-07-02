<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Service\User;
use Psr\Container\ContainerInterface;
use SionModel\Problem\ProblemTable;

class ProblemTableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);

        /** @var  User $userService **/
        $userService = $container->get('lmcuser_user_service');
        $user        = $userService->getAuthService()->getIdentity();
        $userId      = $user ? (int) $user->id : null;
        return new ProblemTable($dbAdapter, $container, $userId);
    }
}
