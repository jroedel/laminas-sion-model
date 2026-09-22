<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

use SionModel\Db\Sql\Predicate\InvalidPredicate;
use SionModel\Db\Sql\Predicate\PredicateInterface;

use function array_key_first;
use function array_map;
use function array_push;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function preg_split;
use function sprintf;
use function str_contains;
use function strcasecmp;
use function trim;

/**
 * A `SELECT`, assembled clause by clause and rendered with its values bound.
 *
 * `Laminas\Db\Sql\Select` until 2026-09-22, reduced to the clauses this application writes.
 * `test/Db/sql-surface.txt` is the contract: every statement in it must come back byte for
 * byte, which is what `./tools/sql-surface.sh --check` asserts.
 *
 * **Two rules are load-bearing and easy to miss**, because both are invisible in the call
 * sites and visible in every recorded statement:
 *
 * - a column is written `` `table`.`col` AS `col` `` — prefixed with the table and aliased to
 *   itself — so a joined table's column of the same name does not silently replace it in the
 *   result row;
 * - `ORDER BY` always carries a direction, `ASC` when none was asked for.
 *
 * What is deliberately **not** here: `UNION` and the other set operations, `DISTINCT`, and
 * sub-selects as a table. No call site uses them, and laminas-db's support for them is where
 * most of its complexity lived.
 */
final class Select implements Statement
{
    public const ORDER_ASCENDING  = 'ASC';
    public const ORDER_DESCENDING = 'DESC';

    public const JOIN_INNER = 'inner';
    public const JOIN_LEFT  = 'left';
    public const JOIN_RIGHT = 'right';

    /** Clause names `reset()` accepts, matching the method that sets each. */
    public const COLUMNS = 'columns';
    public const JOINS   = 'joins';
    public const WHERE   = 'where';
    public const GROUP   = 'group';
    public const HAVING  = 'having';
    public const ORDER   = 'order';
    public const LIMIT   = 'limit';
    public const OFFSET  = 'offset';

    /** @var array<array-key, string|Expression> */
    private array $columns = ['*'];

    /** @var list<array{name: string, alias: ?string, on: string|PredicateInterface, columns: array<array-key, string|Expression>, type: string}> */
    private array $joins = [];

    private Where $where;
    private Where $having;

    /** @var list<string> */
    private array $group = [];

    /** @var list<array{0: string, 1: string}|Expression> */
    private array $order = [];

    private ?int $limit  = null;
    private ?int $offset = null;

    private string $table;
    private ?string $alias = null;

    /** @param string|array<string, string> $table a name, or `[alias => name]` */
    public function __construct(string|array $table)
    {
        $this->where  = new Where();
        $this->having = new Where();
        $this->from($table);
    }

    /** @param string|array<string, string> $table */
    public function from(string|array $table): self
    {
        if (is_array($table)) {
            $alias = array_key_first($table);
            if (! is_string($alias)) {
                throw new InvalidPredicate('A table given as an array is [alias => name].');
            }
            $this->alias = $alias;
            $this->table = $table[$alias];

            return $this;
        }

        $this->alias = null;
        $this->table = $table;

        return $this;
    }

    /** @param array<array-key, string|Expression> $columns keyed by alias where one is wanted */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param string|array<string, string>             $name    a name, or `[alias => name]`
     * @param array<array-key, string|Expression>      $columns the joined table's columns to select
     */
    public function join(
        string|array $name,
        string|PredicateInterface $on,
        array $columns = ['*'],
        string $type = self::JOIN_INNER
    ): self {
        $alias = null;
        if (is_array($name)) {
            $alias = array_key_first($name);
            if (! is_string($alias)) {
                throw new InvalidPredicate('A joined table given as an array is [alias => name].');
            }
            $name = $name[$alias];
        }

        $this->joins[] = [
            'name'    => $name,
            'alias'   => $alias,
            'on'      => $on,
            'columns' => $columns,
            'type'    => $type,
        ];

        return $this;
    }

    /** @param Where|PredicateInterface|array<array-key, mixed> $predicate */
    public function where(Where|PredicateInterface|array $predicate, string $combination = Where::OP_AND): self
    {
        return $this->addTo($this->where, $predicate, $combination);
    }

    /** @param Where|PredicateInterface|array<array-key, mixed> $predicate */
    public function having(Where|PredicateInterface|array $predicate, string $combination = Where::OP_AND): self
    {
        return $this->addTo($this->having, $predicate, $combination);
    }

    /** @param list<string> $columns */
    public function group(array $columns): self
    {
        $this->group = $columns;

        return $this;
    }

    /**
     * `['a', 'b' => 'DESC']`, or one string which may name several columns separated by commas.
     *
     * @param string|array<array-key, string|Expression> $order
     */
    public function order(string|array $order): self
    {
        if (is_string($order)) {
            $order = str_contains($order, ',') ? (preg_split('/,\s+/', $order) ?: []) : [$order];
        }

        foreach ($order as $key => $value) {
            if ($value instanceof Expression) {
                $this->order[] = $value;
                continue;
            }

            if (is_int($key)) {
                //`order(['UpdatedOn DESC'])` — one string carrying both.
                if (str_contains($value, ' ')) {
                    [$key, $value] = preg_split('/ /', $value, 2) ?: [$value, self::ORDER_ASCENDING];
                } else {
                    $key   = $value;
                    $value = self::ORDER_ASCENDING;
                }
            }

            $this->order[] = [
                (string) $key,
                0 === strcasecmp(trim($value), self::ORDER_DESCENDING)
                    ? self::ORDER_DESCENDING
                    : self::ORDER_ASCENDING,
            ];
        }

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /** Put one clause back to its initial state. */
    public function reset(string $clause): self
    {
        match ($clause) {
            self::COLUMNS => $this->columns = ['*'],
            self::JOINS   => $this->joins = [],
            self::WHERE   => $this->where = new Where(),
            self::HAVING  => $this->having = new Where(),
            self::GROUP   => $this->group = [],
            self::ORDER   => $this->order = [],
            self::LIMIT   => $this->limit = null,
            self::OFFSET  => $this->offset = null,
            default       => throw new InvalidPredicate(sprintf('"%s" is not a clause of a SELECT.', $clause)),
        };

        return $this;
    }

    /**
     * @return array{0: string, 1: list<mixed>} the statement, and the values its placeholders
     *         bind, in the order they appear
     */
    public function render(): array
    {
        $values = [];

        //The table's own name is what a column is prefixed with, unless the table is aliased.
        $from   = Identifier::quote($this->table);
        $prefix = null === $this->alias ? $from : Identifier::quote($this->alias);
        if (null !== $this->alias) {
            $from .= ' AS ' . Identifier::quote($this->alias);
        }

        $sql = 'SELECT ' . implode(', ', $this->renderColumns($prefix)) . ' FROM ' . $from;

        foreach ($this->joins as $join) {
            $name = Identifier::quote($join['name']);
            if (null !== $join['alias']) {
                $name .= ' AS ' . Identifier::quote($join['alias']);
            }

            if ($join['on'] instanceof PredicateInterface) {
                [$on, $bound] = $join['on']->render();
                array_push($values, ...$bound);
            } else {
                $on = Identifier::quoteFragment($join['on'], ['=', 'AND', 'OR', '(', ')', 'BETWEEN', '<', '>']);
            }

            $sql .= ' ' . self::joinType($join['type']) . ' JOIN ' . $name . ' ON ' . $on;
        }

        if (0 !== $this->where->count()) {
            [$fragment, $bound] = $this->where->render();
            $sql               .= ' WHERE ' . $fragment;
            array_push($values, ...$bound);
        }

        if ([] !== $this->group) {
            $sql .= ' GROUP BY ' . implode(', ', array_map(Identifier::quote(...), $this->group));
        }

        if (0 !== $this->having->count()) {
            [$fragment, $bound] = $this->having->render();
            $sql               .= ' HAVING ' . $fragment;
            array_push($values, ...$bound);
        }

        if ([] !== $this->order) {
            $parts = [];
            foreach ($this->order as $term) {
                $parts[] = $term instanceof Expression
                    ? (string) $term
                    : Identifier::quoteFragment($term[0]) . ' ' . $term[1];
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if (null !== $this->limit) {
            $sql     .= ' LIMIT ?';
            $values[] = $this->limit;
        }

        if (null !== $this->offset) {
            $sql     .= ' OFFSET ?';
            $values[] = $this->offset;
        }

        return [$sql, $values];
    }

    /** @return list<string> */
    private function renderColumns(string $prefix): array
    {
        $rendered = [];

        foreach ($this->columns as $alias => $column) {
            $rendered[] = self::renderColumn($column, $alias, $prefix);
        }

        foreach ($this->joins as $join) {
            $joinPrefix = Identifier::quote($join['alias'] ?? $join['name']);
            foreach ($join['columns'] as $alias => $column) {
                $rendered[] = self::renderColumn($column, $alias, $joinPrefix);
            }
        }

        return $rendered;
    }

    private static function renderColumn(string|Expression $column, string|int $alias, string $prefix): string
    {
        if ('*' === $column) {
            return $prefix . '.*';
        }

        //An expression is not a column of any table, so it is never prefixed, and it carries
        //no name of its own — an alias has to be given or the result row has nothing to key on.
        if ($column instanceof Expression) {
            return is_string($alias)
                ? (string) $column . ' AS ' . Identifier::quote($alias)
                : (string) $column;
        }

        $name = $prefix . '.' . Identifier::quote($column);

        return $name . ' AS ' . Identifier::quote(is_string($alias) ? $alias : $column);
    }

    private static function joinType(string $type): string
    {
        return match ($type) {
            self::JOIN_INNER => 'INNER',
            self::JOIN_LEFT  => 'LEFT',
            self::JOIN_RIGHT => 'RIGHT',
            default          => throw new InvalidPredicate(sprintf('"%s" is not a join this builder writes.', $type)),
        };
    }

    /** @param Where|PredicateInterface|array<array-key, mixed> $predicate */
    private function addTo(Where $set, Where|PredicateInterface|array $predicate, string $combination): self
    {
        if (is_array($predicate)) {
            $set->addPredicates($predicate, $combination);

            return $this;
        }

        $set->addPredicate($predicate, $combination);

        return $this;
    }
}
