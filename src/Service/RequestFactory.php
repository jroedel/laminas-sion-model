<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\View\Helper\Request;

class RequestFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new Request($container->get('Request'));
    }
}
