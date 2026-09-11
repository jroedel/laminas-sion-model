<?php

declare(strict_types=1);

namespace SionModel\Form\Validation;

use SionModel\Form\Collection;
use SionModel\Form\Fieldset;
use SionModel\Form\InputFilterProviderInterface;
use LogicException;

use function array_key_exists;
use function sprintf;

/**
 * The whole of a form's validation, as plain data.
 *
 * ## What this replaces
 *
 * `Laminas\Form\Form::getInputFilter()` builds the filter the application validates with,
 * and it builds it from two places at once: an input per **element**, out of that
 * element's own `getInputSpecification()`, and an input per key of
 * `getInputFilterSpecification()`, merged on top. `Form::attachInputFilterDefaults()` is
 * where that happens, and it is 140 lines of it.
 *
 * The element half is what step 5 removes, and the reason is not tidiness: a check that
 * exists only because an object was constructed a certain way is a check nobody can read.
 * Measured 2026-09-10, before that work started: **120 fields across the application were
 * validated only by their element**, 31 of them by `Csrf`, and no test in the repository
 * could see it, because every parity harness fed both sides the same specification.
 *
 * So this class assembles the same *shape* laminas assembles, from the specifications
 * alone. What comes out is a value: nested arrays of names, booleans and class names, with
 * no objects and no behaviour, which {@see InputFilter} then runs.
 *
 * ## The three entry shapes
 *
 * ```
 * 'title'    => ['required' => true, 'filters' => [...], 'validators' => [...]]  // an input
 * 'map'      => ['fieldset'   => [ ...the same, recursively... ]]                // a fieldset
 * 'checkout' => ['collection' => [ ...the same, recursively... ]]                // a collection
 * ```
 *
 * `fieldset` and `collection` are reserved keys. Nothing else can collide with them: an
 * input rule's vocabulary is exactly `required`, `allow_empty`, `continue_if_empty`,
 * `break_on_failure`, `filters` and `validators`, and `FormValidationContractTest` walks
 * every specification in the application to keep it that way.
 *
 * ## Why an element with no specification entry becomes `required => false`
 *
 * Because that is what laminas does with it — `attachInputFilterDefaults()` gives any
 * element that is not an `InputProviderInterface` exactly `['name' => $name, 'required' =>
 * false]` — and because the alternative is worse than it looks. Dropping such an element
 * would drop its key from `getData()`, and `getData()` is what controllers hand to
 * `SionTable::updateEntity()`.
 *
 * The 42 elements in this state are all `Submit` and `Button`, which is checked rather
 * than assumed: `FormGapCollector`'s `elementsMissingFromSpec` reports any *data* element
 * that is not named in a specification, and that category is empty. If it stops being
 * empty, this rule silently starts un-validating a real field — which is why the category
 * is asserted against the baseline.
 *
 * ## Key order matches laminas, deliberately
 *
 * Elements first in the order they were added, then specification keys naming no element,
 * then fieldsets. That is `attachInputFilterDefaults()`'s order — elements are added to an
 * empty filter, the specification loop merges into an existing name or appends, fieldsets
 * come last — and reproducing it means `getValues()` can be compared to laminas' with a
 * strict `===` rather than a sort-both-sides comparison that would hide a missing key.
 */
final class FormSpecification
{
    /**
     * @return array<string, mixed> the assembled specification, nested as the form is
     */
    public static function of(Fieldset $fieldset): array
    {
        $declared = $fieldset instanceof InputFilterProviderInterface
            ? $fieldset->getInputFilterSpecification()
            : [];

        $assembled = [];

        //Elements first, in the order they were added.
        foreach ($fieldset->getElements() as $name => $element) {
            $name             = (string) $name;
            $assembled[$name] = array_key_exists($name, $declared)
                ? $declared[$name]
                : ['required' => false];
        }

        //Then anything the specification names that is not an element. There is none
        //today — `specKeysWithoutElement` is empty and `FormWriteSurfaceTest` explains why
        //a key naming no element is a column the form writes and no visitor can see — but
        //laminas would build an input for it, so leaving it out here would be a silent
        //difference rather than a deliberate one.
        foreach ($declared as $name => $rules) {
            $name = (string) $name;
            if (! array_key_exists($name, $assembled)) {
                $assembled[$name] = $rules;
            }
        }

        //Fieldsets last.
        foreach ($fieldset->getFieldsets() as $name => $child) {
            $name = (string) $name;

            if (array_key_exists($name, $assembled)) {
                throw new LogicException(sprintf(
                    'The specification of %s names %s, which is also a fieldset on it. laminas '
                    . 'would keep whichever half it reached first; this engine will not guess.',
                    $fieldset::class,
                    $name
                ));
            }

            $assembled[$name] = $child instanceof Collection
                ? ['collection' => self::ofCollectionTarget($child)]
                : ['fieldset' => self::of($child)];
        }

        return $assembled;
    }

    /**
     * @return array<string, mixed> the specification one row of a collection is validated by
     */
    private static function ofCollectionTarget(Collection $collection): array
    {
        $target = $collection->getTargetElement();

        //`Laminas\Form\Element\Collection` also accepts a plain element as its target, in
        //which case laminas validates each row as a single value rather than as a row of
        //named fields. Nothing here does that, and guessing which of the two shapes was
        //meant is exactly the invention this layer removes — so it is an error, loudly,
        //rather than a collection that quietly validates nothing.
        if (! $target instanceof Fieldset) {
            throw new LogicException(sprintf(
                'The collection %s has %s as its target element. This engine assembles a '
                . 'specification per named field, so a collection over a bare element is not '
                . 'something it can describe.',
                (string) $collection->getName(),
                null === $target ? 'nothing' : $target::class
            ));
        }

        return self::of($target);
    }
}
