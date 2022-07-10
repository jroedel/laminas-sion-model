<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Log\LoggerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class SionCacheServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $storage = $container->get('SionModel\PersistentCache');
        $config  = $container->get('config');
        $logger  = $container->get(LoggerInterface::class);
        $service = new SionCacheService(
            persistentCache: $storage,
            logger: $logger,
            maxItemsToCache: $config['sion_model']['max_items_to_cache'] ?? 2
        );
        $em      = $container->get('Application')->getEventManager();
        $em->attach(MvcEvent::EVENT_FINISH, [$service, 'onFinishWriteCache'], 100);
        return $service;
    }
}
