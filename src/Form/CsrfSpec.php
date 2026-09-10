<?php

declare(strict_types=1);

namespace SionModel\Form;

use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Csrf;
use Laminas\Form\ElementInterface;
use Laminas\Validator\Csrf as CsrfValidator;

use function array_merge;

/**
 * The `Csrf` validator a `security` element implies, for a form specification to restate.
 *
 * ## Why a form has to restate it at all
 *
 * `Laminas\Form\Element\Csrf` supplies its own validator through
 * `InputProviderInterface::getInputSpecification()`, so every form here is CSRF-checked
 * today without a single specification mentioning it. Measured 2026-09-10: **31 forms in
 * that state, and zero specifications naming `security`.**
 *
 * That is fine while `Laminas\InputFilter` assembles the filter, and fatal the moment
 * {@see Validation\InputFilter} does. The replacement engine reads the specification and
 * nothing else — deliberately, because a specification is a value you can read, and the
 * silent invention of whichever half is missing is exactly what step 5 removes. So each of
 * those 31 forms has to say `'security' => ['validators' => CsrfSpec::validators(...)]`
 * before the engine can take over, or the cutover removes CSRF protection from the site.
 *
 * ## Why it takes the element rather than returning a constant
 *
 * Because a constant would have been wrong, and silently. `Csrf::getCsrfValidator()` builds
 * its validator as
 *
 *     array_merge(['name' => $this->getName()], $this->getCsrfValidatorOptions())
 *
 * and that **`name` is the session container key**, not a label. A specification-side
 * `['name' => CsrfValidator::class]` with no options gets the validator's own default,
 * `csrf`, which reads a different container from the `security` one the element wrote the
 * token into — so the chain becomes `Csrf(security), Csrf(csrf)` and **a valid token is
 * rejected**. Measured before this class was written: every form on the site would have
 * started refusing every submission.
 *
 * The options also differ per form. `SionForm` and four others pass
 * `csrf_options => ['timeout' => 900]`; the rest pass nothing and get laminas' 300. Reading
 * the element is what makes one helper right for both.
 *
 * ## Why plain data and not the element's own validator instance
 *
 * Handing back `$element->getCsrfValidator()` would be tighter still — the same object,
 * so no chance of disagreement — and {@see Validation\InputFilter} would drop it on the
 * floor: it reads `['name' => string]` entries and rejects anything else. A specification
 * that is data all the way down is what lets the engine be simple, so the merge above is
 * reproduced here as data rather than smuggled through as an object.
 *
 * @see ChoiceDomain for the same pattern over a choice element's `InArray`
 */
final class CsrfSpec
{
    /**
     * The specification entry for a form's CSRF element.
     *
     * `required` is the element's own: `Csrf::getInputSpecification()` declares it, and a
     * missing token must fail rather than pass as an absent optional field.
     *
     * The `StringTrim` is the element's too, and it is not decoration: a token arriving with
     * a stray newline — a hand-written client, a copy-paste — is a token that matches after
     * trimming and not before. Losing it would make a form intermittently refuse a valid
     * submission, which is the worst failure shape there is.
     *
     * @return array{required: bool, filters: list<array{name: class-string}>, validators: list<array{name: class-string, options: array<string, mixed>}>}
     */
    public static function forElement(ElementInterface $element): array
    {
        return [
            'required'   => true,
            'filters'    => [['name' => StringTrim::class]],
            'validators' => self::validators($element),
        ];
    }

    /**
     * Just the validators, for a form that has something of its own to add.
     *
     * @return list<array{name: class-string, options: array<string, mixed>}>
     */
    public static function validators(ElementInterface $element): array
    {
        $options = $element instanceof Csrf ? $element->getCsrfValidatorOptions() : [];

        return [
            [
                'name'    => CsrfValidator::class,
                //`name` first so a form that really did set its own container key in
                //csrf_options keeps it — the order laminas merges in.
                'options' => array_merge(['name' => (string) $element->getName()], $options),
            ],
        ];
    }
}
