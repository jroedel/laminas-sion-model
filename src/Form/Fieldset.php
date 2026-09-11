<?php

declare(strict_types=1);

namespace SionModel\Form;

use ArrayIterator;
use SionModel\Form\Element\Element;
use SionModel\Form\Exception\ElementNotFound;
use Traversable;

use function array_key_exists;
use function count;
use function is_array;
use function is_iterable;
use function is_string;
use function iterator_to_array;
use function sprintf;

/**
 * A named group of elements: the container half of the form model.
 *
 * ## What it replaces
 *
 * `Laminas\Form\Fieldset`, 700 lines of which roughly 200 are the object-binding and
 * hydrator machinery and another 100 the `PriorityList`. Neither is reached here — nothing
 * in the application binds an object to a form or sets an element's priority, measured
 * across every call site before this was written — so children live in one insertion-ordered
 * array, which is the order laminas' `PriorityList` yields when no priority is ever set and
 * the order `test/Form/form-markup.php` and `test/Element/element-surface.php` both record.
 *
 * It extends `Element` because a fieldset **is** an element to everything that walks a
 * form: it has a name, options, attributes and messages, `Form::prepare()` renames it, and
 * `BootstrapFormRenderer` asks it the same questions. laminas has the same inheritance for
 * the same reason.
 *
 * ## Three behaviours that look incidental and are not
 *
 * `add()` takes a **specification** as readily as an object, because that is how all 402
 * `$this->add([...])` calls in this application build their elements, and the type it
 * builds comes from the form's own factory rather than from a global: see
 * {@see getFormFactory()}.
 *
 * `setMessages()` hands each message set to the element of that name and **keeps the ones
 * that name nothing**, which is what lets a fieldset carry a message for a field the
 * engine validated and the form does not render. `getMessages()` collects the other
 * direction. `BootstrapFormRenderer::errors()` reads the elements, so the distribution is
 * what puts a validation message on screen.
 *
 * `populateValues()` is silent about a name it has no child for, and — this is the part
 * that is easy to lose — **it does not clear a child whose name is absent from the data**.
 * laminas does the same, and the difference is visible on every failed POST: a checkbox
 * that was not checked posts nothing, and clearing it here rather than leaving it would
 * make the re-rendered form disagree with what was submitted.
 */
class Fieldset extends Element implements FieldsetInterface, PrepareAwareInterface
{
    /** All children, in the order they were added. @var array<string, ElementInterface> */
    protected array $children = [];

    /** The children that are not fieldsets. @var array<string, ElementInterface> */
    protected array $elements = [];

    /** The children that are. @var array<string, FieldsetInterface> */
    protected array $fieldsets = [];

    /** Messages for names this fieldset has no child for. @var array<string, mixed> */
    protected array $ownMessages = [];

    protected ?Factory $factory = null;

    /**
     * The factory that builds this fieldset's children from specifications.
     *
     * Defaulting to {@see Factory::default()} rather than to a bare factory is the whole
     * point: a bare one would have to be told, per fieldset, which class `'type' => 'Select'`
     * means. `SionModel\Form\Element\Registry` is the one list, and the three things that
     * build elements — this, the container's form service, and the associations API — all
     * read it.
     */
    public function getFormFactory(): Factory
    {
        return $this->factory ??= Factory::default();
    }

    public function setFormFactory(Factory $factory): static
    {
        $this->factory = $factory;

        return $this;
    }

    /** @inheritDoc */
    public function add(ElementInterface|array|Traversable $elementOrSpec, array $flags = []): static
    {
        if (! $elementOrSpec instanceof ElementInterface) {
            $spec          = is_array($elementOrSpec) ? $elementOrSpec : iterator_to_array($elementOrSpec);
            $elementOrSpec = $this->getFormFactory()->create($spec);
        }

        $name = $elementOrSpec->getName();
        if (isset($flags['name']) && '' !== $flags['name']) {
            $name = $flags['name'];
            $elementOrSpec->setName($name);
        }

        if (null === $name || '' === $name) {
            throw new ElementNotFound(sprintf(
                '%s: the element provided is not named, and no name was given in flags',
                static::class
            ));
        }

        //Re-adding a name replaces it in place rather than appending a second entry —
        //`unset` first is what keeps the insertion order from changing under a replacement.
        //`Collection::populateValues()` replaces rows this way.
        unset($this->children[$name], $this->elements[$name], $this->fieldsets[$name]);

        $this->children[$name] = $elementOrSpec;

        if ($elementOrSpec instanceof FieldsetInterface) {
            $this->fieldsets[$name] = $elementOrSpec;
        } else {
            $this->elements[$name] = $elementOrSpec;
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->children);
    }

    public function get(string $name): ElementInterface
    {
        return $this->children[$name]
            ?? throw ElementNotFound::named($name, static::class);
    }

    public function remove(string $name): static
    {
        unset($this->children[$name], $this->elements[$name], $this->fieldsets[$name]);

        return $this;
    }

    /** @inheritDoc */
    public function getElements(): array
    {
        return $this->elements;
    }

    /** @inheritDoc */
    public function getFieldsets(): array
    {
        return $this->fieldsets;
    }

    public function count(): int
    {
        return count($this->children);
    }

    /** @return Traversable<string, ElementInterface> */
    public function getIterator(): Traversable
    {
        //A copy, not a reference to the live array: `library-mass-checkout.html.twig`
        //iterates a collection while nothing adds to it, but `Collection::prepareElement()`
        //adds rows *while* laminas' equivalent is being walked, and an iterator over the
        //live array would be undefined there.
        return new ArrayIterator($this->children);
    }

    /** @inheritDoc */
    public function setMessages(iterable $messages): static
    {
        foreach ($messages as $name => $messageSet) {
            $name = (string) $name;

            if (! $this->has($name)) {
                $this->ownMessages[$name] = $messageSet;
                continue;
            }

            $child = $this->get($name);
            $child->setMessages(is_iterable($messageSet) ? $messageSet : [$messageSet]);
        }

        return $this;
    }

    /** @inheritDoc */
    public function getMessages(): array
    {
        $messages = $this->ownMessages;

        foreach ($this->children as $name => $child) {
            $messageSet = $child->getMessages();
            if ([] === $messageSet) {
                continue;
            }

            $messages[$name] = $messageSet;
        }

        return $messages;
    }

    /** @inheritDoc */
    public function populateValues(iterable $data): void
    {
        $data = is_array($data) ? $data : iterator_to_array($data);

        foreach ($this->children as $name => $child) {
            $exists = array_key_exists($name, $data);

            if ($child instanceof FieldsetInterface) {
                if ($exists && is_iterable($data[$name])) {
                    $child->populateValues($data[$name]);
                    continue;
                }

                if ($child instanceof Collection) {
                    //A collection whose key is absent or null is emptied rather than left
                    //alone: with `allow_remove`, a submission that removed every row sends
                    //nothing, and leaving the rows standing would re-create them.
                    $child->populateValues($exists && null !== $data[$name] ? $data[$name] : []);
                    continue;
                }

                continue;
            }

            if ($exists) {
                $child->setValue($data[$name]);
            }
        }
    }

    /**
     * Rename every child to `fieldset[child]`, and prepare the ones that ask to be.
     *
     * This is what a browser posts and what a template's script selects by, so the exact
     * shape is load-bearing rather than cosmetic —
     * `library-mass-checkout.html.twig` reaches for
     * `input[name='checkout[0][checkedOutOn]']`.
     */
    public function prepareElement(FormInterface $form): void
    {
        $name = (string) $this->getName();

        foreach ($this->children as $child) {
            $child->setName($name . '[' . (string) $child->getName() . ']');

            if ($child instanceof PrepareAwareInterface) {
                $child->prepareElement($form);
            }
        }
    }

    /**
     * A deep copy: a cloned fieldset's children are its own.
     *
     * `Collection` clones its target element once per row, and a shallow copy would give
     * every row the same element objects — four rows sharing one `personId`, so setting the
     * second row's value would change all four.
     */
    public function __clone(): void
    {
        $children        = $this->children;
        $this->children  = [];
        $this->elements  = [];
        $this->fieldsets = [];

        foreach ($children as $name => $child) {
            $clone = clone $child;
            //setName() rather than trusting the clone's own: a child added under a flag
            //alias carries a different name from the one it is keyed by, and the key is
            //what every caller looks it up with.
            if (is_string($name)) {
                $clone->setName($name);
            }

            $this->add($clone);
        }
    }
}
