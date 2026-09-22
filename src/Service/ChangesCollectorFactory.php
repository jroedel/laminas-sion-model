<?php

namespace SionModel\Service;

use Psr\Container\ContainerInterface;

class ChangesCollectorFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $collector = new ChangesCollector($container);
        return $collector;
    }
}
