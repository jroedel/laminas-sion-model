<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

use InvalidArgumentException;

/** A condition this builder cannot write, refused where it is declared rather than at the database. */
final class InvalidPredicate extends InvalidArgumentException
{
}
