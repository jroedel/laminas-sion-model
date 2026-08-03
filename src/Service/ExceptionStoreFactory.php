<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use SionModel\Error\Config;
use SionModel\Error\ExceptionStore;

/**
 * Factory responsible of priming the ExceptionStore service
 */
class ExceptionStoreFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config  = (array) $container->get('Config');
        $options = Config::notifications($config);

        return new ExceptionStore(
            Config::storePath($config),
            (int) $options['max_fingerprints'],
            (int) $options['ring_size'],
            (int) $options['max_write_up_bytes']
        );
    }
}
