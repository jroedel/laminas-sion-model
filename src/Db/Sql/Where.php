<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

use Countable;
use SionModel\Db\Sql\Predicate\In;
use SionModel\Db\Sql\Predicate\InvalidPredicate;
use SionModel\Db\Sql\Predicate\IsNotNull;
use SionModel\Db\Sql\Predicate\IsNull;
use SionModel\Db\Sql\Predicate\Like;
use SionModel\Db\Sql\Predicate\Literal;
use SionModel\Db\Sql\Predicate\Operator;
use SionModel\Db\Sql\Predicate\PredicateInterface;

use function array_push;
use function count;
use function is_array;
use function is_int;
use function str_contains;

/**
 * A group of conditions joined by one operator, and itself a condition.
 *
 * Three laminas classes in one — `Laminas\Db\Sql\Where`, `Predicate\Predicate` and
 * `Predicate\PredicateSet` were a chain of three where the two subclasses added a default
 * and some fluent sugar. A nested group is just another instance added to its parent, which
 * is what puts the parentheses in.
 *
 * **Parentheses come from nesting, and only from nesting.** A flat set of conditions renders
 * flat, so `A OR B OR C` with an `AND D` appended is `A OR B OR C AND D` — which MariaDB
 * reads with `AND` binding tighter, i.e. not what the caller meant. That has cost this
 * repository a real bug; a group that means "any of these" must be its own `Where`.
 */
final class Where implements PredicateInterface, Countable
{
    public const OP_AND = 'AND';
    public const OP_OR  = 'OR';

    /** @var list<array{0: self::OP_*, 1: PredicateInterface}> */
    private array $predicates = [];

    public function addPredicate(PredicateInterface $predicate, string $combination = self::OP_AND): self
    {
        if (self::OP_AND !== $combination && self::OP_OR !== $combination) {
            throw new InvalidPredicate('A combination is either ' . self::OP_AND . ' or ' . self::OP_OR . '.');
        }

        $this->predicates[] = [$combination, $predicate];

        return $this;
    }

    /**
     * The array form, which is how most of this application states a `WHERE`.
     *
     * A string key is a column, and the value decides the comparison: `null` means `IS NULL`,
     * an array means `IN`, anything else means `=`. An integer key is a condition written
     * out in full. A {@see PredicateInterface} value under a string key is refused — the key
     * would be silently dropped, and laminas-db refused it for the same reason.
     *
     * @param array<array-key, mixed> $predicates
     */
    public function addPredicates(array $predicates, string $combination = self::OP_AND): self
    {
        foreach ($predicates as $key => $value) {
            if (is_int($key)) {
                if ($value instanceof PredicateInterface) {
                    $this->addPredicate($value, $combination);
                    continue;
                }

                $this->addPredicate(new Literal((string) $value), $combination);
                continue;
            }

            //An Expression under a *string* key is the right-hand side of a comparison, not a
            //condition — `['Count' => new Expression('COUNT(*)')]` means `\`Count\` = COUNT(*)`.
            //Under an integer key the same object is a condition in its own right. laminas-db
            //drew that line by having two classes of the name; here the key draws it, and the
            //check has to come before the PredicateInterface refusal below because Expression
            //is both.
            if (! $value instanceof Expression && $value instanceof PredicateInterface) {
                throw new InvalidPredicate(
                    'A condition under the key "' . $key . '" would discard the key; add it without one.'
                );
            }

            //laminas-db read a `?` in the key as a bindable expression. Nothing here writes one,
            //and without that branch the key would be quoted as a column name and the statement
            //would be wrong rather than rejected.
            if (str_contains($key, '?')) {
                throw new InvalidPredicate(
                    'The key "' . $key . '" is not a column name; state it as a condition of its own.'
                );
            }

            $this->addPredicate(match (true) {
                null === $value  => new IsNull($key),
                is_array($value) => new In($key, $value),
                default          => new Operator($key, Operator::EQ, $value),
            }, $combination);
        }

        return $this;
    }

    public function equalTo(string $identifier, mixed $value, string $combination = self::OP_AND): self
    {
        return $this->addPredicate(new Operator($identifier, Operator::EQ, $value), $combination);
    }

    public function isNull(string $identifier, string $combination = self::OP_AND): self
    {
        return $this->addPredicate(new IsNull($identifier), $combination);
    }

    public function isNotNull(string $identifier, string $combination = self::OP_AND): self
    {
        return $this->addPredicate(new IsNotNull($identifier), $combination);
    }

    public function like(string $identifier, string $like, string $combination = self::OP_AND): self
    {
        return $this->addPredicate(new Like($identifier, $like), $combination);
    }

    /** @param array<array-key, mixed> $valueSet */
    public function in(string $identifier, array $valueSet, string $combination = self::OP_AND): self
    {
        return $this->addPredicate(new In($identifier, $valueSet), $combination);
    }

    public function count(): int
    {
        return count($this->predicates);
    }

    public function render(): array
    {
        $sql    = '';
        $values = [];

        foreach ($this->predicates as $index => [$combination, $predicate]) {
            if (0 !== $index) {
                $sql .= ' ' . $combination . ' ';
            }

            [$fragment, $bound] = $predicate->render();
            $sql               .= $predicate instanceof self ? '(' . $fragment . ')' : $fragment;
            array_push($values, ...$bound);
        }

        return [$sql, $values];
    }
}
