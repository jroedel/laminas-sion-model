<?php

declare(strict_types=1);

namespace SionModel\Validator\Exception;

use RuntimeException as SplRuntimeException;

/** A validator was asked to judge before it had what it needs. */
final class RuntimeException extends SplRuntimeException
{
}
