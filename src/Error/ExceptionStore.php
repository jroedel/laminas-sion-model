<?php

namespace SionModel\Error;

/**
 * The on-disk record of every distinct failure the application has hit.
 *
 * Layout, one directory per fingerprint:
 *
 *   <path>/<fp>/meta.json          counters and notification bookkeeping
 *   <path>/<fp>/first.txt          write-up of the first occurrence
 *   <path>/<fp>/last.txt           write-up of the most recent occurrence
 *   <path>/<fp>/recent/1..N.txt    ring buffer, 1.txt newest
 *   <path>/.emails/<YmdH>          hourly send counter
 *   <path>/.emails/breaker         epoch until which sending is suspended
 *   <path>/.overflow               fingerprints dropped at capacity
 *
 * Deliberately *not* one file per occurrence: a failure inside a loop would
 * write thousands. The counter in meta.json carries the volume, while first,
 * last and a short ring answer the two questions that actually matter — is it
 * still happening, and is it the same input every time.
 *
 * Every method is failure-tolerant and returns rather than throws. This code
 * runs while the application is already broken, and a full disk here must not
 * escalate a handled 500 into an unhandled fatal.
 *
 * Dependency-free so it is unit-testable against a temp directory without a
 * booted application.
 */
class ExceptionStore
{
    /** @var string */
    private $path;

    /** @var int */
    private $maxFingerprints;

    /** @var int */
    private $ringSize;

    /** @var int */
    private $maxWriteUpBytes;

    /**
     * @param string $path            directory holding the store
     * @param int    $maxFingerprints hard ceiling on distinct fingerprints, so
     *                                an exception storm cannot fill the disk
     * @param int    $ringSize        how many recent write-ups to retain
     * @param int    $maxWriteUpBytes truncation ceiling for a single write-up
     */
    public function __construct($path, $maxFingerprints = 500, $ringSize = 3, $maxWriteUpBytes = 262144)
    {
        $this->path            = rtrim((string) $path, '/');
        $this->maxFingerprints = (int) $maxFingerprints;
        $this->ringSize        = max(0, (int) $ringSize);
        $this->maxWriteUpBytes = max(4096, (int) $maxWriteUpBytes);
    }

    /** @return string */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Record one occurrence.
     *
     * The read-modify-write of meta.json happens under an exclusive lock, and
     * that lock — not the mkdir — is what makes "is this the first occurrence"
     * a single-winner question. Two workers hitting a brand new fingerprint
     * simultaneously produce exactly one outcome with isNew(), so exactly one
     * notification is sent.
     *
     * @param ExceptionRecord $record
     * @return RecordOutcome|null null when the store is unusable or full
     */
    public function record(ExceptionRecord $record)
    {
        $fingerprint = $record->getFingerprint();
        if (! $this->isValidFingerprint($fingerprint)) {
            return null;
        }
        if (! $this->ensureDirectory($this->path)) {
            return null;
        }

        $dir = $this->path . '/' . $fingerprint;
        //see isAtCapacity() on why this check is not locked and what that costs
        if (! is_dir($dir) && $this->isAtCapacity()) {
            $this->noteOverflow($record);
            return null;
        }
        if (! $this->ensureDirectory($dir)) {
            return null;
        }

        $handle = @fopen($dir . '/meta.json', 'c+');
        if (false === $handle) {
            return null;
        }

        $outcome = null;
        try {
            @flock($handle, LOCK_EX);
            $existing = $this->readJsonHandle($handle);
            $isNew    = ! is_array($existing) || ! isset($existing['count']);

            $meta               = $record->toMeta();
            $meta['first_seen'] = $isNew
                ? $record->getOccurredAt()
                : $this->pick($existing, 'first_seen', $record->getOccurredAt());
            $meta['last_seen']  = $record->getOccurredAt();
            $meta['count']      = $isNew ? 1 : ((int) $this->pick($existing, 'count', 0)) + 1;
            //notification bookkeeping belongs to the notifier; carry it across
            $meta['notified_at']     = $isNew ? null : $this->pick($existing, 'notified_at', null);
            $meta['notified_counts'] = $isNew ? [] : (array) $this->pick($existing, 'notified_counts', []);
            $notifyError             = $isNew ? null : $this->pick($existing, 'notify_error', null);
            if (null !== $notifyError) {
                $meta['notify_error']    = $notifyError;
                $meta['notify_error_at'] = $this->pick($existing, 'notify_error_at', null);
            }

            $this->writeJsonHandle($handle, $meta);
            $outcome = new RecordOutcome($fingerprint, $isNew, $meta['count'], $meta['notified_counts']);
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }

        $this->writeUps($dir, $record, null !== $outcome && $outcome->isNew());

        return $outcome;
    }

    /**
     * Note that a notification has been sent for this fingerprint at this
     * occurrence count.
     *
     * Called *before* the send is attempted, on purpose. If the mail server
     * hangs, the fingerprint is already marked, so the next request does not
     * queue up behind the same doomed connection — the failure is recorded in
     * meta.json instead, where fetch-exceptions.sh will show it.
     *
     * @param string    $fingerprint
     * @param int|int[] $counts occurrence counts now considered reported
     * @return bool
     */
    public function markNotified($fingerprint, $counts)
    {
        $counts = is_array($counts) ? $counts : [$counts];
        return $this->amendMeta($fingerprint, function (array $meta) use ($counts) {
            $existing = isset($meta['notified_counts']) ? (array) $meta['notified_counts'] : [];
            $merged   = array_merge($existing, $counts);
            $meta['notified_counts'] = array_values(array_unique(array_map('intval', $merged)));
            sort($meta['notified_counts']);
            $meta['notified_at']     = date('c');
            unset($meta['notify_error'], $meta['notify_error_at']);
            return $meta;
        });
    }

    /**
     * @param string $fingerprint
     * @param string $message
     * @return bool
     */
    public function noteNotifyError($fingerprint, $message)
    {
        return $this->amendMeta($fingerprint, function (array $meta) use ($message) {
            $meta['notify_error']    = substr((string) $message, 0, 500);
            $meta['notify_error_at'] = date('c');
            return $meta;
        });
    }

    /**
     * Reserve one of this hour's notification slots.
     *
     * The rate ceiling is a blast shield: a request-triggerable exception that
     * somehow produces many distinct fingerprints must not turn into a mail
     * flood.
     *
     * @param int $maxPerHour
     * @return bool false when this hour's allowance is spent
     */
    public function claimEmailSlot($maxPerHour)
    {
        $maxPerHour = (int) $maxPerHour;
        if ($maxPerHour <= 0) {
            return false;
        }
        $dir = $this->path . '/.emails';
        if (! $this->ensureDirectory($dir)) {
            //can't account for sends; better to send than to go silent
            return true;
        }
        $this->pruneEmailLedger($dir);

        $handle = @fopen($dir . '/' . date('YmdH'), 'c+');
        if (false === $handle) {
            return true;
        }
        try {
            @flock($handle, LOCK_EX);
            $sent = (int) stream_get_contents($handle);
            if ($sent >= $maxPerHour) {
                return false;
            }
            @rewind($handle);
            @ftruncate($handle, 0);
            @fwrite($handle, (string) ($sent + 1));
            @fflush($handle);
            return true;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * Whether sending is currently suspended after a transport failure.
     *
     * @return bool
     */
    public function isBreakerOpen()
    {
        $until = @file_get_contents($this->path . '/.emails/breaker');
        if (false === $until || '' === trim((string) $until)) {
            return false;
        }
        return time() < (int) trim((string) $until);
    }

    /**
     * Suspend sending for a while after the transport failed.
     *
     * A mail host that is refusing connections will refuse the next one too,
     * and each attempt costs the visitor a socket timeout on a request that has
     * already failed.
     *
     * @param int $seconds
     * @return void
     */
    public function openBreaker($seconds = 900)
    {
        $dir = $this->path . '/.emails';
        if (! $this->ensureDirectory($dir)) {
            return;
        }
        @file_put_contents($dir . '/breaker', (string) (time() + (int) $seconds), LOCK_EX);
    }

    /**
     * Read one fingerprint's metadata.
     *
     * @param string $fingerprint
     * @return array|null
     */
    public function readMeta($fingerprint)
    {
        if (! $this->isValidFingerprint($fingerprint)) {
            return null;
        }
        $raw = @file_get_contents($this->path . '/' . $fingerprint . '/meta.json');
        if (false === $raw) {
            return null;
        }
        $meta = json_decode($raw, true);
        return is_array($meta) ? $meta : null;
    }

    /**
     * Every fingerprint currently in the store.
     *
     * @return string[]
     */
    public function fingerprints()
    {
        $dirs = @glob($this->path . '/*', GLOB_ONLYDIR);
        if (! is_array($dirs)) {
            return [];
        }
        $fingerprints = [];
        foreach ($dirs as $dir) {
            $name = basename($dir);
            if ($this->isValidFingerprint($name)) {
                $fingerprints[] = $name;
            }
        }
        return $fingerprints;
    }

    /**
     * @param string $fingerprint
     * @return bool
     */
    public function isValidFingerprint($fingerprint)
    {
        return is_string($fingerprint) && 1 === preg_match('/^[0-9a-f]{8}$/', $fingerprint);
    }

    /**
     * Whether the fingerprint ceiling has been reached.
     *
     * This check is deliberately unlocked, unlike the meta.json read-modify-write
     * in record(). Two workers each recording a *different* brand new fingerprint
     * at the capacity boundary can both pass it, so the store can overshoot the
     * ceiling — but only by at most the number of concurrent workers, since each
     * one creates a single directory. That is a handful of directories, each
     * bounded by maxWriteUpBytes, against a ceiling whose job is to stop
     * unbounded growth rather than to be exact.
     *
     * Locking it would serialise every first-ever occurrence behind a global
     * lock, on the request path, to buy an exactness nothing here needs.
     *
     * @return bool
     */
    private function isAtCapacity()
    {
        if ($this->maxFingerprints <= 0) {
            return false;
        }
        return count($this->fingerprints()) >= $this->maxFingerprints;
    }

    /**
     * Leave a breadcrumb when capacity forced us to drop a new fingerprint, so
     * a silent store is distinguishable from a full one.
     *
     * @param ExceptionRecord $record
     * @return void
     */
    private function noteOverflow(ExceptionRecord $record)
    {
        $line = sprintf(
            "%s %s %s %s\n",
            $record->getOccurredAt(),
            $record->getFingerprint(),
            $record->getClass(),
            $record->getRoute()
        );
        @file_put_contents($this->path . '/.overflow', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param string          $dir
     * @param ExceptionRecord $record
     * @param bool            $isNew
     * @return void
     */
    private function writeUps($dir, ExceptionRecord $record, $isNew)
    {
        $writeUp = $record->toWriteUp();
        if (strlen($writeUp) > $this->maxWriteUpBytes) {
            $writeUp = substr($writeUp, 0, $this->maxWriteUpBytes) . "\n[truncated]\n";
        }
        if ($isNew) {
            @file_put_contents($dir . '/first.txt', $writeUp, LOCK_EX);
        }
        @file_put_contents($dir . '/last.txt', $writeUp, LOCK_EX);
        $this->rotateRing($dir, $writeUp);
    }

    /**
     * @param string $dir
     * @param string $writeUp
     * @return void
     */
    private function rotateRing($dir, $writeUp)
    {
        if (0 === $this->ringSize) {
            return;
        }
        $ring = $dir . '/recent';
        if (! $this->ensureDirectory($ring)) {
            return;
        }
        for ($slot = $this->ringSize - 1; $slot >= 1; $slot--) {
            $from = $ring . '/' . $slot . '.txt';
            if (is_file($from)) {
                @rename($from, $ring . '/' . ($slot + 1) . '.txt');
            }
        }
        @file_put_contents($ring . '/1.txt', $writeUp, LOCK_EX);
    }

    /**
     * Read-modify-write one fingerprint's metadata under an exclusive lock.
     *
     * @param string   $fingerprint
     * @param callable $mutator
     * @return bool
     */
    private function amendMeta($fingerprint, $mutator)
    {
        if (! $this->isValidFingerprint($fingerprint)) {
            return false;
        }
        $handle = @fopen($this->path . '/' . $fingerprint . '/meta.json', 'c+');
        if (false === $handle) {
            return false;
        }
        try {
            @flock($handle, LOCK_EX);
            $meta = $this->readJsonHandle($handle);
            if (! is_array($meta)) {
                return false;
            }
            $this->writeJsonHandle($handle, $mutator($meta));
            return true;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return array|null
     */
    private function readJsonHandle($handle)
    {
        @rewind($handle);
        $raw = stream_get_contents($handle);
        if (false === $raw || '' === trim((string) $raw)) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param resource $handle
     * @param array    $data
     * @return void
     */
    private function writeJsonHandle($handle, array $data)
    {
        //exception messages can carry raw bytes from a database or a request;
        //substitute rather than let json_encode return false and lose the record
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (false === $json) {
            return;
        }
        @rewind($handle);
        @ftruncate($handle, 0);
        @fwrite($handle, $json . "\n");
        @fflush($handle);
    }

    /**
     * @param array|null $data
     * @param string     $key
     * @param mixed      $default
     * @return mixed
     */
    private function pick($data, $key, $default)
    {
        return is_array($data) && isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * The hourly ledger would otherwise accumulate a file per hour forever.
     *
     * @param string $dir
     * @return void
     */
    private function pruneEmailLedger($dir)
    {
        $files = @glob($dir . '/[0-9]*');
        if (! is_array($files)) {
            return;
        }
        $cutoff = date('YmdH', time() - 172800);
        foreach ($files as $file) {
            if (basename($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /**
     * @param string $dir
     * @return bool
     */
    private function ensureDirectory($dir)
    {
        if (is_dir($dir)) {
            return true;
        }
        //the race with a concurrent worker is expected, hence the is_dir recheck
        return @mkdir($dir, 0775, true) || is_dir($dir);
    }
}
