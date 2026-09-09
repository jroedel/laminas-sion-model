<?php

declare(strict_types=1);

namespace SionModel\Cache;

use SionModel\Db\Model\SionTable;

use function in_array;

/**
 * The tables whose queued cache writes still need draining at the end of the request.
 *
 * ## The bug this exists for
 *
 * A SionTable does not write to the persistent cache when it caches something. It
 * queues the item and writes the queue out at the end of the request, because
 * serializing a large object is expensive and doing it mid-render costs the visitor
 * the time. `SionCacheTrait::onFinishWriteCache()` is that write, and until 2026-08 the
 * only thing that ever called it was a listener on laminas-mvc's `MvcEvent::EVENT_FINISH`.
 *
 * A Symfony-served route never reaches that event. So on the whole of a
 * strangled application's ported surface, every table read, cached into memory,
 * queued — and threw the queue away. Nothing failed and nothing logged: a queued
 * item and a written one are indistinguishable to the request that queued it,
 * because both are served out of `$memoryCache` for the rest of that request. The
 * cost only appears on the *next* request, as a miss, forever.
 *
 * Measured on schoenstatt.link 2026-08-22, over 49 requests to nine pages under
 * the live Symfony front controller: 348 reads of data keys, **0 hits, 0 writes**.
 * The identical pages under the laminas front controller: 419 reads, 349 hits, 35
 * writes. Nobody had noticed in the eleven days since the cutover because wall
 * time went *down* — not booting laminas-mvc saves more than the cache was
 * returning, so the two changes cancelled out in the only number anyone watches.
 *
 * ## Why a registry and not a service the listener resolves
 *
 * Because the host's end-of-request hook must cost nothing on a request that
 * touched no table. There is no way to ask a ServiceManager whether a service was
 * instantiated, so a listener that resolved tables by name would *build* them —
 * ten table objects, each with a database adapter, on a request that wanted a
 * health check. Registration runs from the factory instead, which by definition
 * only runs when something asked for the table.
 *
 * ## Host wiring
 *
 * The host creates one of these per request, registers it in the container under
 * this class's name, and calls {@see flush()} from whatever its end-of-request hook
 * is; {@see \SionModel\Service\SionTableWiring::wireFlushPoint()} does the rest. A
 * host that registers nothing has no flush point and its persistent cache stores nothing.
 * See `App\Http\SionCacheFlushListener` in schoenstatt.link for the Symfony side.
 *
 * **Not from a console process.** An APCu segment belongs to the SAPI that created
 * it, so anything a CLI run writes lands in a segment no web request can read (see
 * docs/caching.md). Flushing there would spend the serialization cost for nothing.
 */
final class CacheFlushQueue
{
    /** @var list<SionTable> */
    private array $tables = [];

    /**
     * Idempotent, because a factory may wire the same table twice — JUser's
     * UserTableFactory swaps in its own namespaced cache after
     * `SionTableWiring::apply()` has already run. Registering twice would mean two
     * passes over one write queue — the duplicate-write bug the MvcEvent listener's
     * own guard used to stop.
     */
    public function register(SionTable $table): void
    {
        if (in_array($table, $this->tables, true)) {
            return;
        }
        $this->tables[] = $table;
    }

    /** Whether any table was built at all. Nothing needs it yet; diagnostics do. */
    public function isEmpty(): bool
    {
        return [] === $this->tables;
    }

    /** Whether this table's queue will be written at the end of the request. */
    public function contains(SionTable $table): bool
    {
        return in_array($table, $this->tables, true);
    }

    /**
     * Write out every registered table's queue.
     *
     * Cheap when nothing was cached: `onFinishWriteCache()` returns on an empty
     * queue before touching the storage. Safe to call twice — the queue is drained
     * as it is written, so a second call finds nothing.
     */
    public function flush(): void
    {
        foreach ($this->tables as $table) {
            $table->onFinishWriteCache();
        }
    }
}
