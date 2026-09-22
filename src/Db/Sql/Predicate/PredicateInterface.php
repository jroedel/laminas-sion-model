<?php

declare(strict_types=1);

namespace SionModel\Db\Sql\Predicate;

/**
 * One condition, rendered as a fragment and the values it binds.
 *
 * `Laminas\Db\Sql\Predicate\PredicateInterface` until 2026-09-22, cut down to the one thing
 * every implementation was asked for. laminas expressed this as `getExpressionData()`, an
 * array of `[format, values, types]` triples interpreted by an abstract walker; the walker
 * existed to support platforms and quoting strategies this application has exactly one of.
 */
interface PredicateInterface
{
    /**
     * @return array{0: string, 1: list<mixed>} the SQL fragment, and the values its `?`
     *         placeholders bind, in order
     */
    public function render(): array;
}
