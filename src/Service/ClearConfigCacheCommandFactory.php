<?php

namespace SionModel\Service;

use Psr\Container\ContainerInterface;
use SionModel\Console\Command\ClearConfigCacheCommand;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;

class ClearConfigCacheCommandFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        //The host names its own cache files: it is the side that writes them, and until
        //2026-09-21 this factory derived them a second time through
        //`Laminas\ModuleManager\Listener\ListenerOptions`. That package is gone, and a
        //copy of the naming rule here would be a second owner of it either way — so the
        //host registers the list and this asks for it.
        //
        //Absent, the command clears nothing, which is the honest answer for a host that
        //caches no merged configuration.
        $files = $container->has('ConfigCacheFiles') ? $container->get('ConfigCacheFiles') : [];

        return new ClearConfigCacheCommand(
            is_array($files) ? array_values(array_filter($files, is_string(...))) : []
        );
    }
}
