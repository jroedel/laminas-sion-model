<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

use SionModel\Db\Sql\Identifier;

/**
 * `col LIKE ?`.
 *
 * The wildcards belong to the caller: every site in this application builds its own
 * `'%' . … . '%'`, and the escaping of `%` and `_` inside a user's search term is the
 * caller's business too — `TranslationsTable` is the one place that does it.
 */
final class Like implements PredicateInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $like
    ) {
    }

    public function render(): array
    {
        return [Identifier::quote($this->identifier) . ' LIKE ?', [$this->like]];
    }
}
