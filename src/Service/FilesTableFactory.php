<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Service\User;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\FilesTable;

class FilesTableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $sionModelConfig = $container->get('SionModel\Config');
        if (! isset($sionModelConfig['files_directory']) || empty($sionModelConfig['public_files_directory'])) {
            throw new Exception(
                'Please specify the \'files_directory\' and \'public_files_directory\' keys to use the FilesTable.'
            );
        }

        $dbAdapter = $container->get(Adapter::class);

        /** @var User $userService **/
        $userService  = $container->get('lmcuser_user_service');
        $user         = $userService->getAuthService()->getIdentity();
        $actingUserId = $user ? (int) $user->id : null;

        return new FilesTable($dbAdapter, $container, $actingUserId, $sionModelConfig);
    }
}
