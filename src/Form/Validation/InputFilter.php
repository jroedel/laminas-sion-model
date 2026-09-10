<?php

declare(strict_types=1);

namespace SionModel\Form\Validation;

use InvalidArgumentException;
use Laminas\Filter\FilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;

use function array_key_exists;
use function array_keys;
use function array_replace;
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
 * ## What it is driven by, and what that cost to arrange
 *
 * The specification alone. `Laminas\Form\Form::getInputFilter()` also builds an input per
 * **element**, from that element's own `getInputSpecification()`, and merges the two —
 * which is why, measured 2026-09-10, **120 fields across the application were validated
 * only by that element half**, 31 of them by `Csrf`. Every one of those was a check this
 * engine would have dropped in silence. They were moved into the specifications over the
 * following days; `test/Fuzz/known-form-gaps.php`'s `validationSuppliedOnlyByElement`
 * holds what is left, and `FormValidationContractTest` fails when it grows.
 *
 * The other half of the merge — the shape, not the rules — is {@see FormSpecification},
 * which assembles the same nested structure `Form::attachInputFilterDefaults()` does out
 * of the specifications and the element list.
 *
 * `test/Integration/InputFilterEngineParityTest` can see none of this: it feeds laminas'
 * `Factory` and this engine the *same* specification, so both sides start where that list
 * ends. It proves the two agree given a specification. `EngineMatchesAssembledFilterTest`
 * compares field by field against what the application really validates with, and
 * `WholeFormEngineParityTest` compares whole submissions.
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

    /** @var array<string, mixed> */
    private array $messages = [];

    /**
     * The names to validate, or null for all of them.
     *
     * A group is a **whitelist that also narrows the result**: `BaseInputFilter::getValues()`
     * iterates `$this->validationGroup ?? array_keys($this->inputs)`, so a field outside the
     * group is neither checked nor returned. `Schoenstatt\Form\EditAssignmentForm` relies on
     * exactly that — its comment says so — and `JUser\Form\EditUserForm` sets one covering
     * every element, which is a no-op it can keep.
     *
     * @var list<string>|null
     */
    private ?array $validationGroup = null;

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

    /**
     * An engine over a specification, resolving rules through laminas' plugin managers.
     *
     * The one place the "today they resolve to laminas' own filters and validators" of the
     * constructor's docblock is actually decided, so that swapping a rule set is one edit
     * rather than one per caller. Four callers want it: {@see \SionModel\Form\Form} for
     * every form on the site, and the three non-form validators — the associations API, the
     * phrases API and the Patres import — that used to reach for
     * `Laminas\Form\Form::getInputFilter()`.
     *
     * The plugin managers take a **bare** `ServiceManager` rather than the application's.
     * Measured when the form cutover landed: all 23 filter names and all 29 validator names
     * any specification here uses resolve out of the managers' own defaults, and SionModel's
     * two custom validators are named by class. Handing them the application container would
     * make an input filter depend on module loading, which is exactly what lets the
     * associations API validate with no MVC, no merged config and no session.
     *
     * @param array<string, mixed> $spec
     */
    public static function withLaminasRules(array $spec): self
    {
        $filters    = null;
        $validators = null;

        return new self(
            $spec,
            static function (string $name, array $options) use (&$filters): object {
                $filters ??= new FilterPluginManager(new ServiceManager());

                return $filters->get($name, $options);
            },
            static function (string $name, array $options) use (&$validators): object {
                $validators ??= new ValidatorPluginManager(new ServiceManager());

                return $validators->get($name, $options);
            }
        );
    }

    /**
     * The rules this engine was built with.
     *
     * The laminas filter this replaces was introspected as an object graph — `has()`,
     * `get()`, `getValidatorChain()` — and two callers did exactly that: the API schema
     * endpoint publishes a phrase's maximum length, and the parity tests compare two
     * surfaces field by field. A specification is plain data, so they read it instead.
     *
     * @return array<string, mixed>
     */
    public function specification(): array
    {
        return $this->spec;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data     = $data;
        $this->values   = [];
        $this->messages = [];
    }

    /**
     * Validate and return only these names; null restores all of them.
     *
     * @param list<string>|null $group
     * @throws InvalidArgumentException when a name is not in the specification, which is
     *         `BaseInputFilter::validateValidationGroup()`'s behaviour and worth keeping:
     *         a group naming a field that does not exist is a whitelist that silently
     *         stopped whitelisting when the field was renamed.
     */
    public function setValidationGroup(?array $group): void
    {
        if (null === $group) {
            $this->validationGroup = null;

            return;
        }

        foreach ($group as $name) {
            if (! array_key_exists($name, $this->spec)) {
                throw new InvalidArgumentException(sprintf(
                    'The validation group names %s, which the specification does not describe.',
                    $name
                ));
            }
        }

        $this->validationGroup = $group;
    }

    public function isValid(): bool
    {
        $this->values   = [];
        $this->messages = [];
        $valid          = true;

        foreach ($this->validationGroup ?? array_keys($this->spec) as $name) {
            $name  = (string) $name;
            $rules = $this->spec[$name] ?? null;
            if (! is_array($rules)) {
                continue;
            }

            //A fieldset or a collection is a specification of its own rather than a rule,
            //so it is dispatched before anything reads `required` off it.
            if (is_array($rules['fieldset'] ?? null)) {
                $valid = $this->validateFieldset($name, $rules['fieldset']) && $valid;
                continue;
            }
            if (is_array($rules['collection'] ?? null)) {
                $valid = $this->validateCollection($name, $rules['collection']) && $valid;
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

    /**
     * One nested fieldset, validated by its own specification.
     *
     * `BaseInputFilter::populate()` is the source of the two coercions here: a name absent
     * from the data, and a name whose value is not an array, both become an empty set
     * rather than an error. So `map=hello` does not throw — it validates an empty `map`,
     * which fails exactly the fields inside it that are required.
     *
     * @param array<string, mixed> $spec
     */
    private function validateFieldset(string $name, array $spec): bool
    {
        $data = is_array($this->data[$name] ?? null) ? $this->data[$name] : [];

        $child = new self($spec, $this->makeFilter, $this->makeValidator);
        $child->setData($data);
        $valid = $child->isValid();

        $this->values[$name] = $child->getValues();
        if (! $valid) {
            $this->messages[$name] = $child->getMessages();
        }

        return $valid;
    }

    /**
     * A collection: the same specification applied to each row, keyed as the rows are.
     *
     * `CollectionInputFilter` carries a `count` and an `isRequired` that decide whether too
     * few rows is a failure. Neither is reachable from a form — `Form::attachInputFilterDefaults()`
     * constructs the collection filter and calls neither setter — so a collection is exactly
     * "validate each row that arrived", and reproducing the unreachable half would be
     * reproducing laminas rather than the application.
     *
     * A row that is not an array is validated as an empty row. laminas instead throws
     * `InvalidArgumentException` from `CollectionInputFilter::setData()`, which reaches the
     * visitor as a 500 with their whole submission lost; answering "this row is invalid" is
     * the same verdict without the crash. Recorded here because it is a deliberate
     * divergence, and asserted by `WholeFormEngineParityTest`.
     *
     * @param array<string, mixed> $spec
     */
    private function validateCollection(string $name, array $spec): bool
    {
        $rows = is_array($this->data[$name] ?? null) ? $this->data[$name] : [];

        $valid    = true;
        $values   = [];
        $messages = [];

        /** @var mixed $row */
        foreach ($rows as $key => $row) {
            $child = new self($spec, $this->makeFilter, $this->makeValidator);
            $child->setData(is_array($row) ? $row : []);

            if (! $child->isValid()) {
                $valid           = false;
                $messages[$key]  = $child->getMessages();
            }
            $values[$key] = $child->getValues();
        }

        $this->values[$name] = $values;
        if ([] !== $messages) {
            $this->messages[$name] = $messages;
        }

        return $valid;
    }

    /**
     * @return array<string, mixed> the filtered value of every field validated, nested as
     *         the specification is
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, mixed> field name => message key => message, nested for a
     *         fieldset and keyed by row for a collection, which is the shape
     *         `Laminas\Form\Fieldset::setMessages()` distributes
     */
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
        $chain = [];

        //The injected check goes first **and breaks the chain**, which is not a detail:
        //`Input::injectNotEmptyValidator()` calls `prependByName(NotEmpty::class, [], true)`
        //and that third argument is `breakChainOnFailure`. So an empty value reports
        //`isEmpty` and nothing else — rather than `isEmpty` plus whatever a length or
        //format rule says about a value that should never have reached it.
        if ($injectNotEmpty && ! $this->declaresNotEmpty($this->entries($rules, 'validators'))) {
            $chain[] = ['NotEmpty', [], true];
        }
        foreach ($this->entries($rules, 'validators') as [$name, $options]) {
            $chain[] = [$name, $options, false];
        }

        //Every declared validator runs, and their messages merge, which is exactly
        //`ValidatorChain::isValid()`: it continues unless an entry sets
        //`breakChainOnFailure`, and nothing in this application sets it.
        //
        //This used to stop at the first failure and report only its messages. The verdict
        //was identical — every rule here is independent — but the message *list* was not,
        //and the list is what the visitor reads: an address that is both malformed and too
        //long said one of the two things rather than both. Changing what a form says is
        //not something an engine swap gets to do as a side effect.
        $messages = [];

        foreach ($chain as [$name, $options, $breaks]) {
            /** @var object $validator */
            $validator = ($this->makeValidator)($name, $options);
            /** @psalm-suppress MixedMethodCall */
            if ($validator->isValid($value)) {
                continue;
            }

            /** @var array<string, string> $failed */
            $failed   = $validator->getMessages();
            $messages = array_replace($messages, $failed);

            if ($breaks) {
                break;
            }
        }

        return $messages;
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
