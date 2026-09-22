<?php

declare(strict_types=1);

namespace SionModel\Db;

use PDO;
use PDOException;
use PDOStatement;
use SionModel\Db\Sql\Statement;

use function array_key_exists;
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
    public function __construct(private readonly PDO $pdo)
    {
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
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($statement, $values)->fetchAll(PDO::FETCH_ASSOC);

        return new ResultSet($rows);
    }

    /**
     * @param list<mixed> $values
     * @return int the number of rows the statement changed
     */
    public function execute(Statement|string $statement, array $values = []): int
    {
        return $this->run($statement, $values)->rowCount();
    }

    /** The id the last `INSERT` generated, on this connection. */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
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

        $prepared = $this->pdo->prepare($sql);
        $prepared->execute($bound);

        return $prepared;
    }
}
