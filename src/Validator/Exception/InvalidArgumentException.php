<?php

declare(strict_types=1);

namespace SionModel\Validator\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

/** A validator was configured with something it cannot use. */
final class InvalidArgumentException extends SplInvalidArgumentException
{
}
