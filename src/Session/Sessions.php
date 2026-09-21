<?php

declare(strict_types=1);

namespace SionModel\Session;

/**
 * The session a class reaches when nothing handed it one.
 *
 * This is global mutable state, which this library otherwise avoids, and it exists for one
 * caller: {@see \SionModel\Validator\Csrf}. A validator is built by the form engine from a
 * `FormSpecification` — a rule name through a flat `Registry` — with no container, no
 * module loading and no host wiring anywhere in the path. That is deliberate and is what
 * lets the associations API validate with none of those present, so there is nowhere for a
 * session to be injected from.
 *
 * `Laminas\Session\AbstractContainer::getDefaultManager()` was the same static for the same
 * reason, and this replaced it on 2026-09-21.
 *
 * The host sets it once per request. Unset, the fallback is an ordinary {@see PhpSession}
 * over whatever `$_SESSION` holds — which is right for a CLI process or a test, where
 * `$_SESSION` is empty and a CSRF token simply will not validate across requests, and
 * wrong for nothing.
 */
final class Sessions
{
    private static ?SessionInterface $default = null;

    public static function setDefault(?SessionInterface $session): void
    {
        self::$default = $session;
    }

    public static function default(): SessionInterface
    {
        return self::$default ??= new PhpSession();
    }
}
