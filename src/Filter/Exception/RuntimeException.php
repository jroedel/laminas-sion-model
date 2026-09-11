<?php

declare(strict_types=1);

namespace SionModel\Filter\Exception;

use RuntimeException as SplRuntimeException;

/** A filter was handed a value it cannot possibly filter. */
final class RuntimeException extends SplRuntimeException
{
}
