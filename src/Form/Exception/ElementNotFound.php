<?php

declare(strict_types=1);

namespace SionModel\Form\Exception;

use InvalidArgumentException;

use function sprintf;

/**
 * `Laminas\Form\Exception\InvalidElementException`, which is what `Fieldset::get()` throws.
 *
 * It extends `InvalidArgumentException` for the same reason laminas' does: eleven call
 * sites in this application and its tests catch the general one, and a class outside that
 * hierarchy would turn each of them into a fatal.
 */
final class ElementNotFound extends InvalidArgumentException
{
    public static function named(string $name, string $in): self
    {
        return new self(sprintf('No element by the name of [%s] found in %s', $name, $in));
    }
}
