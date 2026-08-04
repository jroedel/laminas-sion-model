<?php

namespace SionModel\Cache;

/**
 * Normalizes opcache_get_status() into the shape /sm/cache-status reports.
 *
 * Split out from the controller because it is pure array arithmetic over values
 * the caller supplies, which makes it testable without a container, a request or
 * even a loaded OPcache — the same reasoning as LegacyCacheConfig.
 *
 * Why this endpoint reports OPcache at all: like APCu, an OPcache segment
 * belongs to the SAPI that created it (`opcache.enable_cli` is off), so a CLI
 * probe sees its own empty cache and tells you nothing about what the web server
 * is doing. Only a request into the web SAPI can answer that.
 *
 * The numbers chosen here are the ones that distinguish "healthy" from
 * "silently degraded". OPcache does not slow down gracefully: when it runs out
 * of memory or hash slots it *restarts*, throwing away every compiled script,
 * and the only lasting evidence is a restart counter. Those counters are
 * therefore the OPcache analogue of APCu's `expunges`.
 */
final class OpcacheStatus
{
    /**
     * @param array<string, mixed>|false|null $status Result of opcache_get_status(false).
     *        false is what the function returns when OPcache is disabled.
     * @param array<string, string|false> $ini Raw ini_get() values, keyed by
     *        directive name (opcache.validate_timestamps, opcache.revalidate_freq,
     *        opcache.max_accelerated_files).
     * @return array<string, mixed>
     */
    public static function summarize($status, array $ini = []): array
    {
        if (! is_array($status) || empty($status['opcache_enabled'])) {
            return ['enabled' => false];
        }

        $mem = is_array($status['memory_usage'] ?? null) ? $status['memory_usage'] : [];
        $stats = is_array($status['opcache_statistics'] ?? null) ? $status['opcache_statistics'] : [];
        $interned = is_array($status['interned_strings_usage'] ?? null) ? $status['interned_strings_usage'] : [];
        $jit = is_array($status['jit'] ?? null) ? $status['jit'] : [];

        $used = (int) ($mem['used_memory'] ?? 0);
        $free = (int) ($mem['free_memory'] ?? 0);
        $wasted = (int) ($mem['wasted_memory'] ?? 0);
        //OPcache reports no total: the segment is exactly the sum of the three
        $total = $used + $free + $wasted;

        $cachedKeys = (int) ($stats['num_cached_keys'] ?? 0);
        //max_cached_keys is NOT opcache.max_accelerated_files. PHP rounds the
        //hash table up to the next prime, so a configured 10000 reports 16229 —
        //and the table size is the real ceiling. Comparing usage against the
        //configured number understates the headroom, which is exactly the wrong
        //way to be wrong about a cache that flushes when it fills.
        $maxKeys = (int) ($stats['max_cached_keys'] ?? 0);

        $hits = (int) ($stats['hits'] ?? 0);
        $misses = (int) ($stats['misses'] ?? 0);
        $lookups = $hits + $misses;

        $startTime = (int) ($stats['start_time'] ?? 0);

        return [
            'enabled' => true,
            //the single clearest signal: OPcache itself saying it has no room left
            'cacheFull' => (bool) ($status['cache_full'] ?? false),
            'restartPending' => (bool) ($status['restart_pending'] ?? false),
            'restartInProgress' => (bool) ($status['restart_in_progress'] ?? false),

            'memoryTotalBytes' => $total,
            'memoryUsedBytes' => $used,
            'memoryFreeBytes' => $free,
            'memoryWastedBytes' => $wasted,
            'memoryPercentUsed' => self::percent($used + $wasted, $total),
            'wastedPercent' => self::round($mem['current_wasted_percentage'] ?? null),

            'cachedScripts' => (int) ($stats['num_cached_scripts'] ?? 0),
            'cachedKeys' => $cachedKeys,
            'maxCachedKeys' => $maxKeys,
            'keysPercentUsed' => self::percent($cachedKeys, $maxKeys),

            'hits' => $hits,
            'misses' => $misses,
            'hitRatePercent' => self::percent($hits, $lookups),

            //non-zero means the cache has already been thrown away at least once:
            //oom = out of memory, hash = the script table filled, manual = a
            //deliberate opcache_reset()
            'oomRestarts' => (int) ($stats['oom_restarts'] ?? 0),
            'hashRestarts' => (int) ($stats['hash_restarts'] ?? 0),
            'manualRestarts' => (int) ($stats['manual_restarts'] ?? 0),

            'internedPercentUsed' => self::percent(
                (int) ($interned['used_memory'] ?? 0),
                (int) ($interned['buffer_size'] ?? 0)
            ),
            'internedStrings' => (int) ($interned['number_of_strings'] ?? 0),

            //surfaced because it decides whether a deploy needs a pool restart:
            //with timestamp validation off, changed files are never noticed
            'validateTimestamps' => self::flag($ini['opcache.validate_timestamps'] ?? null),
            'revalidateFreq' => (int) ($ini['opcache.revalidate_freq'] ?? 0),
            'maxAcceleratedFilesConfigured' => (int) ($ini['opcache.max_accelerated_files'] ?? 0),
            'jitEnabled' => (bool) ($jit['on'] ?? false),

            'uptimeSeconds' => $startTime > 0 ? max(0, self::now() - $startTime) : null,
        ];
    }

    /**
     * Overridable clock so uptime is assertable in a test.
     *
     * @var callable|null
     */
    public static $clock = null;

    private static function now(): int
    {
        $clock = self::$clock;
        return null === $clock ? time() : (int) $clock();
    }

    private static function percent(int $part, int $whole): ?float
    {
        if ($whole <= 0) {
            return null;
        }
        return round($part * 100 / $whole, 1);
    }

    /**
     * @param mixed $value
     */
    private static function round($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    /**
     * ini_get() hands back '1'/'0'/'' rather than a bool.
     *
     * @param mixed $value
     */
    private static function flag($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
