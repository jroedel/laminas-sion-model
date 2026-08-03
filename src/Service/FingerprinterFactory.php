<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use SionModel\Error\Config;
use SionModel\Error\Fingerprinter;

/**
 * Factory responsible of priming the Fingerprinter service
 */
class FingerprinterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new Fingerprinter(Config::appRoot((array) $container->get('Config')));
    }
}
