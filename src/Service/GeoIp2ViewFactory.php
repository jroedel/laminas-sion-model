<?php

declare(strict_types=1);

namespace SionModel\Service;

use GeoIp2\Database\Reader;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\View\Helper\GeoIp2City;

class GeoIp2ViewFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $geo = $container->get(Reader::class);
        return new GeoIp2City($geo);
    }
}
