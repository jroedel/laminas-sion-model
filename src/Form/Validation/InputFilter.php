<?php

declare(strict_types=1);

namespace SionModel\Form\Validation;

use InvalidArgumentException;

use function array_key_exists;
use function array_unshift;
use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;
use function str_ends_with;

/**
 * The input-filter engine, reproducing `Laminas\InputFilter\InputFilter` over the same
 * `getInputFilterSpecification()` arrays the forms already declare.
 *
 * ## Why the engine moves before the filters and validators do
 *
 * Step 5 replaces four laminas packages at once if it is attempted in one piece, across 136
 * files. It is separable because the *engine* and the *rules* are separable: this class
 * decides whether a field is required, whether an empty value is acceptable, and in what
 * order things run, while the individual filters and validators are named by class in the
 * spec and resolved through {@see $resolve}. So the engine can be replaced first and
 * parity-tested against laminas while still running laminas' own filters and validators,
 * and those can then be swapped one at a time behind an unchanging engine.
 *
 * ## The semantics, read out of the laminas source rather than assumed
 *
 * `Laminas\InputFilter\Input::isValid()` and `BaseInputFilter::validateInputs()`, in order:
 *
 *   1. a field absent from the data and **not required** is not validated — but it still
 *      appears in the values, carrying its filtered absent value. `getValues()` walks every
 *      input in the specification rather than the ones that were submitted, so the shape of
 *      the result does not depend on what the browser sent;
 *   2. a field absent from the data and **required** fails with the required message;
 *   3. an empty value (`null`, `''` or `[]`) on a **not required** field is accepted
 *      without running its validators;
 *   4. an empty value on a field that allows empty is likewise accepted;
 *   5. otherwise the validators run, and when the field neither allows empty nor continues
 *      if empty, a not-empty check is **injected in front of them** — unless the field
 *      already declares one of its own.
 *
 * ## What this engine does NOT carry, and must before it can be cut over
 *
 * It is driven by the specification alone. `Laminas\Form\Form::getInputFilter()` also
 * builds an input per **element**, from each element's own `getInputSpecification()`, and
 * merges the two. Measured 2026-09-10: **101 fields across the 36 forms are validated only
 * by that element half**, against 199 whose validators are declared in a specification —
 * a `Select`'s `InArray` over its value options, `Uri` on a `Url`,
 * `Regex`/`GreaterThan`/`LessThan`/`Step` on a `Number`, and **31 `Csrf` checks**, since no
 * form's specification names `security`: `SionModel\Form\SionForm` adds the element and
 * laminas supplies the validator from it.
 *
 * So replacing `Laminas\InputFilter` with this class today would drop all 101, every CSRF
 * check on the site included. They are enumerated in `test/Fuzz/known-form-gaps.php` under
 * `validationSuppliedOnlyByElement` and that list must reach zero first.
 *
 * `test/Integration/InputFilterEngineParityTest` cannot see any of this: it feeds laminas'
 * `Factory` and this engine the *same* specification, so both sides start where that list
 * ends. It proves the two agree given a specification. It proves nothing about what the
 * assembled filter contains.
 *
 * ## The semantics, continued
 *
 * That injection is the rule most easily missed, because it does not depend on `required`.
 * `Schoenstatt\Form\SearchForm` is the case that proves it: `showPhotos` is
 * `required => false` with a `Boolean` filter, so an empty submission filters to `false` —
 * which is **not** empty by the narrow definition above (`null`, `''`, `[]` only). The
 * field therefore reaches the validators, the injected not-empty check rejects `false`, and
 * an optional checkbox reports "Value is required and can't be empty". Reproducing that is
 * not endorsing it; it is what the application does today.
 *
 * **`required => false` does not imply `allow_empty`.** `Input` defaults `required` to true
 * and `allowEmpty` to false, and `Factory` infers `required` from `allow_empty` but never
 * the reverse — so a `required => false` field is accepted when empty by rule 3, not
 * because empty was allowed. The distinction matters the moment a field becomes required:
 * it starts rejecting empty without anything else changing.
 *
 * Filters always run before validators, and run whether or not the value is empty — the
 * filtered value is what both the validators and the caller see.
 */
final class InputFilter
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, array<string, string>> */
    private array $messages = [];

    /**
     * @param array<string, mixed> $spec the form's getInputFilterSpecification()
     * @param callable(string, array<string, mixed>): object $makeFilter
     * @param callable(string, array<string, mixed>): object $makeValidator
     *
     * Two resolvers rather than one, because a specification names filters and validators
     * the same way — by a **short name** as often as by a class (`'StripTags'`, `'ToInt'`)
     * — and only the position in the spec says which kind is meant. laminas resolves those
     * through two separate plugin managers for the same reason.
     *
     * They are injected rather than hardcoded so that the rules can be swapped one at a
     * time behind an engine that is already proven: today they resolve to laminas' own
     * filters and validators, and each can be replaced without this class changing.
     */
    public function __construct(
        private readonly array $spec,
        private readonly mixed $makeFilter,
        private readonly mixed $makeValidator
    ) {
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data     = $data;
        $this->values   = [];
        $this->messages = [];
    }

    public function isValid(): bool
    {
        $this->values   = [];
        $this->messages = [];
        $valid          = true;

        foreach ($this->spec as $name => $rules) {
            if (! is_string($name) || ! is_array($rules)) {
                continue;
            }

            $required = (bool) ($rules['required'] ?? true);
            $hasValue = array_key_exists($name, $this->data);

            //Every field in the specification carries a value, submitted or not — that is
            //what BaseInputFilter::getValues() does, and a caller reading $values['x'] for
            //an unsubmitted optional field would otherwise meet an undefined key where
            //laminas gave it null. The filters run either way, so an absent value is
            //whatever the chain makes of null.
            $value               = $this->filter($hasValue ? $this->data[$name] : null, $rules);
            $this->values[$name] = $value;

            //(1) absent and optional: in the values, never validated
            if (! $hasValue && ! $required) {
                continue;
            }

            //(2) absent and required
            if (! $hasValue) {
                $this->messages[$name] = ['isEmpty' => 'Value is required and can\'t be empty'];
                $valid                 = false;
                continue;
            }

            $empty           = null === $value || '' === $value || [] === $value;
            $allowEmpty      = (bool) ($rules['allow_empty'] ?? false);
            $continueIfEmpty = (bool) ($rules['continue_if_empty'] ?? false);

            //(3) and (4)
            if ($empty && ! $continueIfEmpty && (! $required || $allowEmpty)) {
                continue;
            }

            //(5)
            $failures = $this->validate($value, $rules, ! $allowEmpty && ! $continueIfEmpty);
            if ([] !== $failures) {
                $this->messages[$name] = $failures;
                $valid                 = false;
            }
        }

        return $valid;
    }

    /** @return array<string, mixed> the filtered value of every field in the specification */
    public function getValues(): array
    {
        return $this->values;
    }

    /** @return array<string, array<string, string>> field name => message key => message */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /** @param array<string, mixed> $rules */
    private function filter(mixed $value, array $rules): mixed
    {
        foreach ($this->entries($rules, 'filters') as [$name, $options]) {
            /** @var object $filter */
            $filter = ($this->makeFilter)($name, $options);
            /** @psalm-suppress MixedMethodCall */
            $value = $filter->filter($value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $rules
     * @param bool $injectNotEmpty laminas prepends a NotEmpty when the field neither allows
     *        empty nor continues if empty, and skips it when the field declares its own —
     *        `Input::injectNotEmptyValidator()`. Prepending matters: it is what makes
     *        `isEmpty` the reported failure rather than whatever a later rule says about a
     *        value that should not have got that far.
     * @return array<string, string> message key => message, first failing validator only
     */
    private function validate(mixed $value, array $rules, bool $injectNotEmpty): array
    {
        $declared = $this->entries($rules, 'validators');

        if ($injectNotEmpty && ! $this->declaresNotEmpty($declared)) {
            array_unshift($declared, ['NotEmpty', []]);
        }

        foreach ($declared as [$name, $options]) {
            /** @var object $validator */
            $validator = ($this->makeValidator)($name, $options);
            /** @psalm-suppress MixedMethodCall */
            if (! $validator->isValid($value)) {
                /** @var array<string, string> $messages */
                $messages = $validator->getMessages();

                //laminas' ValidatorChain runs the whole chain and merges messages unless a
                //validator breaks the chain; every validator these forms declare is a
                //single independent rule, so reporting the first failure is the same answer
                //with a shorter message list. Asserted against laminas in the parity test.
                return $messages;
            }
        }

        return [];
    }

    /** @param list<array{0: string, 1: array<string, mixed>}> $declared */
    private function declaresNotEmpty(array $declared): bool
    {
        foreach ($declared as [$name]) {
            if ('NotEmpty' === $name || str_ends_with($name, '\\NotEmpty')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $rules
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function entries(array $rules, string $key): array
    {
        $out = [];
        /** @var mixed $list */
        $list = $rules[$key] ?? [];
        if (! is_array($list)) {
            return $out;
        }
        foreach ($list as $entry) {
            //Not `continue`. A specification entry this engine cannot read is a filter or
            //validator that silently does not run, which is the failure this whole layer
            //exists to stop being possible — and it is a shape laminas accepts, so it is
            //not hypothetical: `Laminas\InputFilter\Factory` takes a validator *instance*
            //where this takes a name, and handing one over would have been checked by
            //nothing.
            if (! is_array($entry) || ! is_string($entry['name'] ?? null)) {
                throw new InvalidArgumentException(sprintf(
                    'A %s entry must be ["name" => string, "options" => array]; got %s. '
                    . 'This engine reads specifications as data, so a validator or filter '
                    . 'object has to be declared by class name instead.',
                    $key,
                    get_debug_type($entry)
                ));
            }
            /** @var array<string, mixed> $options */
            $options = is_array($entry['options'] ?? null) ? $entry['options'] : [];
            $out[]   = [$entry['name'], $options];
        }

        return $out;
    }
}
