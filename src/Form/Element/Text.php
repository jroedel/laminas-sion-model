<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A single-line text input. 73 of them, and the class carries nothing but its type
 * attribute: everything that made a laminas `Text` more than a bare element was its input
 * specification, and validation is the form specification's business now.
 */
class Text extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'text',
    ];
}
