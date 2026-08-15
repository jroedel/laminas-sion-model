<?php

declare(strict_types=1);

namespace SionModel\Form;

use Laminas\Form\Element\MultiCheckbox;
use Laminas\Form\Element\Select;
use Laminas\Form\ElementInterface;
use Laminas\Validator\Explode;
use Laminas\Validator\InArray;

use function array_keys;
use function array_map;
use function count;

/**
 * The `InArray` a choice element's own option list implies, for a form specification to restate.
 *
 * ## Why a form has to restate it at all
 *
 * A `Select`, `Radio` or `MultiCheckbox` builds its own `InArray` from its value options —
 * `Laminas\Form\Element\Select::getInputSpecification()` does it — and naming that element in a
 * form's `getInputFilterSpecification()` **throws the element's input away**. The spec entry
 * replaces it rather than merging into it, so the moment a form declares
 *
 *     'categoryId' => ['required' => false],
 *
 * the option list stops constraining anything and any string at all reaches the column. In this
 * application that had happened to **88 choice fields**, measured 2026-08-15 by the fuzz
 * harness — the pattern is easy to write and its consequence is invisible in the source.
 *
 * ## Why the haystack can come from the element
 *
 * The obvious objection is that a select's options are filled in by a *factory*, after the form
 * is constructed, so a specification written in the constructor could not know them. It does not
 * have to: `Laminas\Form\Form::getInputFilter()` calls `getInputFilterSpecification()` lazily, on
 * first use, which is `isValid()` time — long after the factory ran. Measured rather than
 * assumed: `PersonForm`'s `spousePersonId` reports all 325 of its options at spec time.
 *
 * That is what makes this a one-line change per field instead of the constructor-injected
 * domain object `AssociationForm` needed. Pass the element, not a haystack:
 *
 *     'spousePersonId' => [
 *         'required'   => false,
 *         'validators' => [ChoiceDomain::inArray($this->get('spousePersonId'))],
 *     ],
 *
 * ## Two things it deliberately does not do
 *
 * **An empty option list yields no validator.** A haystack of nothing would reject every
 * submission, which is a worse failure than the one this fixes and an easy one to ship: several
 * selects in this application are populated by JavaScript from another field's value and hold
 * *zero* options server-side — `AssignmentForm::roleId` is the example, and constraining it
 * would refuse all 170 assignments in the database. Returning nothing means the fuzz harness
 * reports the field as unconstrained again, which is the honest signal: a domain nobody can
 * enumerate cannot be enforced.
 *
 * **Comparison is not strict.** Value option keys arrive from the database as integers and a
 * browser posts strings, so `COMPARE_STRICT` would reject `'633'` against `633` — every id
 * select in the application. `COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY` is the
 * mode that compares loosely without letting `'633abc'` match, and it is what
 * `App\Schoenstatt\Association\AssociationInputFilterSpec` already uses for the same reason.
 *
 * ## Before adding this to a field, check the data fits
 *
 * A select whose stored values are already outside its own options is not protected by this —
 * it is broken by it, either refusing to save records that exist or silently rewriting them.
 * That is not hypothetical: `mus_compositions.Country` holds `pt` and `cl` against an
 * uppercase-keyed option list, and three publication selects reference ids their own lists do
 * not offer. Constrain those only after the data is corrected.
 */
final class ChoiceDomain
{
    /**
     * @return array<string, mixed> a validator specification, or [] when the element has no
     *                              options to constrain against
     */
    public static function inArray(ElementInterface $element): array
    {
        if (! $element instanceof Select && ! $element instanceof MultiCheckbox) {
            return [];
        }

        $haystack = array_map(
            static fn(int|string $value): string => (string) $value,
            array_keys($element->getValueOptions())
        );

        if (0 === count($haystack)) {
            return [];
        }

        //A multiple select posts an array, and InArray validates a scalar. Explode is what
        //Laminas\Form\Element\Select::getInputSpecification() wraps it in for exactly this,
        //so the element's discarded input and this replacement have the same shape.
        if ($element instanceof MultiCheckbox || $element->getAttribute('multiple')) {
            return [
                'name'    => Explode::class,
                'options' => [
                    'validator' => new InArray([
                        'haystack' => $haystack,
                        'strict'   => InArray::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY,
                    ]),
                ],
            ];
        }

        return [
            'name'    => InArray::class,
            'options' => [
                'haystack' => $haystack,
                'strict'   => InArray::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY,
            ],
        ];
    }
}
