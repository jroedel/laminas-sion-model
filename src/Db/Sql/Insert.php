<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

use function array_keys;
use function array_map;
use function implode;

/**
 * `INSERT INTO t (cols) VALUES (…)`.
 *
 * `Laminas\Db\Sql\Insert` until 2026-09-22, without the `INSERT … SELECT` form, which
 * nothing here uses. `INSERT … ON DUPLICATE KEY UPDATE` is not here either and never was:
 * laminas-db has no such clause, so JTranslate writes those two statements by hand.
 *
 * A `null` is written as the literal `NULL` rather than bound. That is what laminas-db did,
 * it is what the recording holds, and it is not cosmetic — a bound `null` and a literal
 * `NULL` are the same value to the server, but the statement text is what the recording
 * compares.
 */
final class Insert implements Statement
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(private readonly string $table)
    {
    }

    /** @param array<string, mixed> $values keyed by column */
    public function values(array $values): self
    {
        $this->values = $values;

        return $this;
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function render(): array
    {
        $columns     = array_map(Identifier::quote(...), array_keys($this->values));
        $bound       = [];
        $placeholders = [];

        foreach ($this->values as $value) {
            if (null === $value) {
                $placeholders[] = 'NULL';
                continue;
            }

            if ($value instanceof Expression) {
                $placeholders[] = (string) $value;
                continue;
            }

            $placeholders[] = '?';
            $bound[]        = $value;
        }

        return [
            'INSERT INTO ' . Identifier::quote($this->table)
                . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $bound,
        ];
    }
}
