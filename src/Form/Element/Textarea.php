<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A multi-line text input. The `rows` attribute 33 of these carry is a plain attribute,
 * read off `getAttributes()` by the renderer like any other.
 */
class Textarea extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'textarea',
    ];
}
