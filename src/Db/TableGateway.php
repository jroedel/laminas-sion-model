<?php

declare(strict_types=1);

namespace SionModel\Db;

use SionModel\Db\Sql\Delete;
use SionModel\Db\Sql\Insert;
use SionModel\Db\Sql\Predicate\PredicateInterface;
use SionModel\Db\Sql\Select;
use SionModel\Db\Sql\Update;
use SionModel\Db\Sql\Where;

/**
 * One table, and the four things this application does to one.
 *
 * `Laminas\Db\TableGateway\TableGateway` until 2026-09-22, which was an event-driven
 * assembly — a feature set, a result-set prototype, an `AbstractTableGateway` with six
 * initialisation hooks — for what is a table name and a connection. Nothing here ever
 * registered a feature or replaced a prototype.
 *
 * `selectWith()` and `select()` are both kept because the call sites split evenly between
 * them: one builds the whole statement and hands it over, the other names the table's
 * condition and nothing else.
 */
final class TableGateway
{
    public function __construct(
        private readonly string $table,
        private readonly Connection $connection
    ) {
    }

    public function table(): string
    {
        return $this->table;
    }

    /**
     * The connection this gateway runs on.
     *
     * `JTranslate\Model\TranslationsTable` composes two gateways and needs the connection
     * they share for the statements it writes by hand; laminas-db's `getAdapter()`, which it
     * inherited from `AbstractTableGateway`, is where it got it before.
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    /** A statement built against this table, run as given. */
    public function selectWith(Select $select): ResultSet
    {
        return $this->connection->select($select);
    }

    /** @param Where|PredicateInterface|array<array-key, mixed>|null $where */
    public function select(Where|PredicateInterface|array|null $where = null): ResultSet
    {
        $select = new Select($this->table);
        if (null !== $where) {
            $select->where($where);
        }

        return $this->connection->select($select);
    }

    /**
     * @param array<string, mixed> $values keyed by column
     * @return int the number of rows inserted
     */
    public function insert(array $values): int
    {
        return $this->connection->execute((new Insert($this->table))->values($values));
    }

    /**
     * @param array<string, mixed>                              $values keyed by column
     * @param Where|PredicateInterface|array<array-key, mixed>  $where  never optional: an
     *        update with no condition rewrites every row, and {@see Update} refuses one
     * @return int the number of rows changed
     */
    public function update(array $values, Where|PredicateInterface|array $where): int
    {
        return $this->connection->execute((new Update($this->table))->set($values)->where($where));
    }

    /**
     * @param Where|PredicateInterface|array<array-key, mixed> $where
     * @return int the number of rows removed
     */
    public function delete(Where|PredicateInterface|array $where): int
    {
        return $this->connection->execute((new Delete($this->table))->where($where));
    }

    /** The id the last insert through this connection generated. */
    public function getLastInsertValue(): int
    {
        return $this->connection->lastInsertId();
    }
}
