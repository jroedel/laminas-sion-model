<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A telephone input.
 *
 * ## What it used to be
 *
 * Until the element model it extended `Laminas\Form\Element` and implemented
 * `InputProviderInterface`, handing the input filter a `StringTrim`, a `StripNewlines`, a
 * `ToNull` and `SionModel\Validator\Phone`. All four are now stated where every other rule
 * is: `SionForm::$phoneInputFilterSpec` for the seven phone fields on person and role forms,
 * and `App\Schoenstatt\Association\AssociationInputFilterSpec::phone()` for the three on an
 * association. That move happened before this class changed, and the fuzz harness'
 * `filteringSuppliedOnlyByElement` and `validationSuppliedOnlyByElement` categories are what
 * proved it: both are empty of phone fields.
 *
 * It never extended `Laminas\Form\Element\Tel`, which laminas marked `@final` — Tel
 * contributed nothing but the `type="tel"` attribute, and that attribute is declared here.
 */
class Phone extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'tel',
    ];
}
