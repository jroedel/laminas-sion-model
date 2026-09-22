<?php

declare(strict_types=1);

namespace SionModel\Db;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function count;
use function reset;

/**
 * The rows a `SELECT` returned, as plain arrays.
 *
 * `Laminas\Db\ResultSet\ResultSet` until 2026-09-22, which handed out an `ArrayObject` per
 * row — four call sites in this application called `getArrayCopy()` on one, and one asked
 * `is_array($row) || $row instanceof ArrayObject` because it could not tell. A row is an
 * array here, and those questions have one answer.
 *
 * **Rows are fetched when the set is built, not while it is walked.** laminas-db's set was
 * buffered too (PDO buffers by default against MySQL), so this is the behaviour that was
 * already in force, and it is what makes `count()` and `current()` answerable at all: PDO's
 * own row count is not dependable for a `SELECT`, and a statement that has been walked once
 * cannot be walked again.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class ResultSet implements IteratorAggregate, Countable
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private readonly array $rows)
    {
    }

    /**
     * The first row, or null when there is none — what a lookup by primary key asks for.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $rows = $this->rows;

        return false === ($first = reset($rows)) ? null : $first;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }
}
