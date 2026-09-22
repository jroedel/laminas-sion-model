<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

/**
 * A condition written out as given, binding nothing.
 *
 * What an integer-keyed entry in a `where([...])` array becomes: `where(['deleted = 0'])`.
 * Like {@see \SionModel\Db\Sql\Expression} it is emitted verbatim and must never be built
 * from user input.
 */
final class Literal implements PredicateInterface
{
    public function __construct(private readonly string $literal)
    {
    }

    public function render(): array
    {
        return [$this->literal, []];
    }
}
