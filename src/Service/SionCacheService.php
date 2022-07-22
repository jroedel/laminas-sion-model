<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterInterface;
use Laminas\Filter\PregReplace;
use Laminas\Filter\StringToLower;
use Laminas\Log\LoggerInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_merge;
use function debug_backtrace;
use function in_array;
use function memory_get_peak_usage;
use function microtime;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

class SionCacheService
{
    private array $memoryCache = [];

    /**
     * List of keys that should be persisted onFinish
     *
     * @var array $newPersistentCacheItems
     */
    private array $newPersistentCacheItems = [];

    /**
     * For each cache key, the list of entities they depend on.
     * For example:
     * [
     *      'events' => ['event', 'dates',  'emails', 'persons'],
     *      'unlinked-events => ['event'],
     * ]
     * That is to say, each time an entity of that type is created or updated,
     * the cache will be invalidated.
     *
     * @var array $cacheDependencies
     */
    protected array $cacheDependencies = [];

    private FilterInterface $classIdentifierFilter;

    public function __construct(
        private StorageInterface $persistentCache,
        private LoggerInterface $logger,
        protected int $maxItemsToCache = 2
    ) {
        //check if there are dependencies in the cache
        $hasCacheDependencies = false;
        $cacheDependencies    = $this->persistentCache->getItem(
            'cachedependencies',
            $hasCacheDependencies
        );
        if ($hasCacheDependencies) {
            $this->cacheDependencies = $cacheDependencies;
        }

        //prep FQCN filter
        $this->classIdentifierFilter = new FilterChain();
        $this->classIdentifierFilter->attach(new StringToLower())
            ->attach(new PregReplace(['pattern' => '/\\\\/', 'replacement' => '']));
    }

    public function flush(): bool
    {
        $success = $this->persistentCache->flush();
        if (! $success) {
            $e = new Exception();
            $this->logger->err(
                "Attempted to flush cache, but we were unsuccessful",
                ['trace' => $e->getTrace()]
            );
        }
        return $success;
    }

    /**
     * Cache some entities. A simple proxy of the cache's setItem method with dependency support.
     *
     * @param array $entityDependencies Entities are abstract concepts. When it's reported that an entity changed
     *                                  all cache items that depended on it are eliminated.
     * @throws Exception|ExceptionInterface
     */
    public function cacheEntityObjects(string $cacheKey, array &$objects, array $entityDependencies = []): void
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        Assert::keyExists($caller, 'class');
        Assert::stringNotEmpty($caller['class']);
        $filteredCallerClassName                    = $this->getClassIdentifier($caller['class']);
        $fullyQualifiedCacheKey                     = $filteredCallerClassName . '-' . $cacheKey;
        $this->memoryCache[$fullyQualifiedCacheKey] = $objects;
        if (! in_array($fullyQualifiedCacheKey, $this->newPersistentCacheItems, true)) {
            $this->newPersistentCacheItems[] = $fullyQualifiedCacheKey;
        }
        //we suppose that the dependencies for a given cacheKey will not change
        if (! isset($this->cacheDependencies[$fullyQualifiedCacheKey])) {
            $this->cacheDependencies[$fullyQualifiedCacheKey] = $entityDependencies;
            //don't wait till the end of the call, because sometimes we get short-circuited
            $this->persistentCache->setItem('cachedependencies', $this->cacheDependencies);
        } else {
            //if we hear of any new dependencies, we want to know about them
            $this->cacheDependencies[$fullyQualifiedCacheKey] = array_merge(
                $this->cacheDependencies[$fullyQualifiedCacheKey],
                $entityDependencies
            );
        }
    }

    /**
     * Retrieve a cache item. A simple proxy of the cache's getItem method.
     * First we check the memoryCache, if it's not there, we look in the
     * persistent cache. If it's in the persistent cache, we set it in the
     * memory cache and return the objects. If we don't find the key we
     * return null.
     *
     * @throws Exception
     */
    public function &fetchCachedEntityObjects(string $key, bool &$success = false): mixed
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        Assert::keyExists($caller, 'class');
        Assert::stringNotEmpty($caller['class']);
        $filteredCallerClassName = $this->getClassIdentifier($caller['class']);
        $fullyQualifiedCacheKey  = $filteredCallerClassName . '-' . $key;
        if (isset($this->memoryCache[$fullyQualifiedCacheKey])) {
            return $this->memoryCache[$fullyQualifiedCacheKey];
        }
        $objects = $this->persistentCache->getItem($fullyQualifiedCacheKey, $success);
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
     */
    public function &fetchMemoryCachedEntityObjects(string $key): mixed
    {
        Assert::notEmpty($key);
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        Assert::keyExists($caller, 'class');
        Assert::stringNotEmpty($caller['class']);
        $filteredCallerClassName = $this->getClassIdentifier($caller['class']);
        $fullyQualifiedCacheKey  = $filteredCallerClassName . '-' . $key;
        if (isset($this->memoryCache[$fullyQualifiedCacheKey])) {
            return $this->memoryCache[$fullyQualifiedCacheKey];
        }
        $null = null;
        return $null;
    }

    /**
     * Examine the $this->cacheDependencies array to see if any depends on the entity passed.
     */
    public function removeDependentCacheItems(array $entities): void
    {
        Assert::allStringNotEmpty($entities);
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        Assert::keyExists($caller, 'class');
        Assert::stringNotEmpty($caller['class']);
        //@todo instead of the caller class, maybe we should lookup the SionTable of the mentioned entity
        $filteredCallerClassName = $this->getClassIdentifier($caller['class']);
        $changesKey              = $filteredCallerClassName . '-changes';
        $problemsKey             = $filteredCallerClassName . '-problems';
        $removedItems            = [];
        foreach ($this->cacheDependencies as $fullyQualifiedCacheKey => $dependentEntities) {
            //remove the item if it's the changes table or problems of one of the dependent entities
            if (in_array($fullyQualifiedCacheKey, [$problemsKey, $changesKey])) {
                $this->persistentCache->removeItem($fullyQualifiedCacheKey);
                $removedItems[] = $fullyQualifiedCacheKey;
                if (isset($this->memoryCache[$fullyQualifiedCacheKey])) {
                    unset($this->memoryCache[$fullyQualifiedCacheKey]);
                }
                continue;
            }
            foreach ($entities as $entity) {
                if (in_array($entity, $dependentEntities, true)) {
                    $this->persistentCache->removeItem($fullyQualifiedCacheKey);
                    $removedItems[] = $fullyQualifiedCacheKey;
                    if (isset($this->memoryCache[$fullyQualifiedCacheKey])) {
                        unset($this->memoryCache[$fullyQualifiedCacheKey]);
                    }
                }
            }
        }
        if (isset($this->logger)) {
            $this->logger->debug("An entity cache has been expired.", [
                'entity'       => $entities,
                'removedItems' => $removedItems,
            ]);
        }
    }

    public function getClassIdentifier(string $fqcn): string
    {
        return $this->classIdentifierFilter->filter($fqcn);
    }

    /**
     * At the end of the page load, cache any uncached items up to max_number_of_items_to_cache.
     * This is because serializing big objects can be very memory expensive.
     */
    public function onFinishWriteCache(): void
    {
        $maxObjects = $this->maxItemsToCache;
        $count      = 0;
        foreach ($this->newPersistentCacheItems as $fullyQualifiedCacheKey) {
            if (array_key_exists($fullyQualifiedCacheKey, $this->memoryCache)) {
                if (isset($this->logger)) {
                    $this->logger->debug("Writing cache.", ['cacheKey' => $fullyQualifiedCacheKey]);
                }
                try {
                    //add some debugging information since it's often difficult to cache really large objects
                    $start       = microtime(true);
                    $startMemory = memory_get_peak_usage(false);
                    $this->persistentCache->setItem(
                        $fullyQualifiedCacheKey,
                        $this->memoryCache[$fullyQualifiedCacheKey]
                    );
                    $memorySpike     = (memory_get_peak_usage(false) - $startMemory) / 1024 / 1024;
                    $timeElapsedSecs = microtime(true) - $start;
                    if (isset($this->logger)) {
                        $this->logger->debug("Successfully wrote cache.", [
                            'cacheKey'    => $fullyQualifiedCacheKey,
                            'elapsedTime' => $timeElapsedSecs,
                            'memorySpike' => $memorySpike . " MiB",
                        ]);
                    }
                } catch (Exception $e) {
                    //This probably means we've used up all the memory. Free some and continue gracefully
                    unset($this->memoryCache);
                    $memorySpike     = (memory_get_peak_usage(false) - $startMemory) / 1024 / 1024;
                    $timeElapsedSecs = microtime(true) - $start;
                    if (isset($this->logger)) {
                        $this->logger->err("Error writing cache.", [
                            'cacheKey'    => $fullyQualifiedCacheKey,
                            'elapsedTime' => $timeElapsedSecs,
                            'memorySpike' => $memorySpike . " MiB",
                            'exception'   => $e->getMessage(),
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
