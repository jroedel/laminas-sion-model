<?php

declare(strict_types=1);

namespace SionModel\Form\Exception;

use DomainException as BaseDomainException;

/**
 * `Laminas\Form\Exception\DomainException`: a form asked to do something in the wrong
 * order — validated with no data, or read for data before it validated.
 */
final class DomainException extends BaseDomainException
{
}
