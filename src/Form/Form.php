<?php

declare(strict_types=1);

namespace SionModel\Form;

use Laminas\Filter\FilterPluginManager;
use Laminas\Form\Exception\DomainException;
use Laminas\Form\Form as LaminasForm;
use Laminas\Form\FormInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use LogicException;
use SionModel\Form\Validation\FormSpecification;
use SionModel\Form\Validation\InputFilter as Engine;

use function is_array;
use function is_object;
use function sprintf;

/**
 * The application's form base class: validation runs on {@see Engine}, not on
 * `Laminas\InputFilter`.
 *
 * ## What changes, and what deliberately does not
 *
 * `isValid()` and `getData()` are the whole of it. Everything else — elements, rendering,
 * `setData()`, `prepare()`, messages reaching the elements — is still laminas-form's, and
 * has to be: `SionModel\Form\BootstrapFormRenderer` asks elements twelve questions and
 * sixty templates go through it. This class is step 5 of the laminas exit and step 5
 * replaces the *input filter*.
 *
 * The order matters. Removing `laminas-form` means writing an element model and a form
 * model, parity-tested against every one of those templates, and building that on an
 * unproven validation engine would mean debugging both at once. So the engine goes first,
 * behind an unchanged surface, where the thing it replaced is still sitting next to it:
 * `getInputFilter()` still assembles laminas' filter, `WholeFormEngineParityTest` still
 * compares the two on every form, and reverting this class is a one-line change.
 *
 * ## Why the engine is built at isValid() time
 *
 * `FormSpecification::of($this)` reads the elements, and a form's elements are not
 * finished when its constructor returns: `BookFormFactory` fills in authors, collections,
 * publishers and categories afterwards, `AssignmentForm::setData()` narrows `roleId` to
 * the submitted association's roles, and `CheckoutForms::mass()` populates a select inside
 * a collection's target element. laminas has the same property for the same reason —
 * `getInputFilter()` calls `getInputFilterSpecification()` lazily — and `ChoiceDomain`
 * depends on it to read a haystack a factory supplied.
 *
 * ## Why the plugin managers need no wiring
 *
 * A specification names its filters and validators by class or by short name, and every
 * one of the 23 filter names and 29 validator names in this application resolves from a
 * bare `ServiceManager` — measured, not assumed. Anything a rule needs from the
 * application (`RecordExists`' database adapter) is already passed through the
 * specification's own options, because that is the only place a specification can put it.
 *
 * So there is nothing to inject, and that is the point: the alternative was a static
 * registry populated at bootstrap, which is exactly the shape `GlobalAdapterFeature` had
 * before `JUser\Form\DeleteUserForm` took its adapter as a constructor argument instead —
 * a form that could not be constructed without reproducing a bootstrap step.
 */
class Form extends LaminasForm
{
    /** @var array<string, mixed>|null the values of the last validation */
    private ?array $engineValues = null;

    /** @var array<string, mixed> the messages of the last validation */
    private array $engineMessages = [];

    private ?FilterPluginManager $filters       = null;
    private ?ValidatorPluginManager $validators = null;

    /**
     * @throws DomainException when there is no data to validate, as laminas throws.
     * @throws LogicException when an object is bound; see getData().
     */
    public function isValid(): bool
    {
        if ($this->hasValidated) {
            return $this->isValid;
        }

        $this->guardAgainstBoundObject();

        if (! is_array($this->data)) {
            throw new DomainException(sprintf(
                '%s is unable to validate as there is no data currently set',
                __METHOD__
            ));
        }

        $engine = $this->engine();
        $engine->setValidationGroup($this->flatValidationGroup());
        $engine->setData($this->data);

        $this->isValid      = $result = $engine->isValid();
        $this->hasValidated = true;
        $this->engineValues   = $engine->getValues();
        $this->engineMessages = $engine->getMessages();

        if (! $result) {
            //The same call laminas makes, and the reason the renderer needs no change:
            //`Fieldset::setMessages()` hands each message set to the element of that name,
            //and `BootstrapFormRenderer` reads them back through `getMessages()`. The
            //engine nests its messages the way laminas nests them so a fieldset's reach
            //their fieldset's elements.
            $this->setMessages($engine->getMessages());
        }

        return $result;
    }

    /**
     * The messages of the **last** validation, and only those.
     *
     * `Fieldset::getMessages()` cannot answer this. `Form::isValid()` distributes messages
     * with `setMessages()`, which touches only the elements named in that set, so an
     * element that failed on one call and passes on the next **keeps its stale messages
     * forever**. That is fine for a request, which validates once; it is wrong for
     * anything that drives one form repeatedly, and `test/Fuzz/HostileInputDriver` used
     * `getInputFilter()->getMessages()` precisely to avoid it — an answer that stopped
     * being the live one when this class took over.
     *
     * Empty after a validation that passed, and empty before the first one.
     *
     * @return array<string, mixed> field name => message key => message, nested for a
     *         fieldset and keyed by row for a collection
     */
    public function validationMessages(): array
    {
        return $this->engineMessages;
    }

    /**
     * @param int $flag ignored except to refuse `VALUES_RAW`
     * @return array<string, mixed>
     * @throws DomainException when validation has not run, as laminas throws.
     */
    public function getData(int $flag = FormInterface::VALUES_NORMALIZED)
    {
        if (! $this->hasValidated || null === $this->engineValues) {
            throw new DomainException(sprintf(
                '%s cannot return data as validation has not yet occurred',
                __METHOD__
            ));
        }

        //laminas answers VALUES_RAW from `$filter->getRawValues()`. The engine keeps no
        //raw values — it has the submitted data and the filtered result, and a caller
        //wanting the former can read the request. Nothing in this application asks, so
        //this refuses rather than inventing an answer that looks right.
        if (FormInterface::VALUES_RAW === $flag) {
            throw new LogicException(sprintf(
                '%s does not answer VALUES_RAW. Read the request for unfiltered values.',
                __METHOD__
            ));
        }

        return $this->engineValues;
    }

    /**
     * The validation group as a flat list of names.
     *
     * laminas accepts a nested group — `['map' => ['title']]` — and `prepareValidationGroup()`
     * walks it into fieldsets and expands it across a collection's rows. Both groups in this
     * application are flat lists of top-level names, and a nested one would narrow a
     * fieldset in a way this class would silently ignore, so it is refused instead.
     *
     * @return list<string>|null
     */
    private function flatValidationGroup(): ?array
    {
        $group = $this->getValidationGroup();
        if (null === $group) {
            return null;
        }

        $flat = [];
        foreach ($group as $key => $value) {
            if (is_array($value)) {
                throw new LogicException(sprintf(
                    'The validation group of %s narrows the fieldset %s. %s validates a '
                    . 'fieldset by its own specification and cannot narrow one.',
                    static::class,
                    (string) $key,
                    self::class
                ));
            }
            $flat[] = (string) $value;
        }

        return $flat;
    }

    /**
     * Bound objects are refused rather than supported.
     *
     * `Form::bind()` makes `getData()` return a hydrated object instead of an array, and
     * `isValid()` extract the data from it. Nothing in this application binds — measured
     * across every form, and every controller reads `getData()` as an array and hands it to
     * `SionTable` — so supporting it would mean carrying laminas-hydrator into a layer whose
     * purpose is to leave laminas behind, for no caller.
     */
    private function guardAgainstBoundObject(): void
    {
        if (is_object($this->object)) {
            throw new LogicException(sprintf(
                '%s has an object bound to it. %s validates and returns arrays; binding '
                . 'needs a hydrator, which this application does not use anywhere.',
                static::class,
                self::class
            ));
        }
    }

    private function engine(): Engine
    {
        //Rebuilt per validation rather than memoised: a form can be validated twice in a
        //request with its value options narrowed in between — AssignmentForm::setData()
        //does exactly that — and a memoised specification would enforce the first call's
        //domain on the second call's data.
        return new Engine(
            FormSpecification::of($this),
            fn(string $name, array $options): object => $this->filters()->get($name, $options),
            fn(string $name, array $options): object => $this->validators()->get($name, $options)
        );
    }

    private function filters(): FilterPluginManager
    {
        return $this->filters ??= new FilterPluginManager(new ServiceManager());
    }

    private function validators(): ValidatorPluginManager
    {
        return $this->validators ??= new ValidatorPluginManager(new ServiceManager());
    }
}
