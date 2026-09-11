<?php

declare(strict_types=1);

namespace SionModel\Form;

use SionModel\Form\Element\Checkbox;
use SionModel\Form\ElementInterface;
use Laminas\Validator\InArray;

/**
 * The `InArray` a checkbox's two values imply, for a form specification to restate.
 *
 * The sibling of {@see ChoiceDomain}, and separate from it for a reason: that class is
 * about **value options** and the `disable_inarray_validator` flag, and a
 * `SionModel\Form\Element\Checkbox` has neither. Its domain is the pair it was built with,
 * so the derivation is different even though the resulting validator is the same shape.
 *
 * ## Why restate it
 *
 * `Laminas\Form\Element\Checkbox::getInputSpecification()` supplied this validator itself,
 * so 42 checkboxes in this application were constrained without a single specification
 * mentioning it. `SionModel\Form\Validation\InputFilter` reads the specification and nothing
 * else, and `SionModel\Form\Element\Checkbox` supplies no input specification at all, so
 * every one of those checks would simply have stopped happening. This class is where they
 * are written down instead; `test/Fuzz/known-form-gaps.php` measures that none is left on
 * an element, under `validationSuppliedOnlyByElement`.
 *
 * ## What the constraint is actually worth
 *
 * Not much on a field that already carries `SionModel\Filter\ToBit`, which normalises
 * anything to `0` or `1` before a validator sees it. It is worth a great deal on one that
 * does not: the columns behind these are `tinyint`, and without the check an arbitrary
 * string reaches the write. Rather than decide field by field which is which — a judgement
 * that would have to be right 42 times and would be invisible when wrong — every checkbox
 * restates what it already enforces, and the fuzz harness keeps measuring the result.
 *
 * ## `strict` is laminas' `false`, not ChoiceDomain's safer mode
 *
 * Deliberately. `Laminas\Form\Element\Checkbox::getValidator()` passed `'strict' => false`,
 * and this migration's contract is that a specification produces the verdicts the element
 * produced. The
 * difference is theoretical under PHP 8 — the string-to-int comparison change means
 * `'1abc' == 1` is already false — so tightening it would buy nothing here and would make
 * a behaviour change look like a port. If it is ever wanted, it is its own decision.
 *
 * @see ChoiceDomain for selects, radios and multi-checkboxes
 * @see CsrfSpec for the same pattern over the CSRF element
 */
final class CheckboxDomain
{
    /**
     * @return list<array<string, mixed>> one validator specification, or none for an element
     *                                    that is not a checkbox
     */
    public static function validators(ElementInterface $element): array
    {
        //Under laminas this also had to exclude `MultiCheckbox`, which extended `Checkbox`
        //— an instanceof that caught every radio and multi-checkbox and would have handed
        //each one a haystack of two values instead of its option list, refusing every real
        //selection. The element model has no MultiCheckbox: the census found none in any
        //form, so `SionModel\Form\Element\Checkbox` has no subclass and the exclusion has
        //nothing left to exclude. Choice fields belong to ChoiceDomain either way.
        if (! $element instanceof Checkbox) {
            return [];
        }

        return [
            [
                'name'    => InArray::class,
                'options' => [
                    'haystack' => [$element->getCheckedValue(), $element->getUncheckedValue()],
                    'strict'   => false,
                ],
            ],
        ];
    }
}
