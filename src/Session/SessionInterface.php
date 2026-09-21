<?php

declare(strict_types=1);

namespace SionModel\Session;

/**
 * The session, as a source of namespaced bags.
 *
 * **Starting the session is not on this interface**, for the reason
 * `JUser\Host\SessionInterface` gives at greater length: a library that started the
 * session would be choosing cookie parameters, storage and lifetime on the host's behalf,
 * which is how two components end up writing two session cookies. The host starts it;
 * this reads and writes what is there.
 */
interface SessionInterface
{
    public function bag(string $namespace): SessionBagInterface;
}
