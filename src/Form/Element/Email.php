<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * An email input. The `multiple` attribute is what makes one of these accept a list, and
 * `SionModel\Form\InputTypeRules::email()` reads that attribute to decide between a plain
 * address rule and an `Explode` over a comma-separated one.
 */
class Email extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'email',
    ];
}
