<?php

declare(strict_types=1);

namespace SionModel\Db;

use PDO;
use PDOException;
use PDOStatement;
use SionModel\Db\Sql\Statement;

use function array_key_exists;
use function is_bool;
use function is_int;
use function microtime;
use function is_array;
use function sprintf;

/**
 * The one connection this application has, and the two things it is asked to do.
 *
 * `Laminas\Db\Adapter\Adapter` until 2026-09-22 — a driver abstraction, a platform
 * abstraction, a parameter container and a statement cache, over a tree that has only ever
 * talked to MariaDB through PDO. What is left is a `SELECT` that returns rows and everything
 * else that returns a count.
 *
 * **`query()` is two methods here.** laminas-db's took a statement and either an array of
 * values or a mode constant, and returned a result set, a result object or a statement
 * depending on what it was handed; `SionTable` and `TranslationsTable` both carry a
 * `$sqlArgs = Adapter::QUERY_MODE_EXECUTE; //make sure query executes` to steer it. Asking
 * for rows and asking for an effect are different questions, and the call site knows which
 * it is asking.
 *
 * **Prepared statements are emulated**, which is the default and is left alone deliberately:
 * the tree interpolates a `LIMIT` and, in one place, a column name, and MySQL cannot bind
 * either. `docs/sql-observations.md` records what that costs.
 */
final class Connection
{
    /** @var (callable(string, list<mixed>, float): void)|null */
    private $listener = null;

    /** How many callers have opened a transaction; see {@see beginTransaction()}. */
    private int $depth = 0;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Watch every statement this connection runs.
     *
     * `Laminas\Db\Adapter\Profiler\ProfilerInterface` until 2026-09-22, which was an
     * interface, a start/finish pair and a `StatementContainerInterface` the listener had to
     * narrow before it could read the SQL. Two things in this repository want the same thing
     * — the fuzz suite records what a form asked the database, and `tools/perf` times it —
     * and both want the statement and its values, once, before it runs.
     *
     * The listener is called in a `finally`, with the statement, its values and how long the
     * server took. So it sees a statement that threw as well as one that returned, which is
     * the case a recording is most wanted for. `null` detaches.
     *
     * @param (callable(string, list<mixed>, float): void)|null $listener
     */
    public function watch(?callable $listener): void
    {
        $this->listener = $listener;
    }

    /**
     * @param array<string, mixed> $db the `db` block of the merged configuration
     * @throws PDOException when the server refuses the connection.
     */
    public static function fromCredentials(array $db): self
    {
        foreach (['database', 'hostname', 'username', 'password'] as $key) {
            if (! array_key_exists($key, $db)) {
                throw new PDOException(sprintf('The `db` configuration is missing `%s`.', $key));
            }
        }

        $options = is_array($db['driver_options'] ?? null) ? $db['driver_options'] : [];

        return new self(new PDO(
            'mysql:dbname=' . $db['database'] . ';host=' . $db['hostname'],
            (string) $db['username'],
            (string) $db['password'],
            $options + [
                //A failed statement must throw. PDO's default is to set an error code and
                //carry on, which is how a write that the server rejected reads as a write
                //that happened.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        ));
    }

    /** @param list<mixed> $values bound in order when the statement is given as a string */
    public function select(Statement|string $statement, array $values = []): ResultSet
    {
        $prepared = $this->run($statement, $values);

        //A statement with no result set answers 0 columns. Without this, asking a write for
        //its rows returns an empty set and reads as "nothing matched" — the exact silence
        //that made laminas-db's single `query()` hard to reason about, reproduced.
        if (0 === $prepared->columnCount()) {
            throw new PDOException('This statement returns no rows; run it with execute().');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $prepared->fetchAll(PDO::FETCH_ASSOC);

        return new ResultSet($rows);
    }

    /**
     * @param list<mixed> $values
     * @return int the number of rows the statement changed
     */
    public function execute(Statement|string $statement, array $values = []): int
    {
        $prepared = $this->run($statement, $values);

        //The mirror of the check in select(): a SELECT run for effect changes nothing, so a
        //`0` here would be a true answer to a question the caller did not mean to ask.
        if (0 !== $prepared->columnCount()) {
            throw new PDOException('This statement returns rows; run it with select().');
        }

        return $prepared->rowCount();
    }

    /** The id the last `INSERT` generated, on this connection. */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Open a transaction, or note that one is already open.
     *
     * **Nesting is counted, not passed on.** PDO throws on a second `beginTransaction()`, and
     * this application nests in the ordinary course of things: a caller wraps several writes,
     * and one of them — `TranslationsTable::writeMissingPhrasesToDb()` — opens a transaction of
     * its own. laminas-db counted the depth
     * (`Adapter\Driver\Pdo\Connection::$nestedTransactionsCount`) and so does this.
     *
     * The counting is honest about what it does *not* give: an inner `commit()` commits
     * nothing, and an inner `rollBack()` discards the outer caller's writes too. MariaDB has
     * savepoints and PDO does not expose them; nothing here has ever needed one.
     */
    public function beginTransaction(): void
    {
        if (0 === $this->depth) {
            $this->pdo->beginTransaction();
        }

        $this->depth++;
    }

    /** Commit when the outermost caller says so, and not before. */
    public function commit(): void
    {
        if ($this->depth > 0) {
            $this->depth--;
        }

        if (0 === $this->depth && $this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    /** Roll the whole transaction back, whatever the depth. */
    public function rollBack(): void
    {
        $this->depth = 0;

        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * The PDO handle, for the two callers that need more than a statement.
     *
     * The migration runner reads server metadata and the schema inspector asks for tables;
     * both are about the connection rather than about this application's data.
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param list<mixed> $values */
    private function run(Statement|string $statement, array $values): PDOStatement
    {
        if ($statement instanceof Statement) {
            [$sql, $bound] = $statement->render();

            if ([] !== $values) {
                throw new PDOException(
                    'A built statement carries its own values; passing more alongside it would'
                    . ' bind them in an order nothing decided: ' . $sql
                );
            }
        } else {
            $sql   = $statement;
            $bound = $values;
        }

        $startedAt = microtime(true);

        try {
            $prepared = $this->pdo->prepare($sql);
            self::bind($prepared, $bound);
            $prepared->execute();

            return $prepared;
        } finally {
            if (null !== $this->listener) {
                ($this->listener)($sql, $bound, microtime(true) - $startedAt);
            }
        }
    }

    /**
     * Bind each value with a type taken from its PHP type.
     *
     * **`PDOStatement::execute($values)` is not equivalent**, and the difference is not
     * cosmetic: it binds everything as a string, so `false` is sent as `''` and MariaDB
     * refuses it for an integer column — `sch_publications.IsAccessableForFree` is where that
     * surfaced. laminas-db bound by PHP type
     * (`Adapter\Driver\Pdo\Statement::bindParametersFromContainer()`), and this reproduces
     * it: a bool goes as `0`/`1`, an int unquoted, everything else quoted.
     *
     * @param list<mixed> $values
     */
    private static function bind(PDOStatement $statement, array $values): void
    {
        foreach ($values as $index => $value) {
            $type = match (true) {
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value)  => PDO::PARAM_INT,
                null === $value => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            //PDO numbers positional parameters from one.
            $statement->bindValue($index + 1, $value, $type);
        }
    }
}
