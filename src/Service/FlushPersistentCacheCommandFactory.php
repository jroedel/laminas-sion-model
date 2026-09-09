<?php

namespace SionModel\Service;

use Symfony\Component\HttpClient\HttpClient;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Console\Command\FlushPersistentCacheCommand;

class FlushPersistentCacheCommandFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->has('SionModel\Config') ? $container->get('SionModel\Config') : [];
        if (! is_array($config)) {
            $config = [];
        }

        //Run on the server, the configured keys are the production ones, so the
        //flush needs no secret passed in on the command line at all.
        $apiKeys = isset($config['api_keys']) && is_array($config['api_keys'])
            ? array_values(array_filter($config['api_keys'], 'is_string'))
            : [];

        $baseUrl = isset($config['canonical_base_url']) && is_string($config['canonical_base_url'])
            ? $config['canonical_base_url']
            : '';

        return new FlushPersistentCacheCommand(HttpClient::create(), $baseUrl, $apiKeys);
    }
}
