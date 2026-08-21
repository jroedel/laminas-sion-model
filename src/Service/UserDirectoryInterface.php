<?php

declare(strict_types=1);

namespace SionModel\Service;

/**
 * The users a change or a comment can be attributed to.
 *
 * Two screens need this and nothing else does: the change log renders "who changed
 * this" and the comments list renders "who wrote this". It replaces a hard
 * `use JUser\Model\UserTable` in {@see \SionModel\Db\Model\SionTable}, which named one
 * specific user implementation — from a package this one does not even require — for the
 * sake of two display columns.
 *
 * **The value shapes are deliberately loose, and both consumers tolerate absence.**
 * `sion-model/_changes-table.html.twig` reads `updatedBy.username` behind an `is iterable`
 * test and an `?? ''`; `PredicatesTable::processCommentRow()` reads a plain string behind
 * an `isset()`. So an implementation that has no username for a user should **omit that
 * user** rather than invent a placeholder — a blank cell is the designed outcome and a
 * fabricated name is not.
 *
 * Both methods return every user. That is what the two call sites ask for, because each
 * builds a lookup once and then names many rows from it; neither can say up front which
 * ids it will need.
 */
interface UserDirectoryInterface
{
    /**
     * Every user, keyed by user id.
     *
     * Only `username` is read, by the change-log template. Implementations are free to
     * return more.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUsers(): array;

    /**
     * Every username, keyed by user id.
     *
     * Separate from {@see self::getUsers()} rather than derived from it: the one
     * implementation this repository knows of caches the two under different keys, and
     * deriving either from the other would trade a cheap query for an expensive one.
     *
     * @return array<int, string>
     */
    public function getUsernames(): array;
}
