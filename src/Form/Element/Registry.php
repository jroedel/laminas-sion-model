<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use Laminas\Form\Element as LaminasElement;
use Laminas\Form\Factory;
use Laminas\Form\FormElementManager;
use Laminas\ServiceManager\ServiceManager;

/**
 * Which class answers `'type' => 'Select'`.
 *
 * ## Why a class and not a config file
 *
 * Because three different things build elements and all three have to agree.
 *
 *  1. `FormElementManager`, configured from the merged `form_elements` key, builds every
 *     form the container hands out — and injects itself into that form's factory, so the
 *     form's own `add()` calls resolve through it too.
 *  2. A form built with `new` has no manager at all: `Laminas\Form\Fieldset::getFormFactory()`
 *     makes a `Factory` with a default `FormElementManager` the first time it is asked.
 *     {@see \SionModel\Form\Form} overrides that with {@see formFactory()}.
 *  3. `App\Schoenstatt\Association\AssociationValidator` builds a `FormElementManager` by
 *     hand, because the associations API validates with no module loading and no merged
 *     config at all.
 *
 * Three copies of the same list is three chances for one path to keep building laminas
 * elements while the other two do not — and that failure is silent: the form still works,
 * it is simply the old class. So the list lives here and the three read it.
 *
 * ## What is deliberately not listed
 *
 * `Collection`, `Fieldset` and `Form`, which belong to the form model rather than the
 * element model, and every element type no form in this application uses — Captcha, Color,
 * DateTime, Image, Month, MultiCheckbox, Password, Radio, Range, Search, Tel, Time, Week.
 * Those keep resolving to laminas' own classes. A form that starts using one gets a laminas
 * element and `test/Integration/ElementSurfaceTest` says so by name on the next run, which
 * is the loud answer; aliasing them to something that throws would be a louder one for no
 * extra safety, since a new element type needs a decision either way.
 */
final class Registry
{
    /**
     * laminas' class => ours, for every element type this application actually uses.
     *
     * @var array<class-string, class-string>
     */
    public const REPLACEMENTS = [
        LaminasElement::class            => Element::class,
        LaminasElement\Button::class     => Button::class,
        LaminasElement\Checkbox::class   => Checkbox::class,
        LaminasElement\Csrf::class       => Csrf::class,
        LaminasElement\Date::class       => Date::class,
        LaminasElement\DateSelect::class => DateSelect::class,
        LaminasElement\Email::class      => Email::class,
        LaminasElement\File::class       => File::class,
        LaminasElement\Hidden::class     => Hidden::class,
        LaminasElement\Number::class     => Number::class,
        LaminasElement\Select::class     => Select::class,
        LaminasElement\Submit::class     => Submit::class,
        LaminasElement\Text::class       => Text::class,
        LaminasElement\Textarea::class   => Textarea::class,
        LaminasElement\Url::class        => Url::class,
    ];

    /**
     * The short names a form may write, in every spelling `FormElementManager` accepts.
     *
     * Both cases are listed because both appear in this application's forms — `'Text'` 73
     * times and `'text'` five, `'csrf'` in lowercase only — and a spelling that fell through
     * would build a laminas element beside fourteen of ours with nothing to say so.
     *
     * @var array<string, class-string>
     */
    private const SHORT_NAMES = [
        'button'     => Button::class,
        'Button'     => Button::class,
        'checkbox'   => Checkbox::class,
        'Checkbox'   => Checkbox::class,
        'csrf'       => Csrf::class,
        'Csrf'       => Csrf::class,
        'date'       => Date::class,
        'Date'       => Date::class,
        'dateselect' => DateSelect::class,
        'dateSelect' => DateSelect::class,
        'DateSelect' => DateSelect::class,
        'element'    => Element::class,
        'Element'    => Element::class,
        'email'      => Email::class,
        'Email'      => Email::class,
        'file'       => File::class,
        'File'       => File::class,
        'hidden'     => Hidden::class,
        'Hidden'     => Hidden::class,
        'number'     => Number::class,
        'Number'     => Number::class,
        'phone'      => Phone::class,
        'Phone'      => Phone::class,
        'select'     => Select::class,
        'Select'     => Select::class,
        'submit'     => Submit::class,
        'Submit'     => Submit::class,
        'text'       => Text::class,
        'Text'       => Text::class,
        'textarea'   => Textarea::class,
        'Textarea'   => Textarea::class,
        'url'        => Url::class,
        'Url'        => Url::class,
    ];

    /**
     * The `form_elements` configuration: every name that should build one of ours, and the
     * classes themselves as invokables.
     *
     * The aliases override `FormElementManager`'s own — the manager applies configuration
     * after its class defaults, so `'Select'` stops meaning `Laminas\Form\Element\Select`
     * the moment this is merged in.
     *
     * @return array{aliases: array<string, class-string>, invokables: array<class-string, class-string>}
     */
    public static function config(): array
    {
        $invokables = [];
        foreach (self::SHORT_NAMES as $class) {
            $invokables[$class] = $class;
        }

        return [
            //A form naming a laminas element by class gets ours too. None does today; one
            //written from an old example would otherwise slip a laminas element in.
            'aliases'    => self::REPLACEMENTS + self::SHORT_NAMES,
            'invokables' => $invokables,
        ];
    }

    /**
     * A form factory for a form nobody built through the container.
     *
     * Not memoised: `Factory` holds the element manager and a form is free to reconfigure
     * its own, so handing the same instance to every form would let one form's change reach
     * the others. Building it costs an empty `ServiceManager`.
     */
    public static function formFactory(): Factory
    {
        return new Factory(new FormElementManager(new ServiceManager(), self::config()));
    }
}
