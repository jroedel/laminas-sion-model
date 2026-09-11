<?php

declare(strict_types=1);

namespace SionModel\Filter\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

/**
 * A filter was given an option it does not have.
 *
 * Extends SPL's rather than declaring a marker interface of its own: nothing in four
 * repositories catches `Laminas\Filter\Exception\ExceptionInterface`, and a hierarchy
 * nobody catches is a hierarchy nobody needs.
 */
final class InvalidArgumentException extends SplInvalidArgumentException
{
}
