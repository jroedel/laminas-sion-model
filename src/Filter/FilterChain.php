<?php

declare(strict_types=1);

namespace SionModel\Filter;

use SionModel\Filter\Exception\InvalidArgumentException;

use function sprintf;

/**
 * Filters applied in the order they were attached.
 *
 * Three call sites, all of them constructing the chain by hand — a cache key, a phone
 * number, a trimmed string array. The form engine does not use this: it walks the
 * specification itself, because a specification is data and a chain is an object graph.
 *
 * laminas' version carried priorities, a plugin manager, serialization and `merge()`.
 * Nothing here reaches any of it. `attachByName()` stays because
 * {@see TrimStringArray} uses it, and it resolves through the same {@see Registry} the
 * engine does, so a name means one thing in the application.
 */
final class FilterChain implements FilterInterface
{
    /** @var list<FilterInterface> */
    private array $filters = [];

    public function attach(FilterInterface $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     * @throws InvalidArgumentException
     */
    public function attachByName(string $name, array $options = []): self
    {
        $filter = Registry::get($name, $options);

        if (! $filter instanceof FilterInterface) {
            throw new InvalidArgumentException(sprintf('"%s" is not a filter', $name));
        }

        return $this->attach($filter);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        foreach ($this->filters as $filter) {
            /** @var mixed $value */
            $value = $filter->filter($value);
        }

        return $value;
    }
}
