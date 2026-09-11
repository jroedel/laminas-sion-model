<?php

declare(strict_types=1);

namespace SionModel\Filter;

use SionModel\Filter\Exception\InvalidArgumentException;

use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;
use function str_replace;
use function ucwords;

/**
 * The options half of a filter, and nothing else.
 *
 * ## Why there is a base class at all
 *
 * laminas' own is `@deprecated`, with the advice "custom filters should implement
 * FilterInterface without unnecessary inheritance", and that advice is right for a filter
 * written today. It is not right for the fourteen that already exist here and read
 * `$this->options` — they would each need rewriting, and rewriting fourteen working filters
 * to remove a base class is how a migration acquires a regression it cannot explain.
 *
 * So this is `Laminas\Filter\AbstractFilter` minus everything the application does not
 * reach: no `Traversable` options, no `hasPcreUnicodeSupport()`, no `__invoke()`, no
 * `getOptions()`.
 *
 * ## An unknown option is an error, not a shrug
 *
 * laminas threw too, and keeping that matters more here than it did there. Every rule in
 * this library implements the options the application's specifications actually declare —
 * measured, not guessed — and deliberately not the rest. A specification that asks for
 * `charlist` or `allowTags` is asking for something that is not here, and it has to hear
 * about it from a thrown exception rather than by quietly filtering differently.
 */
abstract class AbstractFilter implements FilterInterface
{
    /** @var array<string, mixed> */
    protected $options = [];

    /**
     * @param array<string, mixed>|null $options
     * @throws InvalidArgumentException
     */
    public function __construct($options = null)
    {
        if (null === $options || '' === $options || [] === $options) {
            return;
        }

        $this->setOptions($options);
    }

    /**
     * @param array<string, mixed> $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): static
    {
        if (! is_array($options)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an array of options; received "%s"',
                static::class,
                get_debug_type($options)
            ));
        }

        /** @var mixed $value */
        foreach ($options as $key => $value) {
            //`null_defaults_to` => `setNullDefaultsTo`, which is laminas' rule and is
            //still load-bearing: `SionModel\Filter\ToBit` is configured that way in 32
            //specifications.
            $setter = is_string($key)
                ? 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)))
                : null;

            if (null !== $setter && method_exists($this, $setter)) {
                $this->{$setter}($value);
                continue;
            }

            if (is_string($key) && array_key_exists($key, $this->options)) {
                $this->options[$key] = $value;
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'The option "%s" is not one %s accepts',
                is_string($key) ? $key : get_debug_type($key),
                static::class
            ));
        }

        return $this;
    }
}
