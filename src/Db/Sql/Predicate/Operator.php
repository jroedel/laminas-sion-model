<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

use SionModel\Db\Sql\Expression;
use SionModel\Db\Sql\Identifier;

use function in_array;
use function sprintf;

/** `left <op> right`, with the right side bound unless it is an {@see Expression}. */
final class Operator implements PredicateInterface
{
    public const EQ  = '=';
    public const NEQ = '<>';
    public const LT  = '<';
    public const LTE = '<=';
    public const GT  = '>';
    public const GTE = '>=';

    private const OPERATORS = [self::EQ, self::NEQ, self::LT, self::LTE, self::GT, self::GTE];

    /**
     * Compare two columns rather than a column and a value.
     *
     * A join's `ON` — `relationships.SubjectEntityId = comments.CommentId`. laminas-db said
     * this by passing two type flags to the constructor, which meant every ordinary
     * comparison also carried the machinery for it; a named constructor says the same thing
     * once, and cannot be reached with a value that came from a request.
     */
    public static function betweenColumns(string $left, string $operator, string $right): self
    {
        return new self($left, $operator, new Expression(Identifier::quote($right)));
    }

    public function __construct(
        private readonly string $identifier,
        private readonly string $operator,
        private readonly mixed $value
    ) {
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidPredicate(sprintf('"%s" is not an operator this builder writes.', $operator));
        }
    }

    public function render(): array
    {
        if ($this->value instanceof Expression) {
            return [Identifier::quote($this->identifier) . ' ' . $this->operator . ' ' . (string) $this->value, []];
        }

        return [Identifier::quote($this->identifier) . ' ' . $this->operator . ' ?', [$this->value]];
    }
}
