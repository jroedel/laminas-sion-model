<?php

declare(strict_types=1);

namespace SionModel\Cache;

use function is_array;
use function is_numeric;
use function is_string;
use function sys_get_temp_dir;

/**
 * Builds a {@see Storage} from the cache configuration this application already ships.
 *
 * **No deployment configuration changes.** The shapes in `cache.local.php`,
 * `sionmodel.global.php` and `juser.global.php` are laminas-cache's, in two generations of
 * it, and {@see LegacyCacheConfig} already normalises between them. This reads what comes
 * out of that, so a host upgrades by changing code and not by editing files that live
 * outside the repository.
 *
 * Two keys of the laminas shape are deliberately ignored:
 *
 * - **`plugins.serializer`.** Each backend encodes the way that backend should: APCu stores
 *   PHP values natively, the filesystem one serializes. The configured `Json` serializer
 *   could not round-trip the objects the entity caches hold, and only ever applied to the
 *   filesystem adapter, which production does not use.
 * - **`plugins.exception_handler`.** These implementations do not throw; a failed read or
 *   write is `false` or a miss. `SionCacheTrait` already wraps its calls, and a cache that
 *   throws is a cache that can take a page down.
 *
 * An unknown adapter name falls back to the filesystem rather than throwing, for the same
 * reason: a cache is an optimisation, and a mistyped adapter should make the site slow, not
 * broken.
 */
final class StorageFactory
{
    public const DEFAULT_APCU_TTL = 0;

    /**
     * @param array<string, mixed> $config either generation of the laminas-cache shape
     */
    public static function fromConfig(array $config): Storage
    {
        $normalised = LegacyCacheConfig::translate($config);
        $adapter    = is_string($normalised['adapter'] ?? null) ? $normalised['adapter'] : '';
        $options    = is_array($normalised['options'] ?? null) ? $normalised['options'] : [];

        $namespace = is_string($options['namespace'] ?? null) ? $options['namespace'] : '';
        $ttl       = is_numeric($options['ttl'] ?? null) ? (int) $options['ttl'] : self::DEFAULT_APCU_TTL;

        if ('apcu' === $adapter) {
            return new ApcuStorage($namespace, $ttl);
        }

        //`cache_dir` is commented out in every config this application ships, and the
        //laminas adapter's own default was the system temp directory. Kept, so the files
        //land where they always did.
        $directory = is_string($options['cache_dir'] ?? null) && '' !== $options['cache_dir']
            ? $options['cache_dir']
            : sys_get_temp_dir();

        return new FilesystemStorage($directory, $namespace, $ttl);
    }
}
