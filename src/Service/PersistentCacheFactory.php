<?php

namespace SionModel\Service;

use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Cache\LegacyCacheConfig;

class PersistentCacheFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('SionModel\Config');

        return $container->get(StorageAdapterFactoryInterface::class)
            ->createFromArrayConfiguration(LegacyCacheConfig::translate($config['persistent_cache_config']));
    }
}
