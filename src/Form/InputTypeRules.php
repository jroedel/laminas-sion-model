<?php

declare(strict_types=1);

namespace SionModel\Form;

use Laminas\Filter\DateSelect as DateSelectFilter;
use Laminas\Filter\StringTrim;
use SionModel\Form\Element\AbstractDateTime;
use SionModel\Form\Element\Csrf;
use SionModel\Form\Element\DateSelect;
use SionModel\Form\Element\Email;
use SionModel\Form\Element\Number;
use SionModel\Form\Element\Url;
use SionModel\Form\ElementInterface;
use Laminas\Validator\Date as DateValidator;
use Laminas\Validator\Explode;
use Laminas\Validator\GreaterThan;
use Laminas\Validator\LessThan;
use Laminas\Validator\Regex;
use Laminas\Validator\Step;
use Laminas\Validator\Uri;

use function is_string;

/**
 * The validators an HTML input type implies, for a form specification to restate.
 *
 * The fourth member of the family — {@see ChoiceDomain} for a select's option list,
 * {@see CheckboxDomain} for a checkbox's two values, {@see CsrfSpec} for the token — and
 * the last one step 5 needs: with these, no field in the application is validated by
 * something its specification does not name.
 *
 * ## Why this is written out rather than delegated
 *
 * The obvious implementation is one line: return `$element->getInputSpecification()`'s
 * validators, which is exactly what laminas merges in and therefore cannot be wrong. It
 * is also a dead end. `getInputSpecification()` is laminas-form, and laminas-form is what
 * step 5 removes — so a helper built on it would have to be replaced by this one anyway,
 * after the specifications had already been written against it.
 *
 * It would not even work in the meantime: that method returns validator *objects*, and
 * {@see Validation\InputFilter} reads `['name' => …, 'options' => …]` entries and throws
 * on anything else, deliberately, so that a specification stays a value you can read.
 *
 * So each derivation below is laminas', reproduced from its source and cited, with the
 * options as plain scalars.
 *
 * ## What is deliberately absent: the step validators
 *
 * `SionModel\Form\Element\Number` adds a `Step` validator unless `step="any"`, and
 * `AbstractDateTime` adds a `DateStep` one on the same condition. `Step` is here.
 * `DateStep` is not, and cannot be: it is configured with `DateTimeZone` and
 * `DateInterval` **objects**, which a plain-data specification cannot hold.
 *
 * That costs nothing today and the check is explicit rather than assumed: all nine `Date`
 * elements in the application set `step="any"`, so laminas builds no `DateStep` for any of
 * them, and `DateSelect` contributes only its date validator. A future date field that
 * sets a step would be a real gap — `test/Fuzz/known-form-gaps.php` would report it, and
 * `test/Integration/EngineMatchesAssembledFilterTest` would disagree on it — which is the
 * point of leaving the hole visible rather than papering it with an object.
 */
final class InputTypeRules
{
    /**
     * `Number`: a numeric pattern, the min/max bounds, and the step.
     *
     * @see \Laminas\Form\Element\Number::getValidators() — the source reproduced here
     * @return list<array<string, mixed>>
     */
    public static function number(ElementInterface $element): array
    {
        if (! $element instanceof Number) {
            return [];
        }

        $attributes = $element->getAttributes();
        //laminas defaults this to true and overrides it only when the attribute is
        //present at all — `inclusive => false` is honoured, `inclusive` absent is true.
        $inclusive = $attributes['inclusive'] ?? true;

        //"HTML5 always transmits values in the format 1000.01, without a thousand
        //separator" — laminas' own comment, and the reason this is a Regex rather than
        //an i18n float validator.
        $rules = [['name' => Regex::class, 'options' => ['pattern' => '(^-?\d*(\.\d+)?$)']]];

        if (isset($attributes['min'])) {
            $rules[] = [
                'name'    => GreaterThan::class,
                'options' => ['min' => $attributes['min'], 'inclusive' => $inclusive],
            ];
        }
        if (isset($attributes['max'])) {
            $rules[] = [
                'name'    => LessThan::class,
                'options' => ['max' => $attributes['max'], 'inclusive' => $inclusive],
            ];
        }
        if (! isset($attributes['step']) || 'any' !== $attributes['step']) {
            $rules[] = [
                'name'    => Step::class,
                'options' => [
                    'baseValue' => $attributes['min'] ?? 0,
                    'step'      => $attributes['step'] ?? 1,
                ],
            ];
        }

        return $rules;
    }

    /**
     * `Date`, `DateSelect` and the rest of the date family: the format, and the bounds.
     *
     * laminas throws from the element when `min` or `max` is set and does not parse in
     * the element's own format; that check belongs to the element and is not repeated
     * here, because a specification that threw while being built would take the page down
     * rather than report a field.
     *
     * @see \Laminas\Form\Element\AbstractDateTime::getValidators() — the source reproduced here
     * @return list<array<string, mixed>>
     */
    public static function date(ElementInterface $element): array
    {
        //`DateSelect` is not an AbstractDateTime: it carries no min/max, and its validator
        //is built with a **literal** 'Y-m-d' rather than through getFormat() — laminas'
        //`DateSelect extends MonthSelect` did the same. Reproduced separately rather than
        //folded into the branch below on the assumption the two agree. MonthSelect and the
        //other pickers are deliberately not handled: no form uses one, and a silent wrong
        //format is worse than a reported gap.
        if ($element instanceof DateSelect) {
            return [['name' => DateValidator::class, 'options' => ['format' => 'Y-m-d']]];
        }

        if (! $element instanceof AbstractDateTime) {
            return [];
        }

        $attributes = $element->getAttributes();
        $rules      = [['name' => DateValidator::class, 'options' => ['format' => $element->getFormat()]]];

        //Always inclusive for dates, unlike Number: laminas hardcodes it here.
        if (isset($attributes['min']) && is_string($attributes['min'])) {
            $rules[] = [
                'name'    => GreaterThan::class,
                'options' => ['min' => $attributes['min'], 'inclusive' => true],
            ];
        }
        if (isset($attributes['max']) && is_string($attributes['max'])) {
            $rules[] = [
                'name'    => LessThan::class,
                'options' => ['max' => $attributes['max'], 'inclusive' => true],
            ];
        }

        return $rules;
    }

    /**
     * `Url`: an absolute URI and nothing relative.
     *
     * @see \Laminas\Form\Element\Url::getValidator() — the source reproduced here
     * @return list<array<string, mixed>>
     */
    public static function url(ElementInterface $element): array
    {
        if (! $element instanceof Url) {
            return [];
        }

        return [[
            'name'    => Uri::class,
            'options' => ['allowAbsolute' => true, 'allowRelative' => false],
        ]];
    }

    /**
     * `Email`: laminas' own pattern, wrapped in `Explode` for a multiple field.
     *
     * Not `EmailAddress`. laminas uses a regex here on purpose — its docblock says it is
     * "to match that of the HTML5 specification" — and it is looser than a real mailbox
     * check. Several of these fields declare an `EmailAddress` of their own in the
     * specification already; this restates the element's regex beside it, because both
     * run today and the contract is that the specification says what already happens.
     *
     * @see \SionModel\Form\Element\Email::getEmailValidator()
     * @return list<array<string, mixed>>
     */
    public static function email(ElementInterface $element): array
    {
        if (! $element instanceof Email) {
            return [];
        }

        $pattern = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/';
        $multiple = $element->getAttributes()['multiple'] ?? null;

        if (true === $multiple || 'multiple' === $multiple) {
            return [[
                'name'    => Explode::class,
                'options' => ['validator' => new Regex($pattern)],
            ]];
        }

        return [['name' => Regex::class, 'options' => ['pattern' => $pattern]]];
    }

    /**
     * The **filters** an element type supplies, which are as easy to lose as its validators
     * and rather harder to notice.
     *
     * ## Why this exists separately, and what it cost to find
     *
     * `test/Fuzz/FormGapCollector` compared validator sets for months and never compared
     * filters, so `validationSuppliedOnlyByElement` read 3 while **74 fields** were being
     * filtered by something no specification named. The rest of step 5 was built on that
     * number.
     *
     * It surfaced when the engine was first cut over and the smoke suite refused to create
     * a person: `Schoenstatt\Form\PersonForm::nameDay` is a `DateSelect`, which posts
     * `['year' => …, 'month' => …, 'day' => …]` from its three `<select>`s, and
     * `Laminas\Form\Element\DateSelect::getInputSpecification()` supplied the
     * `Laminas\Filter\DateSelect` that turns that array into `Y-m-d`. Without it the array
     * reaches `Laminas\Validator\Date` unchanged and every person save fails on a field
     * nobody touched.
     *
     * The other 73 are `StringTrim`, and they are not cosmetic either: a `ToNull` after a
     * `StringTrim` turns `'   '` into `null`, and the same `ToNull` without it stores three
     * spaces. The ordering below matters for the same reason — laminas merged the element's
     * filters *before* the specification's, so these are spread first. Since the element
     * swap no element supplies any of it and these helpers are the only source, which makes
     * the ordering a convention rather than a constraint.
     *
     * @return list<array<string, mixed>> filter specifications, or none
     */
    public static function filters(ElementInterface $element): array
    {
        //A `MonthSelect` branch stood second here, because laminas' `DateSelect` extended
        //`MonthSelect` and the two filters differ. The element model has neither the
        //inheritance nor a MonthSelect — no form in the application uses one — so the
        //ordering no longer carries any weight and the branch would never be reached.
        if ($element instanceof DateSelect) {
            return [['name' => DateSelectFilter::class]];
        }

        //`Csrf`, `Url`, `Email`, `Number` and `AbstractDateTime` each declare exactly
        //`['name' => StringTrim::class]` and nothing else — read from their
        //getInputSpecification(), not assumed from a pattern.
        if (
            $element instanceof Csrf
            || $element instanceof Url
            || $element instanceof Email
            || $element instanceof Number
            || $element instanceof AbstractDateTime
        ) {
            return [['name' => StringTrim::class]];
        }

        return [];
    }

    /**
     * Whichever of the four applies to this element, or none.
     *
     * For a caller that has a field and does not want to know which kind it is. Each
     * branch still declines an element it does not recognise, so the result is empty
     * rather than wrong.
     *
     * @return list<array<string, mixed>>
     */
    public static function forElement(ElementInterface $element): array
    {
        return [
            ...self::number($element),
            ...self::date($element),
            ...self::url($element),
            ...self::email($element),
        ];
    }
}
