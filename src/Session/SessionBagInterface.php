<?php

declare(strict_types=1);

namespace SionModel\Session;

/**
 * One namespace of the session: a scratch space with an expiry policy.
 *
 * What `Laminas\Session\Container` was to this library until 2026-09-21, reduced to the
 * six operations `SionModel\Messaging\FlashMessages` and `SionModel\Validator\Csrf`
 * actually perform. The container was an `ArrayObject` with a session manager inside it
 * and thirty methods; two of them mattered here.
 *
 * Expiry is declared on the bag rather than applied by the caller because it is stored
 * *beside* the data and read by a later request — so whoever writes the value is the only
 * one in a position to say how long it lives.
 */
interface SessionBagInterface
{
    /** Null when nothing is stored, which is indistinguishable from a stored null. */
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** @return array<string, mixed> every key in this namespace, in insertion order */
    public function all(): array;

    /**
     * Discard the whole namespace after `$hops` further requests.
     *
     * One hop is "the next request may read this, and nothing after it may" — a flash
     * message, whether or not anything read it.
     */
    public function expireAfterHops(int $hops): void;

    /** Discard the whole namespace `$seconds` from now. */
    public function expireAfterSeconds(int $seconds): void;
}
