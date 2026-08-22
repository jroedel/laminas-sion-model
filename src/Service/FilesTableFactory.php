<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\FilesTable;

/**
 * Builds {@see FilesTable}.
 *
 * The container is no longer handed to the table. It stays here, where knowing about the
 * ServiceManager is the job: the four constructor arguments are what the table cannot work
 * without, and {@see SionTableWiring} attaches the three optional collaborators.
 */
class FilesTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): FilesTable
    {
        $sionModelConfig = $container->get('SionModel\Config');
        if (! isset($sionModelConfig['files_directory']) || empty($sionModelConfig['public_files_directory'])) {
            throw new Exception(
                'Please specify the \'files_directory\' and \'public_files_directory\' keys to use the FilesTable.'
            );
        }

        $table = new FilesTable(
            $container->get(Adapter::class),
            $container->get(EntitiesService::class),
            $sionModelConfig,
            $container->has(ActingUserProviderInterface::class)
                ? $container->get(ActingUserProviderInterface::class)
                : null,
            $sionModelConfig
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
