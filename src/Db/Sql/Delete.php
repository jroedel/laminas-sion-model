<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

use SionModel\Db\Sql\Predicate\InvalidPredicate;
use SionModel\Db\Sql\Predicate\PredicateInterface;

use function is_array;

/**
 * `DELETE FROM t WHERE …`.
 *
 * `Laminas\Db\Sql\Delete` until 2026-09-22, and refusing an empty `WHERE` for the same
 * reason {@see Update} does — with one difference that makes it matter more: an
 * unconditional delete cannot be put right by running it again with better arguments.
 */
final class Delete implements Statement
{
    private Where $where;

    public function __construct(private readonly string $table)
    {
        $this->where = new Where();
    }

    /** @param Where|PredicateInterface|array<array-key, mixed> $predicate */
    public function where(Where|PredicateInterface|array $predicate, string $combination = Where::OP_AND): self
    {
        if (is_array($predicate)) {
            $this->where->addPredicates($predicate, $combination);

            return $this;
        }

        $this->where->addPredicate($predicate, $combination);

        return $this;
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function render(): array
    {
        if (0 === $this->where->count()) {
            throw new InvalidPredicate('A DELETE from `' . $this->table . '` with no WHERE would empty the table.');
        }

        [$fragment, $values] = $this->where->render();

        return ['DELETE FROM ' . Identifier::quote($this->table) . ' WHERE ' . $fragment, $values];
    }
}
