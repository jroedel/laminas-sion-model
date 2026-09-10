<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A button that submits nothing by itself. All eight are Bootstrap toggles, carrying
 * `data-toggle` and `data-target` attributes and no behaviour of their own.
 */
class Button extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'button',
    ];
}
