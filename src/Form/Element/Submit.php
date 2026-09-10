<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A submit button. Its caption is written as a `value` attribute, which
 * {@see Element::setAttribute()} diverts to `setValue()` — so the renderer reads
 * `getValue()` for the caption and never finds `value` among the attributes.
 */
class Submit extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'submit',
    ];
}
