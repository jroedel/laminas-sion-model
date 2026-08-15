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
use function array_values;
use function count;

/**
 * The `InArray` a choice element's own option list implies, for a form specification to restate.
 *
 * ## Why a form has to restate it at all
 *
 * A `Select`, `Radio` or `MultiCheckbox` builds its own `InArray` from its value options —
 * `Laminas\Form\Element\Select::getInputSpecification()` does it — **unless the element sets
 * `disable_inarray_validator => true`**. That option is the whole of it, and it is set on 35
 * elements in the schoenstatt.link application. Where it is set, nothing constrains the field
 * to its own list and any string at all reaches the column; this class is how a form puts the
 * check back.
 *
 * ## What this docblock said until 2026-08-15, and why it was wrong
 *
 * It said that naming an element in `getInputFilterSpecification()` throws the element's input
 * away, "replaces it rather than merging into it". `Laminas\InputFilter\BaseInputFilter::add()`
 * does the opposite, and says so in a comment:
 *
 *     // The element already exists, so merge the config. Please note
 *     // that this merges the new input into the original.
 *     $original = $this->inputs[$name];
 *     $original->merge($input);
 *
 * `Form::attachInputFilterDefaults()` adds the element's input first and the specification's
 * second, so a field named in the spec keeps its element's validators *and* gains the spec's.
 * Naming a select in a specification therefore costs it nothing.
 *
 * The consequence of believing otherwise was not a bug in this class — a redundant second
 * `InArray` over the same haystack changes no outcome — but a measurement that reported 88
 * fields as unprotected when 53 of them were already protected by their own element. Anything
 * that reasons about which validators apply should read the assembled `getInputFilter()`, not
 * the specification: the specification is half the answer, and the more attractive half.
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
 *         'validators' => ChoiceDomain::validators($this->get('spousePersonId')),
 *     ],
 *
 * It answers a **list** rather than one validator specification, and that is what lets it
 * decline. A caller that already has validators of its own spreads it,
 *
 *     'validators' => [...ChoiceDomain::validators($this->get('locale')), $somethingElse],
 *
 * and an empty list simply disappears. Returning a bare `[]` where a specification was expected
 * would instead reach `ValidatorChain` as a validator with no name.
 *
 * ## When the element's options are narrowed at request time: `$fallbackHaystack`
 *
 * Some selects hold *zero* options when the form is constructed and get them later — from a
 * controller that has just learned which library is being viewed, from an HTTP gateway, or
 * from the form's own `setData()` narrowing one field's options by another field's value.
 * Because the specification is built lazily, whatever the element holds at `isValid()` time is
 * what constrains, and for the narrowing cases that is *better* than a static list:
 * `AssignmentForm::setData()` reduces `roleId` to the roles of the submitted association
 * before validation runs, so the check is per-association rather than global.
 *
 * The problem is the request where the narrowing did not happen — a hostile `associationId`,
 * a gateway that timed out — because then the element is still empty and, by the rule below,
 * unconstrained. `$fallbackHaystack` is the answer: the widest domain that is still legitimate,
 * used only when the element itself offers nothing.
 *
 *     'roleId' => [
 *         'required'   => true,
 *         'validators' => ChoiceDomain::validators($this->get('roleId'), $this->allRoleIds()),
 *     ],
 *
 * It must be **derived from the same source the runtime population reads**, never hand-written.
 * A literal list is a second copy of a domain that already exists somewhere, and the failure
 * mode of a stale copy here is a form that refuses valid input — the exact thing this class
 * warns about below.
 *
 * ## Two things it deliberately does not do
 *
 * **No options and no fallback yields no validator.** A haystack of nothing would reject every
 * submission, which is a worse failure than the one this fixes and an easy one to ship: some
 * selects are filled from a service that simply is not reachable in every environment —
 * `ImportFatherForm::personId` comes from the patres HTTP gateway, which fails silently
 * offline — and there is no wider list to fall back to. Returning nothing means the fuzz
 * harness reports the field as unconstrained again, which is the honest signal: a domain
 * nobody can enumerate cannot be enforced.
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
     * @param list<int|string> $fallbackHaystack the widest legitimate domain, used only when the
     *                                           element itself offers no options; must be derived
     *                                           from the same source that populates it at runtime
     * @return list<array<string, mixed>> one validator specification, or none at all when the
     *                                    element has no options to constrain against
     */
    public static function validators(ElementInterface $element, array $fallbackHaystack = []): array
    {
        if (! $element instanceof Select && ! $element instanceof MultiCheckbox) {
            return [];
        }

        $haystack = array_map(
            static fn(int|string $value): string => (string) $value,
            array_keys($element->getValueOptions())
        );

        if (0 === count($haystack)) {
            $haystack = array_values(array_map(
                static fn(int|string $value): string => (string) $value,
                $fallbackHaystack
            ));
        }

        if (0 === count($haystack)) {
            return [];
        }

        //A multiple select posts an array, and InArray validates a scalar. Explode is what
        //Laminas\Form\Element\Select::getInputSpecification() wraps it in for exactly this,
        //so the element's discarded input and this replacement have the same shape.
        if ($element instanceof MultiCheckbox || $element->getAttribute('multiple')) {
            return [[
                'name'    => Explode::class,
                'options' => [
                    'validator' => new InArray([
                        'haystack' => $haystack,
                        'strict'   => InArray::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY,
                    ]),
                ],
            ]];
        }

        return [[
            'name'    => InArray::class,
            'options' => [
                'haystack' => $haystack,
                'strict'   => InArray::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY,
            ],
        ]];
    }
}
