<?php

namespace SionModel\Cache;

/**
 * Normalizes APCu's info calls into the shape /sm/cache-status reports.
 *
 * Split out from the controller for the same reason as OpcacheStatus: it is pure
 * array arithmetic over values the caller supplies, so it is testable without a
 * container, a request, or even a loaded APCu — and, unlike the controller
 * method it replaces, it is reachable from more than one front controller.
 *
 * Why the endpoint reports this at all: an APCu segment belongs to the SAPI that
 * created it, so a CLI probe reads its own empty copy and learns nothing about
 * what the web server is serving (see docs/caching.md).
 *
 * The numbers chosen here are the ones that distinguish "healthy" from "silently
 * degraded". APCu with apc.ttl=0 does not slow down gracefully when the segment
 * fills: a failed allocation expunges *everything*, so `expunges` is the value
 * that matters most and `largestEntries` is what says which key caused it.
 */
final class ApcuStatus
{
    /** How many of the biggest entries to name. */
    public const LARGEST_ENTRIES = 15;

    /**
     * @param array<string, mixed>|false|null $sma Result of apcu_sma_info(true).
     *        false is what the function returns when APCu is not available.
     * @param array<string, mixed>|false|null $cache Result of apcu_cache_info(true),
     *        i.e. the counters without the entry list.
     * @param array<string, mixed>|false|null $entryList Result of apcu_cache_info()
     *        — the same call *with* `cache_list`. Passed separately because it is
     *        the expensive one: it walks every entry.
     * @return array<string, mixed>
     */
    public static function summarize($sma, $cache, $entryList = null, int $limit = self::LARGEST_ENTRIES): array
    {
        if (! is_array($sma) || ! is_array($cache)) {
            return ['apcuEnabled' => false];
        }

        $totalBytes = (int) (($sma['num_seg'] ?? 0) * ($sma['seg_size'] ?? 0));
        $availBytes = (int) ($sma['avail_mem'] ?? 0);
        $usedBytes  = $totalBytes - $availBytes;

        return [
            'apcuEnabled' => true,
            'totalBytes' => $totalBytes,
            'usedBytes' => $usedBytes,
            'availBytes' => $availBytes,
            'percentUsed' => $totalBytes > 0 ? round($usedBytes * 100 / $totalBytes, 1) : null,
            'entries' => isset($cache['num_entries']) ? (int) $cache['num_entries'] : null,
            'hits' => isset($cache['num_hits']) ? (int) $cache['num_hits'] : null,
            'misses' => isset($cache['num_misses']) ? (int) $cache['num_misses'] : null,
            //non-zero means the segment has already been thrown away at least
            //once: with apc.ttl=0 an allocation that does not fit clears the lot
            'expunges' => isset($cache['expunges']) ? (int) $cache['expunges'] : null,
            'uptimeSeconds' => isset($cache['start_time']) ? self::now() - (int) $cache['start_time'] : null,
            'largestEntries' => self::largestEntries($entryList, $limit),
        ];
    }

    /**
     * The biggest cache entries, largest first, as [key => bytes].
     *
     * Aggregate occupancy says the segment is full; it does not say which key
     * filled it. That distinction is what decides whether the fix is a bigger
     * segment or a narrower query, and it is also how the
     * sion_model.max_cached_item_size budget gets tuned against real data rather
     * than a guess. `mem_size` is what APCu actually allocated for the entry, so
     * unlike a serialize() estimate it needs no interpretation.
     *
     * @param array<string, mixed>|false|null $entryList Result of apcu_cache_info()
     * @return array<string, int>
     */
    private static function largestEntries($entryList, int $limit): array
    {
        if (! is_array($entryList) || ! isset($entryList['cache_list']) || ! is_array($entryList['cache_list'])) {
            return [];
        }
        $sizes = [];
        foreach ($entryList['cache_list'] as $entry) {
            if (! is_array($entry) || ! isset($entry['info'])) {
                continue;
            }
            $sizes[$entry['info']] = isset($entry['mem_size']) ? (int) $entry['mem_size'] : 0;
        }
        arsort($sizes);
        return array_slice($sizes, 0, $limit, true);
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
}
