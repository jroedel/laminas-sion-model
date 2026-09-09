<?php

declare(strict_types=1);

namespace SionModel\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Traversable;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_numeric;
use function iterator_to_array;
use function md5;
use function mkdir;
use function serialize;
use function time;
use function unlink;
use function unserialize;

use const LOCK_EX;

/**
 * The same cache on disk, for a process with no APCu — a console command, CI, a
 * development machine without the extension.
 *
 * Not a general-purpose file cache, and deliberately so. It stores one serialized PHP value
 * per key in a file named by the hash of the qualified key, with the expiry inside the file
 * rather than in a companion index, so a half-written entry is a miss and never a corrupt
 * index. Nothing prunes: entries expire when read, and the directory is the one the
 * configuration names — the system temp directory by default, as the laminas adapter used.
 *
 * **The stored format is `serialize()`, not JSON**, which the laminas configuration asked
 * for through a serializer plugin. JSON cannot round-trip the objects the entity caches
 * hold, and the plugin was configured that way for the filesystem adapter alone while APCu
 * — the backend production actually uses — stored native values. The file extension is
 * `.cache` where the laminas adapter used `.dat`, so entries written by the old adapter are
 * simply not found rather than mis-read.
 *
 * @see Storage for why the interface is ours and not PSR-16's.
 */
final class FilesystemStorage implements Storage, CacheInterface
{
    public function __construct(
        private readonly string $directory,
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
        $file    = $this->path($key);
        if (! is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (false === $raw) {
            return null;
        }
        /** @var array{expires: int, value: mixed}|false $entry */
        $entry = @unserialize($raw);
        if (! is_array($entry) || ! isset($entry['expires'])) {
            return null;
        }
        if (0 !== $entry['expires'] && $entry['expires'] < time()) {
            @unlink($file);

            return null;
        }
        $success = true;

        return $entry['value'];
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function getItems(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $this->getItem($key, $success);
            if ($success) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public function setItem(string $key, mixed $value): bool
    {
        if (! $this->ensureDirectory()) {
            return false;
        }
        $entry = [
            'expires' => 0 === $this->ttl ? 0 : time() + $this->ttl,
            'value'   => $value,
        ];

        return false !== @file_put_contents($this->path($key), serialize($entry), LOCK_EX);
    }

    public function removeItem(string $key): bool
    {
        $file = $this->path($key);

        return ! is_file($file) || @unlink($file);
    }

    /**
     * Read, add, write — **not atomic**, unlike the APCu implementation, and `false` for an
     * absent key where APCu would create it.
     *
     * Both differences are acceptable only because of where this class runs: a process with
     * no APCu, which in this application means one console command at a time. Under
     * concurrency the generation counter this backs could miss a bump and let a stale write
     * survive. If this ever becomes the web backend, that has to be revisited.
     */
    public function incrementItem(string $key, int $by = 1): int|false
    {
        $current = $this->getItem($key, $success);
        if (! $success || ! is_numeric($current)) {
            return false;
        }
        $new = (int) $current + $by;

        return $this->setItem($key, $new) ? $new : false;
    }

    public function flush(): bool
    {
        $files = glob($this->directory . '/' . $this->filePrefix() . '*.cache');
        foreach ($files ?: [] as $file) {
            @unlink($file);
        }

        return true;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . $this->filePrefix() . md5($key) . '.cache';
    }

    private function filePrefix(): string
    {
        return '' === $this->namespace ? '' : md5($this->namespace) . '-';
    }

    private function ensureDirectory(): bool
    {
        return is_dir($this->directory) || @mkdir($this->directory, 0775, true) || is_dir($this->directory);
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
        $this->getItem($key, $success);

        return $success;
    }
}
