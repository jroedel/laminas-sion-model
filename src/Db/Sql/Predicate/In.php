<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

use SionModel\Db\Sql\Identifier;

use function array_values;
use function count;
use function implode;
use function array_fill;

/**
 * `col IN (?, ?, …)`, one placeholder per value.
 *
 * **An empty set is refused.** MariaDB rejects `IN ()` as a syntax error, so laminas-db
 * produced a statement that could only fail at the server; whatever the caller meant by
 * "match none of these", saying it here is the only place the mistake is still cheap.
 */
final class In implements PredicateInterface
{
    /** @var list<mixed> */
    private readonly array $valueSet;

    /** @param array<array-key, mixed> $valueSet */
    public function __construct(private readonly string $identifier, array $valueSet)
    {
        if ([] === $valueSet) {
            throw new InvalidPredicate(
                'An IN predicate needs at least one value; `' . $identifier . ' IN ()` is a syntax error.'
            );
        }

        $this->valueSet = array_values($valueSet);
    }

    public function render(): array
    {
        $placeholders = implode(', ', array_fill(0, count($this->valueSet), '?'));

        return [Identifier::quote($this->identifier) . ' IN (' . $placeholders . ')', $this->valueSet];
    }
}
