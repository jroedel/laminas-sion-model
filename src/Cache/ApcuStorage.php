<?php

declare(strict_types=1);

namespace SionModel\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Traversable;

use function apcu_cache_info;
use function apcu_clear_cache;
use function apcu_delete;
use function apcu_enabled;
use function apcu_exists;
use function apcu_fetch;
use function apcu_inc;
use function apcu_store;
use function is_array;
use function iterator_to_array;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The persistent cache on APCu, without laminas-cache.
 *
 * Two properties of APCu decide the shape of this class, and neither is new — the host's
 * docs/caching.md has carried both for a year:
 *
 * - **A segment belongs to the SAPI that created it.** A CLI process can neither read nor
 *   flush what the web server cached, which is why the host's `cache:flush-persistent`
 *   reaches the site over HTTP rather than clearing anything itself.
 * - **Under `apc.ttl=0` a failed allocation wipes the whole segment**, so an oversized item
 *   is not a slow write — it is everyone's cache gone. {@see \SionModel\Db\Model\SionCacheTrait}
 *   bounds item size before it calls `setItem()`, and this class does not second-guess it.
 *
 * There is no serializer: APCu stores PHP values as they are.
 *
 * @see Storage for why the interface is ours and not PSR-16's — the by-reference hit flag
 *      and the atomic increment.
 */
final class ApcuStorage implements Storage, CacheInterface
{
    public function __construct(
        private readonly string $namespace = '',
        private readonly int $ttl = 0
    ) {
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    public function getItem(string $key, ?bool &$success = null, mixed &$casToken = null): mixed
    {
        $success = false;
        if (! apcu_enabled()) {
            return null;
        }
        $value   = apcu_fetch($this->qualify($key), $found);
        $success = (bool) $found;

        return $success ? $value : null;
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function getItems(array $keys): array
    {
        if (! apcu_enabled() || [] === $keys) {
            return [];
        }
        $qualified = [];
        foreach ($keys as $key) {
            $qualified[] = $this->qualify($key);
        }
        $fetched = apcu_fetch($qualified);
        if (! is_array($fetched)) {
            return [];
        }
        $out = [];
        foreach ($fetched as $qualifiedKey => $value) {
            $out[$this->strip((string) $qualifiedKey)] = $value;
        }

        return $out;
    }

    public function setItem(string $key, mixed $value): bool
    {
        return apcu_enabled() && apcu_store($this->qualify($key), $value, $this->ttl);
    }

    public function removeItem(string $key): bool
    {
        return apcu_enabled() && (bool) apcu_delete($this->qualify($key));
    }

    public function incrementItem(string $key, int $by = 1): int|false
    {
        if (! apcu_enabled()) {
            return false;
        }
        //apcu_inc() is atomic, which is the whole reason the generation counter lives in
        //the cache rather than being computed. It also *creates* an absent key and returns
        //$by; see Storage::incrementItem() on why the two backends differ there.
        $new = apcu_inc($this->qualify($key), $by, $success, $this->ttl);

        return $success ? $new : false;
    }

    /**
     * Remove every key of *this namespace*, leaving the rest of the segment alone.
     *
     * Deliberately not `apcu_clear_cache()`: several caches share one segment — the
     * persistent cache, JUser's, the phrase cache — and emptying one must not take the
     * others with it. An unnamespaced cache is the exception and does clear everything,
     * because then there is nothing to tell its keys apart from anyone else's.
     *
     * This is narrower than what it replaces. `Laminas\Cache\Storage\Adapter\Apcu::flush()`
     * was `return apcu_clear_cache();` with no reference to the namespace at all, so
     * flushing any one cache emptied all of them; the namespace-scoped clear lived on a
     * separate `clearByNamespace()` nothing here called. The persistent cache configures no
     * namespace and so still clears the whole segment, which is the only flush the
     * application actually performs and the behaviour docs/DEPLOY.md relies on.
     */
    public function flush(): bool
    {
        if (! apcu_enabled()) {
            return false;
        }
        if ('' === $this->namespace) {
            return (bool) apcu_clear_cache();
        }
        $prefix = $this->namespace . ':';
        foreach ($this->keys() as $key) {
            if (str_starts_with($key, $prefix)) {
                apcu_delete($key);
            }
        }

        return true;
    }

    /** @return list<string> every key in the segment, this namespace or not */
    private function keys(): array
    {
        $info = apcu_cache_info();
        $keys = [];
        foreach ($info['cache_list'] ?? [] as $entry) {
            if (isset($entry['info'])) {
                $keys[] = (string) $entry['info'];
            }
        }

        return $keys;
    }

    private function qualify(string $key): string
    {
        return '' === $this->namespace ? $key : $this->namespace . ':' . $key;
    }

    private function strip(string $qualified): string
    {
        $prefix = '' === $this->namespace ? '' : $this->namespace . ':';

        return '' !== $prefix && str_starts_with($qualified, $prefix)
            ? substr($qualified, strlen($prefix))
            : $qualified;
    }

    // ----------------------------------------------------------------- PSR-16

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->getItem($key, $success);

        return $success ? $value : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->setItem($key, $value);
    }

    public function delete(string $key): bool
    {
        return $this->removeItem($key);
    }

    public function clear(): bool
    {
        return $this->flush();
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys  = $keys instanceof Traversable ? iterator_to_array($keys, false) : $keys;
        $found = $this->getItems($keys);
        $out   = [];
        foreach ($keys as $key) {
            $out[$key] = $found[$key] ?? $default;
        }

        return $out;
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $this->setItem((string) $key, $value) && $ok;
        }

        return $ok;
    }

    /** @param iterable<string> $keys */
    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->removeItem((string) $key) && $ok;
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        return apcu_enabled() && (bool) apcu_exists($this->qualify($key));
    }
}
