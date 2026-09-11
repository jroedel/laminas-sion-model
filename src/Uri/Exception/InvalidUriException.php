<?php

declare(strict_types=1);

namespace SionModel\Uri\Exception;

use InvalidArgumentException;

/** A URI that cannot be rendered back into a string. */
final class InvalidUriException extends InvalidArgumentException
{
}
