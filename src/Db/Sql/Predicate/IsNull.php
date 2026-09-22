<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

use SionModel\Db\Sql\Identifier;

/** `col IS NULL`. Binds nothing: SQL has no placeholder for the absence of a value. */
final class IsNull implements PredicateInterface
{
    public function __construct(private readonly string $identifier)
    {
    }

    public function render(): array
    {
        return [Identifier::quote($this->identifier) . ' IS NULL', []];
    }
}
