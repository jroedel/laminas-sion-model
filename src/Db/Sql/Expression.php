<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

use SionModel\Db\Sql\Predicate\PredicateInterface;

/**
 * A fragment of SQL that is written out as given, not quoted as an identifier.
 *
 * `COUNT(*)`, `MONTH(\`UpdatedOn\`)`, `ST_AsText(\`Location\`)` — the places where the
 * application needs the database's own vocabulary. `Laminas\Db\Sql\Expression` until
 * 2026-09-22.
 *
 * It doubles as a condition — `` `VisitedAt` >= DATE_ADD(NOW(), INTERVAL -1 MONTH) `` is one
 * of these in a `WHERE` — which is why it implements {@see PredicateInterface}. laminas-db
 * kept two unrelated classes of this name, one per role, and every call site had to know
 * which namespace it was importing.
 *
 * **The string is emitted verbatim**, so it must never be built from user input. Every one
 * of the application's expressions is a literal in the source; that is the only safe use and
 * the reason this class carries no escaping of its own.
 */
final class Expression implements PredicateInterface
{
    public function __construct(private readonly string $expression)
    {
    }

    public function __toString(): string
    {
        return $this->expression;
    }

    public function render(): array
    {
        return [$this->expression, []];
    }
}
