<?php

namespace SionModel\Db\Model;

use Zend\Cache\Storage\StorageInterface;
use Zend\Filter\FilterChain;
use Zend\Filter\StringToLower;
use Zend\Filter\PregReplace;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;

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
        if (!isset($this->persistentCache)) {
            throw new \Exception('The cache must be configured to cache entites.');
        }
        $fullyQualifiedCacheKey = $this->getClassIdentifier().'-'.$cacheKey;
        $this->memoryCache[$fullyQualifiedCacheKey] = $objects;
        if (!in_array($fullyQualifiedCacheKey, $this->newPersistentCacheItems, true)) {
            $this->newPersistentCacheItems[] = $fullyQualifiedCacheKey;
        }
        //we suppose that the dependencies for a given cacheKey will not change
        if (!isset($this->cacheDependencies[$fullyQualifiedCacheKey])) {
            $this->cacheDependencies[$fullyQualifiedCacheKey] = $entityDependencies;
            //don't wait till the end of the call, because sometimes we get short circuited
            if (is_object($this->persistentCache)) {
                $this->persistentCache->setItem($this->getClassIdentifier().'-cachedependencies', $this->cacheDependencies);
            }
        }
        return true;
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
        if (!isset($this->persistentCache)) {
            throw new \Exception('Please set a cache before fetching cached entities.');
        }
        $fullyQualifiedCacheKey = $this->getClassIdentifier().'-'.$key;
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
        $fullyQualifiedCacheKey = $this->getClassIdentifier().'-'.$key;
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
        $changesKey = $this->getClassIdentifier().'-changes';
        $problemsKey = $this->getClassIdentifier().'-problems';
        $removedItems = [];
        foreach ($this->cacheDependencies as $fullyQualifiedCacheKey => $dependentEntities) {
            if (in_array($entity, $dependentEntities, true) 
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
        $cacheDependencies = $this->persistentCache->getItem($this->getClassIdentifier().'-cachedependencies', $hasCacheDependencies);
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
        if (is_object($this->persistentCache)) {
            foreach ($this->newPersistentCacheItems as $fullyQualifiedCacheKey) {
                if (key_exists($fullyQualifiedCacheKey, $this->memoryCache)) {
                    if (isset($this->logger)) {
                        $this->logger->debug("Writing cache.", ['cacheKey' => $fullyQualifiedCacheKey]);
                    }
                    try {
                        //add some debugging information since it's often difficult to cache really large objects
                        $start = microtime(true);
                        $startMemory = memory_get_peak_usage(false);
                        $this->persistentCache->setItem(
                            $fullyQualifiedCacheKey, 
                            $this->memoryCache[$fullyQualifiedCacheKey]
                            );
                        $memorySpike = (memory_get_peak_usage(false)-$startMemory)/1024/1024;
                        $timeElapsedSecs = microtime(true) - $start;
                        if (isset($this->logger)) {
                            $this->logger->debug("Successfully wrote cache.", [
                                'cacheKey' => $fullyQualifiedCacheKey,
                                'elapsedTime' => $timeElapsedSecs,
                                'memorySpike' => $memorySpike." MiB",
                            ]);
                        }
                    } catch (\Exception $e) {
                        //This probably means we've used up all the memory. Free some and continue gracefully
                        unset($this->memoryCache);
                        $memorySpike = (memory_get_peak_usage(false)-$startMemory)/1024/1024;
                        $timeElapsedSecs = microtime(true) - $start;
                        if (isset($this->logger)) {
                            $this->logger->err("Error writing cache.", [
                                'cacheKey' => $fullyQualifiedCacheKey,
                                'elapsedTime' => $timeElapsedSecs,
                                'memorySpike' => $memorySpike." MiB",
                                'exception' => $e->getMessage(),
                            ]);
                        }
                        return;
                    }
                    $count++;
                }
                if ($count >= $maxObjects) {
                    break;
                }
            }
        }
    }
}
