<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A numeric input. `min`, `max`, `step` and `inclusive` are plain attributes; they are
 * also what the browser enforces and what `SionModel\Form\InputTypeRules::number()` turns
 * into `GreaterThan`, `LessThan` and `Step` rules, so the element states them once and
 * both readers agree by construction.
 */
class Number extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'number',
    ];
}
