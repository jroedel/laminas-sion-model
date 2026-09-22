<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

use SionModel\Db\Sql\Predicate\PredicateInterface;

use function array_push;
use function implode;
use function is_array;

/**
 * `UPDATE t SET … WHERE …`.
 *
 * `Laminas\Db\Sql\Update` until 2026-09-22. **An update with no `WHERE` is refused**, because
 * the one this application would otherwise write by accident rewrites every row of the
 * table; laminas-db allowed it and the mistake surfaces as a changed row count long after
 * the request that caused it.
 */
final class Update implements Statement
{
    /** @var array<string, mixed> */
    private array $set = [];

    private Where $where;

    public function __construct(private readonly string $table)
    {
        $this->where = new Where();
    }

    /** @param array<string, mixed> $values keyed by column */
    public function set(array $values): self
    {
        $this->set = $values;

        return $this;
    }

    /** @param Where|PredicateInterface|array<array-key, mixed> $predicate */
    public function where(Where|PredicateInterface|array $predicate, string $combination = Where::OP_AND): self
    {
        $this->where->add($predicate, $combination);

        return $this;
    }

    /** A copy gets its own `WHERE`; see {@see Select::__clone()} for why that matters. */
    public function __clone()
    {
        $this->where = clone $this->where;
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function render(): array
    {
        if (0 === $this->where->count()) {
            throw new Predicate\InvalidPredicate(
                'An UPDATE of `' . $this->table . '` with no WHERE would rewrite every row.'
            );
        }

        $assignments = [];
        $values      = [];

        foreach ($this->set as $column => $value) {
            if (null === $value) {
                $assignments[] = Identifier::quote($column) . ' = NULL';
                continue;
            }

            if ($value instanceof Expression) {
                [$sql, $params] = $value->render();
                $assignments[]  = Identifier::quote($column) . ' = ' . $sql;
                array_push($values, ...$params);
                continue;
            }

            $assignments[] = Identifier::quote($column) . ' = ?';
            $values[]      = $value;
        }

        [$fragment, $bound] = $this->where->render();
        array_push($values, ...$bound);

        return [
            'UPDATE ' . Identifier::quote($this->table)
                . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $fragment,
            $values,
        ];
    }
}
