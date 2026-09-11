<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A URL input. Fourteen of them, all contact links on an association or a person.
 *
 * ## `uriHandler` and `allowRelative` are inert, and always were
 *
 * Fourteen of these carry `'options' => ['uriHandler' => SionModel\Uri\Http::class,
 * 'allowRelative' => false]`, which reads as configuration for the URI validator and never
 * was: `Laminas\Form\Element\Url` built its validator with `allowAbsolute => true,
 * allowRelative => false` hard-coded and never looked at the element's options. They are
 * kept here as the plain options they have always been — removing them would change what
 * `test/Element/element-surface.php` records for no gain — and the values they claim are
 * the values `SionModel\Form\InputTypeRules::url()` actually applies, so nothing about the
 * validation changes either way.
 */
class Url extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'url',
    ];
}
