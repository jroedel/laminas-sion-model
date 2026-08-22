<?php

namespace SionModel\Db\Model;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Filter\FilterChain;
use Laminas\Filter\StringToLower;
use Laminas\Filter\PregReplace;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;

/**
 * Entity-aware caching for a SionTable: cache a query result, name the entities
 * it was built from, and drop it again when one of those entities changes.
 *
 * ## The two records this keeps, and why they must not drift apart
 *
 * A cached item is stored under its own key. What that item *depends on* is
 * stored somewhere else entirely — one map, `<class>-cachedependencies`, naming
 * every key this class has cached and the entities behind it. Invalidation reads
 * the map, so **a key missing from the map cannot be invalidated at all**: it
 * stays in the cache serving pre-change data until its own TTL runs out.
 *
 * That is not hypothetical. It took down the user list on 2026-08-09, and the
 * mechanism is worth writing down because nothing about it looks like a bug:
 *
 *   - `JUser\Cache` stores with a 1-day TTL, and both the data and the map live
 *     in it, so both expire.
 *   - A cache miss rewrites the *data* — refreshing its day — but the old code
 *     rewrote the *map* only when a key was new to it. A key already in the map
 *     refreshed nothing.
 *   - So every miss pushed the data's expiry a day into the future while the
 *     map's stood still. Within about two days of ordinary traffic the map
 *     expired underneath a perfectly live set of cached items.
 *   - From that moment `removeDependentCacheItems()` iterated an empty map and
 *     removed nothing, while `fetchCachedEntityObjects()` happily served the
 *     items it could no longer invalidate. Creating a user wrote the row and
 *     left the list showing the world as it was.
 *
 * Three properties now hold, and the unit tests in test/Unit/SionCacheTraitTest
 * assert each of them by name:
 *
 * 1. **The map is refreshed whenever the data is.** `cacheEntityObjects()`
 *    persists the map on every call, not only when it learns something new, so
 *    the map's TTL can never fall behind the items it governs.
 * 2. **An item we cannot invalidate is not served.** `fetchCachedEntityObjects()`
 *    treats a hit whose key is absent from the map as a miss. Should the map be
 *    lost anyway, the cache degrades to querying and re-registering rather than
 *    to serving stale data — the failure is slow, not wrong.
 * 3. **A snapshot older than the last change is never written.** Items are
 *    written at the end of the request, long after the data was read; a
 *    generation counter bumped on every invalidation lets `onFinishWriteCache()`
 *    recognise a snapshot that a concurrent write has already overtaken and drop
 *    it instead of resurrecting it.
 *
 * The map itself is merged rather than overwritten (`persistCacheDependencies()`),
 * because two requests registering different keys at the same time would
 * otherwise leave whichever wrote last as the only one on record — orphaning the
 * other's item exactly as an expiry does.
 */
trait SionCacheTrait
{
    /**
     * @var StorageInterface $cache
     */
    protected $persistentCache;

    /**
     * @var mixed[] $memoryCache
     */
    protected $memoryCache = [];

    /**
     * List of keys that should be persisted onFinish
     * @var array $newPersistentCacheItems
     */
    protected $newPersistentCacheItems = [];

    /**
     * Generation counter read when each queued item's data was captured.
     * Compared against the stored counter before the item is written.
     * @var array<string, int> $captureGenerations
     */
    protected $captureGenerations = [];

    /**
     * Whether the dependency map has already been re-read from the persistent
     * cache to resolve an apparent orphan. Once per request is enough: the
     * point is to notice a key another request registered after we loaded ours,
     * not to poll.
     * @var bool $reReadDependencies
     */
    protected $reReadDependencies = false;

    /**
     * @var bool $onFinishWired whether onFinishWriteCache is already attached
     */
    protected $onFinishWired = false;

    /**
     * Whoever the host wants told when this table invalidates an entity, or null.
     *
     * @var \SionModel\Cache\EntityChangeListeners|null $entityChangeListeners
     */
    protected $entityChangeListeners;

    /**
     * Maximum serialized size, in bytes, of a single persistent cache item.
     * Items above this are skipped instead of written; see
     * exceedsItemSizeBudget() for why an oversized write is worse than no
     * write at all. Zero or less disables the check.
     * @var int $maxItemSize
     */
    protected $maxItemSize = 2097152; //2 MiB

    /**
     * For each cache key, the list of entities they depend on.
     * For example:
     * [
     *      'events' => ['event', 'dates',  'emails', 'persons'],
     *      'unlinked-events => ['event'],
     * ]
     * That is to say, each time an entity of that type is created or updated,
     * the cache will be invalidated.
     * @var array $cacheDependencies
     */
    protected $cacheDependencies = [];

    /**
     * A string representing the FQN of this class for salting cache keys
     * @var string $classIdentifier
     */
    protected $classIdentifier;

    /**
     * Attach the end-of-request cache writer.
     *
     * Idempotent: `SionModel\Service\SionTableWiring` wires this, and a factory that later
     * swaps in its own namespaced cache (JUser does) used to wire it a second time, which is
     * why every JUser cache key appeared twice in the log — "Writing cache" for the same key,
     * back to back, once per listener.
     *
     * The wiring moved out of SionTable's constructor on 2026-08-22, together with the
     * container it needed to reach the MVC `Application` for an event manager. That was the
     * only laminas-mvc reach inside the data layer, and it is a factory's business now.
     *
     * **The default priority is below Laminas\\Mvc\\SendResponseListener's -10000**, and that
     * is the whole point of the number. Both listeners sit on `MvcEvent::EVENT_FINISH`, so a
     * higher priority means the cache is serialized and stored *before* the response is sent
     * and the visitor waits for it — measured at ~17 ms on the heaviest page here. The
     * Symfony host has always had this right, because `kernel.terminate` runs after the
     * response by definition; this is the laminas half catching up, and it is what makes an
     * unbounded write queue cost a visitor nothing on either front controller. Anything that
     * passes an explicit priority here should stay below -10000 for the same reason.
     */
    public function wireOnFinishTrigger(EventManagerInterface $em, $priority = -11000)
    {
        if ($this->onFinishWired) {
            return;
        }
        $this->onFinishWired = true;
        $em->attach(MvcEvent::EVENT_FINISH, [$this, 'onFinishWriteCache'], $priority);
    }

    /**
     * Cache some entities. A simple proxy of the cache's setItem method with dependency support.
     *
     * @param string $cacheKey
     * @param mixed[] $objects
     * @param array $entityDependencies Entities are abstract concepts. When it's reported that an entity changed
     *                                  all cache items that depended on it are eliminated.
     * @return boolean
     */
    public function cacheEntityObjects($cacheKey, &$objects, array $entityDependencies = [])
    {
        if (! isset($this->persistentCache)) {
            throw new \Exception('The cache must be configured to cache entites.');
        }
        $fullyQualifiedCacheKey = $this->getClassIdentifier() . '-' . $cacheKey;
        $this->memoryCache[$fullyQualifiedCacheKey] = $objects;
        if (! in_array($fullyQualifiedCacheKey, $this->newPersistentCacheItems, true)) {
            $this->newPersistentCacheItems[] = $fullyQualifiedCacheKey;
        }
        //Read *now*, before the item joins the write queue: the value this is
        //compared against at the end of the request tells us whether anything
        //changed the entity while the item sat in the queue.
        $this->captureGenerations[$fullyQualifiedCacheKey] = $this->currentGeneration();

        if (! isset($this->cacheDependencies[$fullyQualifiedCacheKey])) {
            $this->cacheDependencies[$fullyQualifiedCacheKey] = $entityDependencies;
        } else {
            //dependencies may have been reloaded from the persistent cache before this call;
            //if we hear of any new dependencies, we want to know about them
            $newDependencies = array_diff($entityDependencies, $this->cacheDependencies[$fullyQualifiedCacheKey]);
            if (! empty($newDependencies)) {
                $this->cacheDependencies[$fullyQualifiedCacheKey] = array_merge(
                    $this->cacheDependencies[$fullyQualifiedCacheKey],
                    $newDependencies
                );
            }
        }
        //Unconditionally, even when the map already said all of this. The map
        //and the item share a TTL; writing the map only when it changes lets it
        //expire under items that are still being refreshed, and an item whose
        //dependencies have expired can never be invalidated again. Persisting
        //here also gets the map out before a short-circuited request ends.
        $this->persistCacheDependencies();

        return true;
    }

    /**
     * Write the dependency map through to the persistent cache.
     *
     * Merges the stored map into ours before writing it back. Two requests that
     * register different keys at the same time both loaded the map before
     * either wrote, so a plain overwrite would drop whichever registration lost
     * the race — leaving a cached item nothing knows how to invalidate. Merging
     * makes the map grow-only, which is the safe direction: naming a key that no
     * longer exists costs one wasted removeItem call.
     *
     * This runs mid-request, deep inside query paths, so a full cache must not
     * be allowed to surface as an exception: Laminas' APCu adapter throws when
     * apcu_store() fails, which would turn a full segment into a 500 on any
     * page that happens to register a cache key.
     */
    protected function persistCacheDependencies()
    {
        if (! is_object($this->persistentCache)) {
            return;
        }
        try {
            $this->mergeStoredCacheDependencies();
            $this->persistentCache->setItem(
                $this->getDependenciesCacheKey(),
                $this->cacheDependencies
            );
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error("Error writing cache dependencies.", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Union the stored dependency map into the in-memory one.
     *
     * @return bool whether a stored map was found
     */
    protected function mergeStoredCacheDependencies()
    {
        if (! is_object($this->persistentCache)) {
            return false;
        }
        $success = false;
        $stored = $this->persistentCache->getItem($this->getDependenciesCacheKey(), $success);
        if (! $success || ! is_array($stored)) {
            return false;
        }
        foreach ($stored as $key => $dependentEntities) {
            if (! is_array($dependentEntities)) {
                continue;
            }
            if (! isset($this->cacheDependencies[$key])) {
                $this->cacheDependencies[$key] = $dependentEntities;
                continue;
            }
            $this->cacheDependencies[$key] = array_values(array_unique(
                array_merge($this->cacheDependencies[$key], $dependentEntities)
            ));
        }
        return true;
    }

    /**
     * Retrieve a cache item. A simple proxy of the cache's getItem method.
     * First we check the memoryCache, if it's not there, we look in the
     * persistent cache. If it's in the persistent cache, we set it in the
     * memory cache and return the objects. If we don't find the key we
     * return null.
     *
     * A hit we could not invalidate is reported as a miss — see mayServe().
     *
     * @param string $key
     * @param bool $success
     * @param mixed $casToken
     * @throws \Exception
     * @return mixed|null
     */
    public function &fetchCachedEntityObjects($key, &$success = null, $casToken = null)
    {
        if (! isset($this->persistentCache)) {
            throw new \Exception('Please set a cache before fetching cached entities.');
        }
        $fullyQualifiedCacheKey = $this->getClassIdentifier() . '-' . $key;
        if (isset($this->memoryCache[$fullyQualifiedCacheKey])) {
            //Already vouched for: it only reached the memory cache by being
            //served or written through the checks below.
            $success = true;
            return $this->memoryCache[$fullyQualifiedCacheKey];
        }
        $objects = $this->persistentCache->getItem($fullyQualifiedCacheKey, $success, $casToken);
        if ($success && $this->mayServe($fullyQualifiedCacheKey)) {
            $this->memoryCache[$fullyQualifiedCacheKey] = $objects;
            return $this->memoryCache[$fullyQualifiedCacheKey];
        }
        //Report the refusal as a miss, so the caller queries and re-registers.
        $success = false;
        $null = null;
        return $null;
    }

    /**
     * May this cached item be handed out?
     *
     * Only if the dependency map names it. The map is the sole record of what
     * an item depends on, so a key it does not name is a key nothing can ever
     * invalidate; serving it means serving whatever the database said when it
     * was written, indefinitely. Answering "no" costs one query and re-registers
     * the key on the way back — the cache repairs itself instead of quietly
     * going stale.
     *
     * @param string $fullyQualifiedCacheKey
     * @return bool
     */
    protected function mayServe($fullyQualifiedCacheKey)
    {
        if (isset($this->cacheDependencies[$fullyQualifiedCacheKey])) {
            return true;
        }
        //Our copy of the map is from the start of the request; another request
        //may have registered this key since. Worth one re-read, not more.
        if (! $this->reReadDependencies) {
            $this->reReadDependencies = true;
            $this->mergeStoredCacheDependencies();
            if (isset($this->cacheDependencies[$fullyQualifiedCacheKey])) {
                return true;
            }
        }
        if (isset($this->logger)) {
            $this->logger->notice("Refusing an orphaned cache item.", [
                'cacheKey' => $fullyQualifiedCacheKey,
                'reason' => 'no dependency record, so it could never be invalidated',
            ]);
        }
        return false;
    }

    /**
     * Very similar to fetchCachedEntityObjects, but only returns data if
     * a memory cached version is already available. This can be useful if
     * the program must decide between executing a delimited query or reusing
     * pre-queried data
     * @param string $key
     * @return mixed
     */
    public function &fetchMemoryCachedEntityObjects($key)
    {
        $fullyQualifiedCacheKey = $this->getClassIdentifier() . '-' . $key;
        if (isset($this->memoryCache[$fullyQualifiedCacheKey])) {
            return $this->memoryCache[$fullyQualifiedCacheKey];
        }
        $null = null;
        return $null;
    }

    /**
     * Examine the $this->cacheDependencies array to see if any depends on the entity passed.
     *
     * Re-reads the stored map first: keys registered by other requests since
     * this one started depend on the entity just as much as ours do, and the
     * map is the only place they are named.
     *
     * @param string $entity
     * @return bool
     */
    public function removeDependentCacheItems($entity)
    {
        $cache = $this->getPersistentCache();
        $this->mergeStoredCacheDependencies();
        $changesKey = $this->getClassIdentifier() . '-changes';
        $problemsKey = $this->getClassIdentifier() . '-problems';
        $removedItems = [];
        foreach ($this->cacheDependencies as $fullyQualifiedCacheKey => $dependentEntities) {
            if (
                in_array($entity, $dependentEntities, true)
                || $changesKey === $fullyQualifiedCacheKey
                || $problemsKey === $fullyQualifiedCacheKey
            ) {
                if (is_object($cache)) {
                    $cache->removeItem($fullyQualifiedCacheKey);
                    $removedItems[] = $fullyQualifiedCacheKey;
                }
                if (isset($this->memoryCache[$fullyQualifiedCacheKey])) {
                    unset($this->memoryCache[$fullyQualifiedCacheKey]);
                }
                if (isset($this->captureGenerations[$fullyQualifiedCacheKey])) {
                    unset($this->captureGenerations[$fullyQualifiedCacheKey]);
                }
            }
        }
        //Tell every request that captured a snapshot before this point to throw
        //it away rather than write it at the end of its own run. Bumped once for
        //the class, not per entity: a request holding data older than *any*
        //change here is holding data we would rather re-read than resurrect.
        $generation = $this->bumpGeneration();

        if (isset($this->logger)) {
            $this->logger->debug("An entity cache has been expired.", [
                'entity' => $entity,
                'removedItems' => $removedItems,
                'generation' => $generation,
            ]);
        }

        //Last, and inside the write: a host cache derived from this entity has to
        //expire at the same moment ours does, and this is the one place every
        //create, update and delete in every module passes through. See
        //SionModel\Cache\EntityChangeListeners for why it is not four call sites
        //in the writers instead.
        if (isset($this->entityChangeListeners)) {
            $this->entityChangeListeners->notify($entity);
        }

        return true;
    }

    /**
     * @param \SionModel\Cache\EntityChangeListeners $listeners
     * @return self
     */
    public function setEntityChangeListeners($listeners)
    {
        $this->entityChangeListeners = $listeners;
        return $this;
    }

    /**
     * The cache key holding the dependency map for this class.
     * @return string
     */
    protected function getDependenciesCacheKey()
    {
        return $this->getClassIdentifier() . '-cachedependencies';
    }

    /**
     * The cache key holding this class's invalidation counter.
     * @return string
     */
    protected function getGenerationCacheKey()
    {
        return $this->getClassIdentifier() . '-cachegeneration';
    }

    /**
     * Read this class's invalidation counter.
     *
     * A missing counter reads as zero. If it expires while a captured stamp
     * says 7, the comparison fails and the pending write is dropped — the safe
     * direction: one uncached item, never a stale one.
     *
     * @return int
     */
    protected function currentGeneration()
    {
        if (! is_object($this->persistentCache)) {
            return 0;
        }
        try {
            $success = false;
            $value = $this->persistentCache->getItem($this->getGenerationCacheKey(), $success);
            return $success ? (int) $value : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Advance this class's invalidation counter.
     *
     * incrementItem because it is the one operation the storage performs
     * atomically (apcu_inc under the APCu adapter): two requests invalidating
     * at once must not both write the same number. Adapters that cannot
     * increment a missing key answer false, which is what the fallback is for.
     *
     * @return int the new value, or 0 if the counter could not be advanced
     */
    protected function bumpGeneration()
    {
        if (! is_object($this->persistentCache)) {
            return 0;
        }
        try {
            $value = $this->persistentCache->incrementItem($this->getGenerationCacheKey(), 1);
            if (! is_int($value) && ! is_numeric($value)) {
                $this->persistentCache->setItem($this->getGenerationCacheKey(), 1);
                return 1;
            }
            return (int) $value;
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error("Error advancing the cache generation.", [
                    'exception' => $e->getMessage(),
                ]);
            }
            return 0;
        }
    }


    /**
     * Get the maxItemSize value in bytes
     * @return int
     */
    public function getMaxItemSize()
    {
        return $this->maxItemSize;
    }

    /**
     *
     * @param int $maxItemSize Bytes; zero or less disables the size check
     * @return self
     */
    public function setMaxItemSize($maxItemSize)
    {
        $this->maxItemSize = (int) $maxItemSize;
        return $this;
    }

    /**
     * Decide whether an item is too big to hand to the persistent cache.
     *
     * APCu lives in a fixed shared segment (apc.shm_size). An item bigger than
     * the free space fails to allocate, and because apc.ttl defaults to 0 that
     * failure makes APCu clear the *entire* cache instead of evicting
     * selectively — so one oversized write throws away every other cached item,
     * for every user. Refusing the write here is strictly better than learning
     * the limit from the adapter's exception, by which time the segment is
     * already gone.
     *
     * strlen(serialize()) is a proxy: APCu serializes with its own routine, so
     * the stored size differs somewhat. It is the right order of magnitude,
     * which is all a guardrail needs.
     *
     * @param string $fullyQualifiedCacheKey
     * @param mixed $value
     * @return bool
     */
    protected function exceedsItemSizeBudget($fullyQualifiedCacheKey, &$value)
    {
        $budget = $this->getMaxItemSize();
        if ($budget <= 0) {
            return false;
        }
        $size = strlen(serialize($value));
        if ($size <= $budget) {
            return false;
        }
        if (isset($this->logger)) {
            $this->logger->warning("Refusing to cache an oversized item.", [
                'cacheKey' => $fullyQualifiedCacheKey,
                'size' => $size,
                'budget' => $budget,
            ]);
        }
        return true;
    }

    /**
     * Get the cache value
     * @return StorageInterface
     */
    public function getPersistentCache()
    {
        return $this->persistentCache;
    }

    /**
     * Set — or replace — the storage this table caches in.
     *
     * Replacing it drops the dependency map that came with the old one. JUser's
     * factory does exactly that: SionTable's constructor injects the
     * application-wide `SionModel\PersistentCache`, then the factory swaps in
     * `JUser\Cache`, which is a different APCu namespace. The map read from the
     * first storage names keys that do not exist in the second, and keeping it
     * would let mayServe() vouch for items this instance can no longer reach.
     *
     * @param StorageInterface $cache
     * @return self
     */
    public function setPersistentCache($cache)
    {
        $this->persistentCache = $cache;
        $this->cacheDependencies = [];
        $this->reReadDependencies = false;
        $this->mergeStoredCacheDependencies();

        return $this;
    }

    /**
     * Get a string to identify this SionTable amongst others. Based on a transformed class name.
     * @return string
     */
    public function getClassIdentifier()
    {
        if (isset($this->classIdentifier)) {
            return $this->classIdentifier;
        }
        $filter = new FilterChain();
        $filter->attach(new StringToLower())
        ->attach(new PregReplace(['pattern' => '/\\\\/', 'replacement' => '']));
        return $this->classIdentifier = $filter->filter(get_class($this));
    }

    /**
     * At the end of the page load, write out every item this request queued.
     *
     * Deferred rather than written at `cacheEntityObjects()` time because
     * serializing a large result set mid-render charges the visitor for it. By the
     * time this runs the response is already sent — `kernel.terminate` on the
     * Symfony host, and priority -11000 on `MvcEvent::EVENT_FINISH`, i.e. below
     * SendResponseListener, on the laminas one — so what it costs, it costs nobody.
     *
     * ## There used to be a count budget here, and it was the wrong shape
     *
     * `max_items_to_cache` wrote at most N items per table per request and dropped
     * the rest, on the theory that the survivors would trickle in over subsequent
     * page loads. It was introduced to stop the flush exhausting `memory_limit`,
     * and it did not bound memory: it bounded *count*, while the thing that
     * exhausted memory was the *size* of individual items (the historical
     * offenders serialized to 29.2 and 45.7 MiB). {@see exceedsItemSizeBudget()}
     * bounds that directly and has since 2026-08.
     *
     * The trickle did work, and the measurement is why the budget is gone anyway.
     * Warming eight pages against production-scale data, at N=1 versus unbounded:
     * both end at the same 16 items and the same 8.22 MiB of segment, but N=1 takes
     * five passes to get there against one, and the whole unbounded flush costs
     * 36 ms across the pass — 17 ms in its heaviest single request, peaking at 1.29
     * MiB of PHP memory against a 512 MB limit. What the budget bought was not
     * memory; it was four extra passes of cold requests (home: 67.8 ms wall / 49.6
     * ms query at N=1, against 16.4 / 0.5 unbounded).
     *
     * And on a table written often it never converged at all — invalidation
     * outpaced a one-item-per-request refill, which is how one key
     * (`jusermodelusertable-usernames`) accounted for 23,107 of 25,394 logged
     * skips: re-queried forever, while logging that it meant to do better.
     *
     * Calling this twice in one request writes nothing the second time: the queue is
     * taken before the loop, not after it. That matters because the call can arrive
     * from two places — `MvcEvent::EVENT_FINISH` on a bridged request and
     * {@see \SionModel\Cache\CacheFlushQueue} on a Symfony-served one — and it is
     * the same property `$onFinishWired` protects on the event side.
     */
    public function onFinishWriteCache()
    {
        if (! is_object($this->persistentCache)) {
            return;
        }
        $queue = $this->newPersistentCacheItems;
        $this->newPersistentCacheItems = [];
        //One read for the whole queue: nothing between here and the last write
        //can invalidate, because invalidation happens in other requests.
        $generation = $this->currentGeneration();
        foreach ($queue as $fullyQualifiedCacheKey) {
            if (! key_exists($fullyQualifiedCacheKey, $this->memoryCache)) {
                continue;
            }
            //Someone changed the data while this snapshot sat in the queue.
            //Writing it now would put pre-change data back into the cache on top
            //of the removal that request just made, and there it would stay
            //until its TTL ran out.
            if ($this->generationMovedOn($fullyQualifiedCacheKey, $generation)) {
                if (isset($this->logger)) {
                    $this->logger->info("Discarding a cache write overtaken by a change.", [
                        'cacheKey' => $fullyQualifiedCacheKey,
                        'capturedAtGeneration' => $this->captureGenerations[$fullyQualifiedCacheKey],
                        'generation' => $generation,
                    ]);
                }
                continue;
            }
            if ($this->exceedsItemSizeBudget($fullyQualifiedCacheKey, $this->memoryCache[$fullyQualifiedCacheKey])) {
                continue;
            }
            if (isset($this->logger)) {
                $this->logger->debug("Writing cache.", ['cacheKey' => $fullyQualifiedCacheKey]);
            }
            //add some debugging information since it's often difficult to cache really large objects
            $start = microtime(true);
            $startMemory = memory_get_peak_usage(false);
            try {
                $this->persistentCache->setItem(
                    $fullyQualifiedCacheKey,
                    $this->memoryCache[$fullyQualifiedCacheKey]
                );
            } catch (\Exception $e) {
                //Laminas' APCu adapter throws when apcu_store() fails, which mostly means
                //the segment is full. Log it and carry on with the queue: the failed
                //allocation has already triggered APCu's expunge, so the next item stands
                //a good chance of fitting, and abandoning the rest guarantees a cold cache.
                //Never unset() $this->memoryCache here: unset() destroys the declared
                //property, so later $this->memoryCache reads fall through to
                //AbstractTableGateway::__get() and fatal ("Call to a member function
                //canCallMagicGet() on null") on every request until APCu is cleared —
                //the production fatal-200 bug.
                $memorySpike = (memory_get_peak_usage(false) - $startMemory) / 1024 / 1024;
                $timeElapsedSecs = microtime(true) - $start;
                if (isset($this->logger)) {
                    $this->logger->error("Error writing cache.", [
                        'cacheKey' => $fullyQualifiedCacheKey,
                        'elapsedTime' => $timeElapsedSecs,
                        'memorySpike' => $memorySpike . " MiB",
                        'exception' => $e->getMessage(),
                    ]);
                }
                continue;
            }
            $memorySpike = (memory_get_peak_usage(false) - $startMemory) / 1024 / 1024;
            $timeElapsedSecs = microtime(true) - $start;
            if (isset($this->logger)) {
                $this->logger->debug("Successfully wrote cache.", [
                    'cacheKey' => $fullyQualifiedCacheKey,
                    'elapsedTime' => $timeElapsedSecs,
                    'memorySpike' => $memorySpike . " MiB",
                ]);
            }
        }
    }

    /**
     * Has anything invalidated this class's cache since the item was captured?
     *
     * An item with no recorded stamp predates this mechanism within the request
     * (nothing queues without one) — treated as current, because refusing it
     * would be a guess in the wrong direction.
     *
     * @param string $fullyQualifiedCacheKey
     * @param int $generation the counter as it stands now
     * @return bool
     */
    protected function generationMovedOn($fullyQualifiedCacheKey, $generation)
    {
        if (! array_key_exists($fullyQualifiedCacheKey, $this->captureGenerations)) {
            return false;
        }
        return $this->captureGenerations[$fullyQualifiedCacheKey] !== $generation;
    }
}
