<?php

declare(strict_types=1);

namespace SionModel\Form;

use LogicException;
use SionModel\Form\Exception\DomainException;
use SionModel\Form\Validation\FormSpecification;
use SionModel\Form\Validation\InputFilter as Engine;

use function is_array;
use function iterator_to_array;
use function sprintf;

/**
 * The application's form base class: a fieldset that can be submitted.
 *
 * ## What it is
 *
 * `Laminas\Form\Form` is 800 lines, and what a form here needs from it is four things:
 * hold elements, take submitted data, validate, and prepare itself for rendering. The
 * first comes from {@see Fieldset}; the middle two are below; `prepare()` is the last.
 *
 * Everything else in laminas' class serves machinery this application does not use, and
 * each omission was measured across every call site rather than assumed. There is no
 * object binding and no hydrator — {@see guardAgainstBoundObject()} has refused a bound
 * object since #239 and nothing noticed. There is no `getInputFilter()`: validation runs
 * on {@see Engine} over {@see FormSpecification}, and by the time the form model was
 * replaced the only callers left were tests measuring an object no request reached. There
 * is no base fieldset, no `wrapElements()`, no `setPriority()`, no `VALUES_RAW`.
 *
 * ## Why the engine is built at isValid() time
 *
 * `FormSpecification::of($this)` reads the elements, and a form's elements are not
 * finished when its constructor returns: `BookFormFactory` fills in authors, collections,
 * publishers and categories afterwards, `AssignmentForm::setData()` narrows `roleId` to
 * the submitted association's roles, and `CheckoutForms::mass()` populates a select inside
 * a collection's target element. laminas had the same property for the same reason, and
 * `ChoiceDomain` depends on it to read a haystack a factory supplied.
 *
 * ## Why the rules need no wiring
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
 * a form that could not be constructed without reproducing a bootstrap step. The wiring
 * itself lives in {@see Engine::withLaminasRules()}, shared with the three non-form
 * validators, so replacing a rule set is one edit rather than four.
 */
class Form extends Fieldset implements FormInterface
{
    /** @var array<string, mixed>|null the data last submitted */
    protected ?array $data = null;

    /** @var list<string>|null */
    protected ?array $validationGroup = null;

    protected bool $hasValidated = false;

    protected bool $isValid = false;

    protected bool $isPrepared = false;

    /** @var array<string, mixed>|null the values of the last validation */
    private ?array $engineValues = null;

    /** @var array<string, mixed> the messages of the last validation */
    private array $engineMessages = [];

    /**
     * A form is named, and laminas' constructor took the name first.
     *
     * @param null|int|string $name
     * @param iterable<string, mixed> $options
     */
    public function __construct($name = null, iterable $options = [])
    {
        parent::__construct($name, $options);

        //laminas' Form seeds `method="POST"` and nothing else; `BootstrapFormRenderer::open()`
        //reads the attribute back, and the two contact-search forms override it with GET.
        if (! $this->hasAttribute('method')) {
            $this->setAttribute('method', 'POST');
        }
    }

    /**
     * Take a submission.
     *
     * The values reach the elements immediately, which is what lets an edit page render a
     * record by calling this and nothing else.
     *
     * @param iterable<string, mixed> $data
     */
    public function setData(iterable $data): static
    {
        $data = is_array($data) ? $data : iterator_to_array($data);

        $this->hasValidated = false;
        $this->data         = $data;
        $this->populateValues($data);

        return $this;
    }

    /**
     * Materialise what a rendering needs, once.
     *
     * Only a fieldset's children are renamed — `prepareElement()` on this class does not
     * rename the form's own top-level elements, because laminas only did that under
     * `wrapElements()`, which nothing sets. What it does reach is every
     * {@see PrepareAwareInterface} child: the CSRF token, the collection's rows, the
     * DateSelect's three sub-elements, the file input's enctype.
     */
    public function prepare(): static
    {
        if ($this->isPrepared) {
            return $this;
        }

        foreach ($this->getIterator() as $child) {
            if ($child instanceof PrepareAwareInterface) {
                $child->prepareElement($this);
            }
        }

        $this->isPrepared = true;

        return $this;
    }

    /** @inheritDoc */
    public function setValidationGroup(array $group): static
    {
        $this->hasValidated    = false;
        $this->validationGroup = $group;

        return $this;
    }

    /** @inheritDoc */
    public function getValidationGroup(): ?array
    {
        return $this->validationGroup;
    }

    /**
     * @throws DomainException when there is no data to validate, as laminas threw.
     */
    public function isValid(): bool
    {
        if ($this->hasValidated) {
            return $this->isValid;
        }

        if (! is_array($this->data)) {
            throw new DomainException(sprintf(
                '%s is unable to validate as there is no data currently set',
                __METHOD__
            ));
        }

        $engine = $this->engine();
        $engine->setValidationGroup($this->validationGroup);
        $engine->setData($this->data);

        $this->isValid      = $result = $engine->isValid();
        $this->hasValidated = true;
        $this->engineValues   = $engine->getValues();
        $this->engineMessages = $engine->getMessages();

        if (! $result) {
            //`Fieldset::setMessages()` hands each message set to the element of that name,
            //and `BootstrapFormRenderer::errors()` reads them back through `getMessages()`.
            //That distribution is what puts a message on screen; the engine nests its
            //messages the way a form is nested so a fieldset's reach their fieldset's
            //elements.
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
     * @return array<string, mixed>
     * @throws DomainException when validation has not run, as laminas threw.
     */
    public function getData(): array
    {
        if (! $this->hasValidated || null === $this->engineValues) {
            throw new DomainException(sprintf(
                '%s cannot return data as validation has not yet occurred',
                __METHOD__
            ));
        }

        return $this->engineValues;
    }

    private function engine(): Engine
    {
        //Rebuilt per validation rather than memoised: a form can be validated twice in a
        //request with its value options narrowed in between — AssignmentForm::setData()
        //does exactly that — and a memoised specification would enforce the first call's
        //domain on the second call's data.
        return Engine::withLaminasRules(FormSpecification::of($this));
    }
}
