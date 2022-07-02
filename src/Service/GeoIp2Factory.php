<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use GeoIp2\Database\Reader;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class GeoIp2Factory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config       = $container->get('Config');
        $databaseFile = $config['sion_model']['geoip2_database_file'];
        if (! isset($databaseFile)) {
            throw new Exception(
                'Unable to find GeoIp2 database file. Please set config[\'sion_model\'][\'geoip2_database_file\'].'
            );
        }
        return new Reader($databaseFile);
    }
}
