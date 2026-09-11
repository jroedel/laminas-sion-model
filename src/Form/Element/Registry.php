<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use SionModel\Form\Collection;
use SionModel\Form\ElementInterface;
use SionModel\Form\Factory;
use SionModel\Form\Fieldset;
use SionModel\Form\Form;

use function class_exists;
use function is_subclass_of;
use function ltrim;
use function strtolower;

/**
 * Which class answers `'type' => 'Select'`.
 *
 * ## Why a class and not a config file
 *
 * Because three different things build elements and all three have to agree: a form's own
 * `add()` calls through {@see Factory}, the container's form services, and
 * `App\Schoenstatt\Association\AssociationValidator`, which validates an API payload with
 * no module loading and no merged config at all. Three copies of the same list is three
 * chances for one path to build something different from the other two — and that failure
 * is silent, because the form still works.
 *
 * It was a `FormElementManager` configuration until the form model landed. A plugin
 * manager brought a `ServiceManager`, a canonicalisation scheme and laminas' own aliases
 * underneath ours, to do a lookup in an array.
 *
 * ## What a `type` may be
 *
 * A short name in any case — 74 forms write `'Text'` and five write `'text'` — or a class,
 * which is how a form names a fieldset of its own (`Books\Form\MassCheckoutFieldset`) or
 * an element by class (`SionModel\Form\Element\Csrf`). A name that is neither is refused
 * by {@see Factory}, rather than quietly building a plain element that would render as an
 * empty text box.
 */
final class Registry
{
    /**
     * Every short name, lower-cased. The lookup lower-cases too, so one entry serves both
     * spellings a form might write.
     *
     * `Collection`, `Fieldset` and `Form` are here and were not in the laminas version:
     * they were the form model, which belonged to laminas then and does not now.
     *
     * @var array<string, class-string<ElementInterface>>
     */
    private const TYPES = [
        'button'     => Button::class,
        'checkbox'   => Checkbox::class,
        'collection' => Collection::class,
        'csrf'       => Csrf::class,
        'date'       => Date::class,
        'dateselect' => DateSelect::class,
        'element'    => Element::class,
        'email'      => Email::class,
        'fieldset'   => Fieldset::class,
        'file'       => File::class,
        'form'       => Form::class,
        'hidden'     => Hidden::class,
        'number'     => Number::class,
        'phone'      => Phone::class,
        'select'     => Select::class,
        'submit'     => Submit::class,
        'text'       => Text::class,
        'textarea'   => Textarea::class,
        'url'        => Url::class,
    ];

    /**
     * The class behind a type, or null when there is none.
     *
     * A class is tried first and exactly as written: `strtolower()` would not find
     * `Books\Form\MassCheckoutFieldset`, and a short name cannot collide with a class name
     * because none of them contains a backslash.
     *
     * @return class-string<ElementInterface>|null
     */
    public static function classFor(string $type): ?string
    {
        $type = ltrim($type, '\\');

        if (class_exists($type) && is_subclass_of($type, ElementInterface::class)) {
            /** @var class-string<ElementInterface> */
            return $type;
        }

        return self::TYPES[strtolower($type)] ?? null;
    }

    /**
     * Every short name this registry answers, for a test that wants to walk them.
     *
     * @return array<string, class-string<ElementInterface>>
     */
    public static function types(): array
    {
        return self::TYPES;
    }
}
