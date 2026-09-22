<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

use SionModel\Db\Sql\Identifier;

/** `col IS NOT NULL`. */
final class IsNotNull implements PredicateInterface
{
    public function __construct(private readonly string $identifier)
    {
    }

    public function render(): array
    {
        return [Identifier::quote($this->identifier) . ' IS NOT NULL', []];
    }
}
