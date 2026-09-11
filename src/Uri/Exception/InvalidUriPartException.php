<?php

declare(strict_types=1);

namespace SionModel\Uri\Exception;

use InvalidArgumentException;

/**
 * A URI carried a scheme or a host this class will not accept.
 *
 * Thrown from the **constructor**, which is where it matters: `SionModel\Db\Model\SionTable`
 * builds one of these to normalise a stored URL and does not catch, so `mailto:` in a URL
 * field is a fatal rather than a rejected value. The form path never reaches it — the `Uri`
 * validator catches this and reports `notUri` — but the API and the importers do.
 */
final class InvalidUriPartException extends InvalidArgumentException
{
    public const INVALID_SCHEME   = 1;
    public const INVALID_HOSTNAME = 2;
}
