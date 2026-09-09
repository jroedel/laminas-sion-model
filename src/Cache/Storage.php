<?php

declare(strict_types=1);

namespace SionModel\Cache;

/**
 * The cache surface {@see \SionModel\Db\Model\SionCacheTrait} needs, which is smaller than
 * any general cache interface and differs from PSR-16 in two ways that matter.
 *
 * **`getItem()` reports a hit separately from the value.** A cached `null` and a miss are
 * different things here: the trait caches query results, and "this query returned nothing"
 * is worth caching. PSR-16 collapses both into the default value, so a caller has to pick a
 * sentinel and hope it never occurs in the data. laminas-cache's by-reference `$success` is
 * the shape our code was written against, and it is kept.
 *
 * **`incrementItem()` is atomic.** It backs the generation counter that lets
 * `onFinishWriteCache()` recognise a snapshot a concurrent request has already overtaken.
 * Read-modify-write would defeat the point: two requests invalidating at once would land on
 * the same generation and one stale write would survive. Neither PSR-6 nor PSR-16 has an
 * increment, which is the single reason this interface exists rather than one of theirs.
 *
 * Implementations also implement `Psr\SimpleCache\CacheInterface`, so a consumer that wants
 * the standard interface — JTranslate's `PhraseCache` does — can take one directly, with no
 * adapter and no dependency on this package.
 */
interface Storage
{
    /**
     * @param bool|null $success set to whether the key was present. The distinction from a
     *        cached null is the point; see the class docblock.
     * @param mixed $casToken accepted and ignored, so that call sites written against
     *        laminas-cache's three-argument form keep working.
     */
    public function getItem(string $key, ?bool &$success = null, mixed &$casToken = null): mixed;

    /**
     * @param list<string> $keys
     * @return array<string, mixed> only the keys that were present
     */
    public function getItems(array $keys): array;

    public function setItem(string $key, mixed $value): bool;

    public function removeItem(string $key): bool;

    /**
     * Add to a stored integer and return the new value.
     *
     * **What an absent key does is backend-specific, and callers must handle both.** APCu
     * creates it and returns `$by`, which is what `apcu_inc()` has done since APCu 5 and
     * what the laminas adapter passed through; the filesystem implementation has nothing to
     * add to and returns `false`. `SionCacheTrait::bumpGeneration()` already covers both —
     * a non-numeric result makes it store 1 — so the two agree on the value that matters.
     *
     * @return int|false
     */
    public function incrementItem(string $key, int $by = 1): int|false;

    /** Empty this namespace. */
    public function flush(): bool;

    /** The prefix every key of this cache carries. Several caches may share a backend. */
    public function namespace(): string;

    /** Seconds an item lives for; 0 means no expiry. */
    public function ttl(): int;
}
