<?php

namespace SionModel\Service;

use Laminas\ModuleManager\Listener\ListenerOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Console\Command\ClearConfigCacheCommand;

class ClearConfigCacheCommandFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $appConfig = $container->has('ApplicationConfig') ? $container->get('ApplicationConfig') : [];
        $listenerOptions = new ListenerOptions(
            is_array($appConfig) && isset($appConfig['module_listener_options'])
            && is_array($appConfig['module_listener_options'])
                ? $appConfig['module_listener_options']
                : []
        );

        //With no cache_dir configured, ListenerOptions builds its file names
        //against an empty directory — i.e. paths at the filesystem root. Since
        //nothing was ever cached in that case, hand the command an empty list
        //rather than an unlink() target outside the application.
        $cacheDir = (string) $listenerOptions->getCacheDir();
        $files = '' === $cacheDir
            ? []
            : [$listenerOptions->getConfigCacheFile(), $listenerOptions->getModuleMapCacheFile()];

        return new ClearConfigCacheCommand($files);
    }
}
