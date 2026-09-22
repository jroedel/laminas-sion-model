<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

/**
 * Something that renders to a statement and the values it binds.
 *
 * The four builders, and the seam the connection executes against: a caller hands it a
 * `Statement` and does not care which. laminas-db expressed this as `PreparableSqlInterface`
 * over an `AbstractSql` base that carried the platform, the driver and a parameter container
 * through every clause; here it is one method, because the platform is MariaDB and the
 * driver is PDO and neither has ever been anything else in this application.
 */
interface Statement
{
    /**
     * @return array{0: string, 1: list<mixed>} the statement, and the values its `?`
     *         placeholders bind, in the order they appear in it
     */
    public function render(): array;
}
