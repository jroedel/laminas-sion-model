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
    
    public function wireOnFinishTrigger(EventManagerInterface $em, $priority = 100)
    {
        $em->attach(MvcEvent::EVENT_FINISH, [$this, 'onFinish'], $priority);
    }
    
    /**
     * Cache some entities. A simple proxy of the cache's setItem method with dependency support.
     *
     * @param string $cacheKey
     * @param mixed[] $objects
     * @param array $entityDependencies
     * @return boolean
     */
    public function cacheEntityObjects($cacheKey, &$objects, array $cacheDependencies = [])
    {
        if (!isset($this->persistentCache)) {
            throw new \Exception('The cache must be configured to cache entites.');
        }
        $cacheKey = $this->getClassIdentifier().'-'.$cacheKey;
        $this->memoryCache[$cacheKey] = $objects;
        $this->newPersistentCacheItems[] = $cacheKey;
        $this->cacheDependencies[$cacheKey] = $cacheDependencies;
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
        $key = $this->getClassIdentifier().'-'.$key;
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }
        $objects = $this->persistentCache->getItem($key, $success, $casToken);
        if ($success) {
            $this->memoryCache[$key]=$objects;
            return $this->memoryCache[$key];
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
        $key = $this->getClassIdentifier().'-'.$key;
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
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
        foreach ($this->cacheDependencies as $key => $dependentEntities) {
            if (in_array($entity, $dependentEntities) || $key == 'changes' || $key == 'problems') {
                if (is_object($cache)) {
                    $cache->removeItem($key);
                }
                if (isset($this->memoryCache[$key])) {
                    unset($this->memoryCache[$key]);
                }
            }
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
        $cacheDependencies = $this->persistentCache->getItem($this->getClassIdentifier().'cachedependencies', $hasCacheDependencies);
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
        static $filter;
        if (!is_object($filter)) {
            $filter = new FilterChain();
            $filter->attach(new StringToLower())
            ->attach(new PregReplace(['pattern' => '/\\\\/', 'replacement' => '']));
        }
        return $filter->filter(get_class($this));
    }
    
    /**
     * At the end of the page load, cache any uncached items up to max_number_of_items_to_cache.
     * This is because serializing big objects can be very memory expensive.
     */
    public function onFinish()
    {
        $maxObjects = $this->getMaxItemsToCache();
        $count = 0;
        if (is_object($this->persistentCache)) {
            $this->persistentCache->setItem($this->getClassIdentifier().'cachedependencies', $this->cacheDependencies);
            foreach ($this->newPersistentCacheItems as $key) {
                if (key_exists($key, $this->memoryCache)) {
                    $this->persistentCache->setItem($key, $this->memoryCache[$key]);
                    $count++;
                }
                if ($count >= $maxObjects) {
                    break;
                }
            }
        }
    }
}
