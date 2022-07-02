<?php

declare(strict_types=1);

/**
 * SionModel Module
 */

namespace SionModel\Service;

use Exception;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EntitiesServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('SionModel\Config');
        if (empty($config['entities'])) {
            throw new Exception('Please set specify entities in app config under key [\'sion_model\'][\'entities\'].');
        }
        return new EntitiesService($config['entities']);
    }
}
