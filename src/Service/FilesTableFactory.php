<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Log\LoggerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Service\User;
use Patres\Model\NodesTable;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\FilesTable;
use SionModel\I18n\LanguageSupport;
use SionModel\Problem\EntityProblem;

class FilesTableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        //@todo this should be DRYed up
        /** @var  User $userService **/
        $userService  = $container->get('lmcuser_user_service');
        $user         = $userService->getAuthService()->getIdentity();
        $actingUserId = $user ? (int) $user->id : null;

        $adapter                = $container->get(Adapter::class);
        $entitiesService        = $container->get(EntitiesService::class);
        $sionCacheService       = $container->get(SionCacheService::class);
        $entityProblemPrototype = $container->get(EntityProblem::class);
        $userTable              = $container->get(UserTable::class);
        $languageSupport        = $container->get(LanguageSupport::class);
        $logger                 = $container->get(LoggerInterface::class);
        $config                 = $container->get('Config');

        return new FilesTable(
            adapter: $adapter,
            entitySpecifications: $entitiesService->getEntities(),
            sionCacheService: $sionCacheService,
            entityProblemPrototype: $entityProblemPrototype,
            userTable: $userTable,
            languageSupport: $languageSupport,
            logger: $logger,
            actingUserId: $actingUserId,
            generalConfig: $config
        );
    }
}
