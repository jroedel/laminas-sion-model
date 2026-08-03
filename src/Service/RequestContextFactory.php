<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use SionModel\Error\Config;
use SionModel\Error\RequestContext;
use Throwable;

/**
 * Factory responsible of priming the RequestContext service
 */
class RequestContextFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = (array) $container->get('Config');

        return new RequestContext(
            Config::appRoot($config),
            Config::notifications($config)['capture'],
            $this->userProvider($container)
        );
    }

    /**
     * The acting-user provider is optional and its absence must not be fatal.
     *
     * This service is built while the application is failing, and identity
     * resolution is one of the things that can be broken — a container that
     * cannot construct the provider must still be able to record the exception
     * that made us ask.
     *
     * @param ContainerInterface $container
     * @return ActingUserProviderInterface|null
     */
    private function userProvider(ContainerInterface $container)
    {
        if (! $container->has(ActingUserProviderInterface::class)) {
            return null;
        }
        try {
            $provider = $container->get(ActingUserProviderInterface::class);
        } catch (Throwable $e) {
            return null;
        }
        return $provider instanceof ActingUserProviderInterface ? $provider : null;
    }
}
