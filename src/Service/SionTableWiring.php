<?php

declare(strict_types=1);

namespace SionModel\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use SionModel\Cache\CacheFlushQueue;
use SionModel\Cache\EntityChangeListeners;
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
 * Four things get wired, all of them genuinely optional — a table with none of them reads
 * and writes correctly, it just caches nothing, logs nothing, tells nobody when it
 * invalidates, and renders a blank name in the two columns that name a user:
 *
 * 1. the persistent cache, and with it the flush point that drains its write queue at the
 *    end of the request — a {@see CacheFlushQueue} the host registered, or failing that a
 *    `MvcEvent::FINISH` listener. Resolving `Application` for that event manager was the
 *    one laminas-mvc reach inside the data layer, and it is now here instead;
 * 2. the logger;
 * 3. the user directory — as a **resolver**, never resolved here. See
 *    {@see self::wireUserDirectory()};
 * 4. the host's {@see EntityChangeListeners}, told whenever this table invalidates an
 *    entity, for a host cache derived from entity data that has to expire with ours.
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
        self::wireEntityChangeListeners($container, $table);
    }

    /**
     * Whoever the host wants told when this table invalidates an entity.
     *
     * Wired outside {@see wireCache()} on purpose, and it is not a tidiness point: a
     * listener is about *writes*, not about caching, so a host that configures no
     * persistent cache still gets told. Registering nothing costs nothing — the table
     * holds null and never calls out.
     */
    public static function wireEntityChangeListeners(ContainerInterface $container, SionTable $table): void
    {
        if (! $container->has(EntityChangeListeners::class)) {
            return;
        }
        /** @var EntityChangeListeners $listeners */
        $listeners = $container->get(EntityChangeListeners::class);
        $table->setEntityChangeListeners($listeners);
    }

    /**
     * The cache, plus whatever will write the queue out at the end of the request.
     *
     * Both or neither: a cache with no flush point collects a write queue that nobody
     * ever drains, which is not a cache at all — it is a per-request memoization that
     * pays the bookkeeping cost of a cache and returns none of the benefit. That is
     * exactly what a Symfony-served route had, silently, for eleven days; see
     * {@see CacheFlushQueue}.
     */
    public static function wireCache(ContainerInterface $container, SionTable $table): void
    {
        if (! $container->has('SionModel\PersistentCache')) {
            return;
        }
        $table->setPersistentCache($container->get('SionModel\PersistentCache'));
        self::wireFlushPoint($container, $table);
    }

    /**
     * Whatever calls `onFinishWriteCache()` for this host, and there are two.
     *
     * The queue wins when the host registered one, and then the MVC `Application` is
     * deliberately **not** resolved: under a Symfony front controller `has('Application')`
     * answers true — laminas-mvc's own module config defines the service whether or not
     * anything ever bootstraps it — so the old code built an MVC application, took its
     * event manager and attached a listener to an event that request would never fire.
     * Every table, every ported request.
     *
     * `has()` on the fallback for the same reason it was always there: a console process
     * has a ServiceManager but no MVC Application. A process with neither gets no flush
     * point at all, which is correct — see {@see CacheFlushQueue} on why a CLI run must
     * not write this cache.
     */
    public static function wireFlushPoint(ContainerInterface $container, SionTable $table): void
    {
        if ($container->has(CacheFlushQueue::class)) {
            /** @var CacheFlushQueue $queue */
            $queue = $container->get(CacheFlushQueue::class);
            $queue->register($table);

            return;
        }

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
