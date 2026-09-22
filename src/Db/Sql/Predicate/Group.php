<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

use SionModel\Db\Sql\Where;

/**
 * Conditions in parentheses, as one condition.
 *
 * **This is where `A OR B AND C` is decided.** MariaDB binds `AND` tighter than `OR`, so a
 * set of alternatives added to a clause that also has a filter means the filter only applies
 * to the last alternative — a bug this repository has already shipped once. Saying `Group`
 * puts the brackets in and says at the call site that they were meant.
 *
 * laminas-db drew the same line by having two classes with the same contents: a
 * `Sql\Where` handed to `Select::where()` replaced the clause and rendered bare, while a
 * `Predicate\Predicate` handed to the same method became a parenthesised group. Which one a
 * call site had written was the only thing that decided whether brackets appeared, and
 * nothing in the call read as though it were about brackets.
 */
final class Group implements PredicateInterface
{
    public function __construct(private readonly Where $conditions)
    {
    }

    public function render(): array
    {
        [$sql, $values] = $this->conditions->render();

        return ['(' . $sql . ')', $values];
    }
}
