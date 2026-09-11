<?php

declare(strict_types=1);

namespace SionModel\Filter;

use SionModel\Filter\Exception\InvalidArgumentException;

use function is_scalar;
use function is_string;
use function preg_replace;

/**
 * A regular expression applied to a value.
 *
 * Three call sites, none of them a form: two normalise a phone number and a cache key, one
 * strips backslashes out of a class name. All three construct it directly with a `pattern`
 * and a `replacement`, so there is no specification naming it.
 *
 * `Books\Filter\SortText` reproduced this class in 2026-08 for the same reason this file
 * exists; it stays where it is, because it also carries the sort-text rules and merging the
 * two would be a refactor dressed as a migration.
 */
final class PregReplace extends AbstractFilter
{
    /** @var array{pattern: string|null, replacement: string} */
    protected $options = [
        'pattern'     => null,
        'replacement' => '',
    ];

    /** @throws InvalidArgumentException */
    public function setPattern(mixed $pattern): static
    {
        if (! is_string($pattern)) {
            throw new InvalidArgumentException('A PregReplace pattern must be a string');
        }

        $this->options['pattern'] = $pattern;

        return $this;
    }

    /** @throws InvalidArgumentException */
    public function setReplacement(mixed $replacement): static
    {
        if (! is_string($replacement)) {
            throw new InvalidArgumentException('A PregReplace replacement must be a string');
        }

        $this->options['replacement'] = $replacement;

        return $this;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        $pattern = $this->options['pattern'];

        if (null === $pattern) {
            throw new InvalidArgumentException('A PregReplace filter was asked to filter before it had a pattern');
        }

        if (! is_scalar($value)) {
            return $value;
        }

        return preg_replace($pattern, $this->options['replacement'], (string) $value);
    }
}
