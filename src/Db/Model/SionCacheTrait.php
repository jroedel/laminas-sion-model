<?php

namespace SionModel\Db\Model;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Filter\FilterChain;
use Laminas\Filter\StringToLower;
use Laminas\Filter\PregReplace;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;

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
     * @var int $maxItemsToCache
     */
    protected $maxItemsToCache = 2;

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

    public function wireOnFinishTrigger(EventManagerInterface $em, $priority = 100)
    {
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
        if (! isset($this->cacheDependencies[$fullyQualifiedCacheKey])) {
            $this->cacheDependencies[$fullyQualifiedCacheKey] = $entityDependencies;
            //don't wait till the end of the call, because sometimes we get short circuited
            $this->persistCacheDependencies();
        } else {
            //dependencies may have been reloaded from the persistent cache before this call;
            //if we hear of any new dependencies, we want to know about them
            $newDependencies = array_diff($entityDependencies, $this->cacheDependencies[$fullyQualifiedCacheKey]);
            if (! empty($newDependencies)) {
                $this->cacheDependencies[$fullyQualifiedCacheKey] = array_merge(
                    $this->cacheDependencies[$fullyQualifiedCacheKey],
                    $newDependencies
                );
                $this->persistCacheDependencies();
            }
        }
        return true;
    }

    /**
     * Write the dependency map through to the persistent cache.
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
            $this->persistentCache->setItem(
                $this->getClassIdentifier() . '-cachedependencies',
                $this->cacheDependencies
            );
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->err("Error writing cache dependencies.", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Retrieve a cache item. A simple proxy of the cache's getItem method.
     * First we check the memoryCache, if it's not there, we look in the
     * persistent cache. If it's in the persistent cache, we set it in the
     * memory cache and return the objects. If we don't find the key we
     * return null.
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
            return $this->memoryCache[$fullyQualifiedCacheKey];
        }
        $objects = $this->persistentCache->getItem($fullyQualifiedCacheKey, $success, $casToken);
        if ($success) {
            $this->memoryCache[$fullyQualifiedCacheKey] = $objects;
            return $this->memoryCache[$fullyQualifiedCacheKey];
        }
        $null = null;
        return $null;
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
     * @param string $entity
     * @return bool
     */
    public function removeDependentCacheItems($entity)
    {
        $cache = $this->getPersistentCache();
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
            }
        }
        if (isset($this->logger)) {
            $this->logger->debug("An entity cache has been expired.", [
                'entity' => $entity,
                'removedItems' => $removedItems,
            ]);
        }

        return true;
    }

    /**
     * Get the maxItemsToCache value
     * @return int
     */
    public function getMaxItemsToCache()
    {
        return $this->maxItemsToCache;
    }

    /**
     *
     * @param int $maxItemsToCache
     * @return self
     */
    public function setMaxItemsToCache($maxItemsToCache)
    {
        $this->maxItemsToCache = $maxItemsToCache;
        return $this;
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
     * APCu lives in a fixed shared segment (apc.shm_size, 32M in production).
     * An item bigger than the free space fails to allocate, and because
     * apc.ttl defaults to 0 that failure makes APCu clear the *entire* cache
     * instead of evicting selectively — so one oversized write throws away
     * every other cached item, for every user. Refusing the write here is
     * strictly better than learning the limit from the adapter's exception,
     * by which time the segment is already gone.
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
            $this->logger->warn("Refusing to cache an oversized item.", [
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
     *
     * @param StorageInterface $cache
     * @return self
     */
    public function setPersistentCache($cache)
    {
        $this->persistentCache = $cache;

        $hasCacheDependencies = false;
        $cacheDependencies = $this->persistentCache->getItem($this->getClassIdentifier() . '-cachedependencies', $hasCacheDependencies);
        if ($hasCacheDependencies) {
            $this->cacheDependencies = $cacheDependencies;
        }

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
     * At the end of the page load, cache any uncached items up to max_number_of_items_to_cache.
     * This is because serializing big objects can be very memory expensive.
     */
    public function onFinishWriteCache()
    {
        $maxObjects = $this->getMaxItemsToCache();
        $count = 0;
        if (! is_object($this->persistentCache)) {
            return;
        }
        foreach ($this->newPersistentCacheItems as $fullyQualifiedCacheKey) {
            if ($count >= $maxObjects) {
                break;
            }
            if (! key_exists($fullyQualifiedCacheKey, $this->memoryCache)) {
                continue;
            }
            //an item we refuse on size never occupied a slot, so it doesn't
            //cost the items queued behind it their chance to be written
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
                    $this->logger->err("Error writing cache.", [
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
            $count++;
        }
    }
}
