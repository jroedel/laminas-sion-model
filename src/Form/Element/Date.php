<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A native date input. Eleven of them.
 *
 * The default format is RFC-3339's full-date, which is what an HTML5 `<input type="date">`
 * submits regardless of how the browser displays it. A form that means something else says
 * so with the `format` option.
 */
class Date extends AbstractDateTime
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'date',
    ];

    protected string $format = 'Y-m-d';
}
