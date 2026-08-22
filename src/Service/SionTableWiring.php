<?php

declare(strict_types=1);

namespace SionModel\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use SionModel\Db\Model\SionTable;

/**
 * The optional half of building a SionTable, done from a container in one call.
 *
 * `SionTable::__construct()` used to take the container and pull six services out of it.
 * That is what this class exists to replace, and the distinction is the whole point of the
 * change: **a factory is allowed to know about laminas-mvc and the ServiceManager, a data
 * class is not.** Everything here is framework-shaped, and none of it is reachable from
 * `SionTable` any more.
 *
 * Three things get wired, all of them genuinely optional — a table with none of them reads
 * and writes correctly, it just caches nothing, logs nothing, and renders a blank name in
 * the two columns that name a user:
 *
 * 1. the persistent cache, and with it the `MvcEvent::FINISH` listener that flushes the
 *    write queue. Resolving `Application` for its event manager was the one laminas-mvc
 *    reach inside the data layer, and it is now here instead;
 * 2. the logger;
 * 3. the user directory — as a **resolver**, never resolved here. See
 *    {@see self::wireUserDirectory()}.
 */
final class SionTableWiring
{
    /**
     * Applies all three. Safe to call on any SionTable, including the host's own user
     * table: nothing is resolved eagerly, and the table refuses to be its own directory.
     */
    public static function apply(ContainerInterface $container, SionTable $table): void
    {
        self::wireCache($container, $table);
        self::wireLogger($container, $table);
        self::wireUserDirectory($container, $table);
    }

    /**
     * The cache, plus the listener that writes the queue out at the end of the request.
     *
     * Both or neither: a cache with no flush listener collects a write queue that nobody
     * ever drains, which is not a cache at all. `wireOnFinishTrigger()` itself refuses a
     * duplicate — a second attach used to mean two passes over the queue and every key
     * appearing twice in the log.
     */
    public static function wireCache(ContainerInterface $container, SionTable $table): void
    {
        if (! $container->has('SionModel\PersistentCache')) {
            return;
        }
        $table->setPersistentCache($container->get('SionModel\PersistentCache'));

        //`has()` because a console process has a ServiceManager but no MVC Application.
        //Previously this was an unguarded get() inside the constructor.
        if ($container->has('Application')) {
            $table->wireOnFinishTrigger($container->get('Application')->getEventManager());
        }
    }

    public static function wireLogger(ContainerInterface $container, SionTable $table): void
    {
        if (! $container->has('SionModel\Logger')) {
            return;
        }
        /** @var LoggerInterface $logger */
        $logger = $container->get('SionModel\Logger');
        $table->setLogger($logger);
    }

    /**
     * Hands over a **closure**, and resolving it here would be a bug.
     *
     * The user table is itself a SionTable, so asking the container for it while another
     * table is under construction leads straight back to the half-built table that asked —
     * the cycle `ocramius/proxy-manager`'s lazy proxies used to defer past. Worse, wiring
     * the host's own user table would make it ask the container for itself. The closure
     * defers both: `SionTable::getUserDirectory()` calls it once, on the first request for
     * a name, and discards an answer identical to `$this`.
     *
     * The service id comes from `sion_model.user_directory_service`, a string rather than a
     * class constant because naming the type is what that indirection exists to stop doing.
     */
    public static function wireUserDirectory(ContainerInterface $container, SionTable $table): void
    {
        $serviceName = self::userDirectoryServiceName($container);
        if (null === $serviceName) {
            return;
        }

        $table->setUserDirectoryResolver(
            /** @return mixed */
            static fn() => $container->has($serviceName) ? $container->get($serviceName) : null
        );
    }

    private static function userDirectoryServiceName(ContainerInterface $container): ?string
    {
        $config = $container->has('SionModel\Config') ? $container->get('SionModel\Config') : [];

        //array_key_exists, not isset: null is a meaningful value — it means "this host has
        //no user directory", which is different from "this host said nothing" and must not
        //fall back to the default.
        if (! is_array($config) || ! array_key_exists('user_directory_service', $config)) {
            return 'JUser\Model\UserTable';
        }
        $serviceName = $config['user_directory_service'];

        return is_string($serviceName) && '' !== $serviceName ? $serviceName : null;
    }
}
