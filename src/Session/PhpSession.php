<?php

declare(strict_types=1);

namespace SionModel\Session;

use ArrayObject;

use function array_key_exists;
use function end;
use function explode;
use function is_array;
use function is_object;
use function microtime;
use function str_contains;

/**
 * The session as PHP stores it: namespaces in `$_SESSION`, expiry metadata beside them.
 *
 * Replaced `laminas-session` on 2026-09-21, and the whole difficulty was that the package
 * did not store arrays. Every namespace it wrote is a serialised
 * `Laminas\Stdlib\ArrayObject`, and a flash queue inside one is an `SplQueue`. A cookie
 * lives 30 days here, so a reader that only understood plain arrays would sign every
 * visitor out for a month — which this application has already lived through once, on
 * 2026-08-07, when sessions written before the Zend -> Laminas renames unserialised to
 * `__PHP_Incomplete_Class` and every ported route answered with an empty 200
 * ({@see \App\Http\SessionListener}).
 *
 * So this **reads three shapes and writes one**:
 *
 * - a plain array, which is what it writes;
 * - any `ArrayObject`, which is what laminas-session wrote (`Laminas\Stdlib\ArrayObject`
 *   extends it) — read while `laminas-stdlib` is still installed, which is why the
 *   session had to leave **before** stdlib and not after;
 * - `__PHP_Incomplete_Class`, for a session written by a release whose classes are gone.
 *   Casting one to array yields keys with NUL-delimited class prefixes; `unwrap()` strips
 *   them rather than letting the values vanish silently.
 *
 * `test/Session/session-surface.php` in the application is the recording of all of it,
 * taken while laminas-session was installed, and `SessionSurfaceTest` is what holds this
 * class to it.
 *
 * ## The metadata key is still `__Laminas`
 *
 * Not an oversight, and the same decision as `Laminas_Auth` in
 * `JUser\Authentication\SessionIdentity`: it is a wire format, not a name anybody chose.
 * Writing expiry under a new key would make every in-flight flash message immortal, and
 * would leave a rolled-back release unable to expire anything this one wrote. Unknown
 * metadata is preserved untouched for the same reason.
 *
 * ## It operates on `$_SESSION` directly
 *
 * As `Laminas\Session\Storage\SessionArrayStorage` did. That is what makes a value written
 * here visible to anything else in the request that reads the superglobal, and it is what
 * lets a test drive this class by assigning a recorded session to `$_SESSION`.
 */
final class PhpSession implements SessionInterface
{
    public const METADATA = '__Laminas';

    private const ACCESS_TIME = '_REQUEST_ACCESS_TIME';

    /** @var array<string, Bag> */
    private array $bags = [];

    /**
     * The access time this request reads hops against.
     *
     * Read once and held, so that every bag in a request agrees about which request it is
     * — laminas took it from the storage, where it was stamped at start.
     */
    private readonly float $accessTime;

    public function __construct(?float $accessTime = null)
    {
        $this->accessTime = $accessTime ?? microtime(true);
    }

    /** This request's access time, which is what a hop is measured against. */
    public function accessTime(): float
    {
        return $this->accessTime;
    }

    public function bag(string $namespace): SessionBagInterface
    {
        //One bag per namespace per request, as laminas cached its containers: two bags
        //over one namespace would each hold their own copy and the later write would win.
        return $this->bags[$namespace] ??= new Bag($this, $namespace);
    }

    /**
     * Stamp this request's access time, as the storage did on start.
     *
     * Called by the host once the session is started. Without it a hop never advances,
     * because every request would compare against its own timestamp.
     */
    public function stampAccessTime(): void
    {
        $metadata                     = $this->metadata();
        $metadata[self::ACCESS_TIME]  = $this->accessTime;
        $this->writeMetadata($metadata);
    }

    /** @return array<string, mixed> */
    public function namespaceData(string $namespace): array
    {
        self::ensureStorage();
        $this->expire($namespace);

        return array_key_exists($namespace, $_SESSION) ? self::unwrap($_SESSION[$namespace]) : [];
    }

    /** @param array<string, mixed> $data */
    public function writeNamespace(string $namespace, array $data): void
    {
        self::ensureStorage();
        if ([] === $data) {
            unset($_SESSION[$namespace]);

            return;
        }

        //Plain arrays from here on. laminas reads one perfectly well — measured in both
        //directions before the package went — so a rollback still finds its sessions.
        $_SESSION[$namespace] = $data;
    }

    /** @return array<string, mixed> */
    public function namespaceMetadata(string $namespace): array
    {
        $metadata = $this->metadata();
        /** @var array<string, mixed> $own */
        $own = is_array($metadata[$namespace] ?? null) ? $metadata[$namespace] : [];

        return $own;
    }

    /** @param array<string, mixed> $own */
    public function writeNamespaceMetadata(string $namespace, array $own): void
    {
        $metadata = $this->metadata();
        if ([] === $own) {
            unset($metadata[$namespace]);
        } else {
            $metadata[$namespace] = $own;
        }
        $this->writeMetadata($metadata);
    }

    /**
     * Apply whatever expiry the namespace carries, exactly as `AbstractContainer` did.
     *
     * Only the container-wide forms are implemented: `EXPIRE` and `EXPIRE_HOPS`. laminas
     * also had per-key variants (`EXPIRE_KEYS`, `EXPIRE_HOPS_KEYS`) and nothing in these
     * four repositories has ever set one — but an old session could still carry them, so
     * they are left in place untouched rather than dropped, which is what a rolled-back
     * release would need to honour them.
     */
    private function expire(string $namespace): void
    {
        $own = $this->namespaceMetadata($namespace);
        if ([] === $own) {
            return;
        }

        //Absolute expiry, against this request's clock. laminas read two clocks here —
        //$_SERVER['REQUEST_TIME'] for EXPIRE and the storage's access time for hops — and
        //they are the same moment, so this reads one. It is also the only one a caller can
        //set, which is what lets the recording be replayed at a fixed time rather than at
        //whatever time the suite happens to run.
        if (isset($own['EXPIRE']) && (int) $this->accessTime > (int) $own['EXPIRE']) {
            unset($own['EXPIRE']);
            $this->writeNamespaceMetadata($namespace, $own);
            unset($_SESSION[$namespace]);

            return;
        }

        if (! isset($own['EXPIRE_HOPS']) || ! is_array($own['EXPIRE_HOPS'])) {
            return;
        }

        //A hop passes when this request is later than the one that last touched the
        //namespace. Reaching -1 is what ends it, so `hops => 1` survives exactly one
        //further request — which is the flash-message contract.
        if ($this->accessTime <= (float) ($own['EXPIRE_HOPS']['ts'] ?? 0)) {
            return;
        }

        $hops = (int) ($own['EXPIRE_HOPS']['hops'] ?? 0) - 1;
        if (-1 === $hops) {
            unset($own['EXPIRE_HOPS']);
            $this->writeNamespaceMetadata($namespace, $own);
            unset($_SESSION[$namespace]);

            return;
        }

        $own['EXPIRE_HOPS'] = ['hops' => $hops, 'ts' => $this->accessTime];
        $this->writeNamespaceMetadata($namespace, $own);
    }

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        self::ensureStorage();
        if (! isset($_SESSION[self::METADATA])) {
            return [];
        }

        return self::unwrap($_SESSION[self::METADATA]);
    }

    /** @param array<string, mixed> $metadata */
    private function writeMetadata(array $metadata): void
    {
        $_SESSION[self::METADATA] = $metadata;
    }

    /**
     * `$_SESSION` exists before anything indexes it.
     *
     * No session started means no superglobal, and a console process or a test reaching a
     * bag legitimately finds nothing there. Writes to it are then simply discarded at the
     * end of the process, which is what laminas did with a container and no manager.
     */
    private static function ensureStorage(): void
    {
        if (! isset($_SESSION) || ! is_array($_SESSION)) {
            $_SESSION = [];
        }
    }

    /**
     * Whatever a namespace deserialised to, as an array.
     *
     * @return array<string, mixed>
     */
    public static function unwrap(mixed $value): array
    {
        if ($value instanceof ArrayObject) {
            /** @var array<string, mixed> $copy */
            $copy = $value->getArrayCopy();

            return $copy;
        }

        if (is_object($value)) {
            //__PHP_Incomplete_Class, or any other object a past release stored. Casting
            //gives keys prefixed "\0ClassName\0" for private and "\0*\0" for protected,
            //plus __PHP_Incomplete_Class_Name. Strip the prefixes rather than lose the
            //values; a key that arrives NUL-delimited is not one any caller asked for.
            $unwrapped = [];
            foreach ((array) $value as $key => $item) {
                $key = (string) $key;
                if ('__PHP_Incomplete_Class_Name' === $key) {
                    continue;
                }
                if (str_contains($key, "\0")) {
                    $parts = explode("\0", $key);
                    $key   = (string) end($parts);
                }
                //An ArrayObject's own payload is its `storage` property, so a stored
                //ArrayObject that lost its class arrives as ['storage' => [...]].
                if ('storage' === $key && is_array($item)) {
                    /** @var array<string, mixed> $item */
                    return $item;
                }
                $unwrapped[$key] = $item;
            }

            /** @var array<string, mixed> $unwrapped */
            return $unwrapped;
        }

        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        return [];
    }
}
