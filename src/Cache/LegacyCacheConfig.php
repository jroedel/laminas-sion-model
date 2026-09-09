<?php

namespace SionModel\Cache;

/**
 * Normalises the two generations of laminas-cache configuration this application's files
 * are written in into one shape: `{adapter: string, options: array, plugins: list}`.
 *
 * Exists so the untracked, machine-specific cache configs — `cache.local.php` on every
 * deployment target — keep working without a coordinated config rewrite during a deploy.
 * It was written for the laminas-cache 2 → 3 bump; laminas-cache is gone now and
 * {@see StorageFactory} reads what comes out of here, so the same files keep working
 * across that removal too. Nothing in it depends on laminas.
 */
final class LegacyCacheConfig
{
    /**
     * @param array<string, mixed> $legacy StorageFactory-shaped: adapter as
     *        array{name, options?, ttl?} (or already a string), plugins as a
     *        list of names, name=>options maps, or {name, options} entries
     * @return array{
     *     adapter: string,
     *     options: array<string, mixed>,
     *     plugins: list<array{name: string, options?: array<string, mixed>}>
     * }
     */
    public static function translate(array $legacy): array
    {
        $adapter = $legacy['adapter'] ?? [];
        if (is_array($adapter)) {
            $name = (string) ($adapter['name'] ?? '');
            $options = $adapter['options'] ?? [];
            // the .dist shape put ttl beside the adapter name instead of in options
            if (isset($adapter['ttl'])) {
                $options['ttl'] = $adapter['ttl'];
            }
        } else {
            $name = (string) $adapter;
            $options = $legacy['options'] ?? [];
        }

        $plugins = [];
        foreach ($legacy['plugins'] ?? [] as $key => $plugin) {
            if (is_string($plugin)) {
                // ['serializer']
                $plugins[] = ['name' => $plugin];
            } elseif (isset($plugin['name'])) {
                // [['name' => 'serializer', 'options' => [...]]]
                $entry = ['name' => (string) $plugin['name']];
                if (isset($plugin['options'])) {
                    $entry['options'] = $plugin['options'];
                }
                $plugins[] = $entry;
            } else {
                // ['exception_handler' => ['throw_exceptions' => true]]
                $plugins[] = ['name' => (string) $key, 'options' => $plugin];
            }
        }

        return ['adapter' => $name, 'options' => $options, 'plugins' => $plugins];
    }
}
