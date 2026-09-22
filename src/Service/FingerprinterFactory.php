<?php

namespace SionModel\Service;

use Psr\Container\ContainerInterface;
use SionModel\Error\Config;
use SionModel\Error\Fingerprinter;

/**
 * Factory responsible of priming the Fingerprinter service
 */
class FingerprinterFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new Fingerprinter(Config::appRoot((array) $container->get('Config')));
    }
}
