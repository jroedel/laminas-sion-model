<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Laminas\Cache\StorageFactory;

class PersistentCacheFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('SionModel\Config');

        return StorageFactory::factory($config['persistent_cache_config']);
    }
}
