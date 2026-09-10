<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A hidden input. Only the type attribute distinguishes it, and `BootstrapFormRenderer`
 * branches on that attribute rather than on this class.
 */
class Hidden extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'hidden',
    ];
}
