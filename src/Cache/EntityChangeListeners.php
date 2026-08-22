<?php

declare(strict_types=1);

namespace SionModel\Cache;

use function in_array;

/**
 * The host's listeners for "an entity's cache was just invalidated".
 *
 * ## Why this exists
 *
 * `SionCacheTrait::removeDependentCacheItems()` is the one place every create,
 * update and delete passes through, in every module. Anything a host derives from
 * entity data and caches separately has exactly one correct moment to expire, and
 * this is it.
 *
 * The alternative is a `clear()` call in each write path, which is how it was going
 * to be done on schoenstatt.link — four of them, across two repositories, for a
 * cache holding the assembled BjyAuthorize ACL. That is not a style preference. A
 * write path that forgets the call leaves an ACL that does not know about a role
 * somebody has just been granted, and `Laminas\Permissions\Acl\Acl::addRole()`
 * *throws* on a parent role it has never heard of — so the forgotten call is not a
 * stale page, it is a 500 on every request that user makes until the item expires.
 * One choke point cannot be forgotten.
 *
 * ## Host wiring
 *
 * Register an instance in the container under this class's name and
 * {@see \SionModel\Service\SionTableWiring::apply()} hands it to every table it
 * builds. A host that registers nothing pays nothing: the tables hold null and
 * the notify call never happens. It is an ordinary shared service rather than a
 * per-request one — unlike {@see CacheFlushQueue}, it accumulates no state.
 *
 * ## What a listener may assume
 *
 * Only that the entity's cached items have just been removed and the generation
 * counter bumped. Not that the write succeeded — `removeDependentCacheItems()` runs
 * as part of the write, and a listener throwing would take the write with it, so
 * keep the work small and total. Not that it will be called once: a request that
 * writes three entities notifies three times, and the same entity twice notifies
 * twice.
 */
final class EntityChangeListeners
{
    /** @var list<EntityChangeListenerInterface> */
    private array $listeners = [];

    public function add(EntityChangeListenerInterface $listener): void
    {
        if (in_array($listener, $this->listeners, true)) {
            return;
        }
        $this->listeners[] = $listener;
    }

    public function notify(string $entity): void
    {
        foreach ($this->listeners as $listener) {
            $listener->entityChanged($entity);
        }
    }
}
