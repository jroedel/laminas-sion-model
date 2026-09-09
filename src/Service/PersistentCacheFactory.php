<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Cache\Storage;
use SionModel\Cache\StorageFactory;

use function is_array;

/**
 * The persistent cache behind every SionTable, built from `sion_model.persistent_cache_config`.
 *
 * The configuration is unchanged — still the laminas-cache shape both generations of these
 * files are written in — because {@see StorageFactory} reads it. What changed is what comes
 * out: {@see \SionModel\Cache\ApcuStorage} rather than a laminas storage adapter.
 */
class PersistentCacheFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Storage
    {
        /** @var array<string, mixed> $config */
        $config = $container->get('SionModel\Config');
        $cache  = is_array($config['persistent_cache_config'] ?? null)
            ? $config['persistent_cache_config']
            : [];

        return StorageFactory::fromConfig($cache);
    }
}
