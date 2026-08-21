<?php

declare(strict_types=1);

namespace SionModel\Service\Adapter;

use SionModel\Service\UserDirectoryInterface;

use function is_array;

/**
 * Wraps two callables so a host application's user table satisfies
 * {@see UserDirectoryInterface} without implementing it.
 *
 * This adapter is why breaking the SionModel → JUser cycle needed no change in JUser:
 * `JUser\Model\UserTable` already answers both questions, and declaring `implements` on it
 * would have forced `: array` return types onto two methods that have none — a covariance
 * fatal rather than a warning, and an audit of every return path in a package that had just
 * been tagged. The wrapper costs one indirection and buys that release staying untouched.
 *
 * A directory that cannot answer is treated as **empty rather than fatal**: the only
 * consumers are two display columns, and a change log that refuses to render because
 * nobody can be named is a worse outcome than one with a blank column.
 */
final class CallableUserDirectory implements UserDirectoryInterface
{
    /** @var callable(): mixed */
    private $resolveUsers;

    /** @var callable(): mixed */
    private $resolveUsernames;

    /**
     * @param callable(): mixed $resolveUsers
     * @param callable(): mixed $resolveUsernames
     */
    public function __construct(callable $resolveUsers, callable $resolveUsernames)
    {
        $this->resolveUsers     = $resolveUsers;
        $this->resolveUsernames = $resolveUsernames;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUsers(): array
    {
        $users = ($this->resolveUsers)();

        return is_array($users) ? $users : [];
    }

    /**
     * @return array<int, string>
     */
    public function getUsernames(): array
    {
        $usernames = ($this->resolveUsernames)();

        return is_array($usernames) ? $usernames : [];
    }
}
