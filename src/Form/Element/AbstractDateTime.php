<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * Shared by the native date/time inputs: one `date()`-compatible format string.
 *
 * The format is the only state, and it is not decoration —
 * `SionModel\Form\InputTypeRules::date()` reads it to build the `Date` validator, so an
 * element and the rule that judges its value describe the same shape from one place.
 * `SionModel\Form\InputTypeRules` also branches on this class, which is why the hierarchy
 * keeps a shared parent rather than repeating a `format` property per input type.
 */
abstract class AbstractDateTime extends Element
{
    /** RFC-3339, which is what an HTML5 date/time input transmits. */
    protected string $format = 'Y-m-d\TH:iP';

    /** @param iterable<string, mixed> $options */
    public function setOptions(iterable $options): static
    {
        parent::setOptions($options);

        if (isset($this->options['format'])) {
            $this->setFormat((string) $this->options['format']);
        }

        return $this;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
