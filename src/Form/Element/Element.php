<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use SionModel\Form\ElementInterface;

use function array_key_exists;
use function is_array;
use function iterator_to_array;

/**
 * A form element: a name, a value, some attributes, some options, and a label.
 *
 * ## What this replaces, and what it deliberately does not carry
 *
 * `Laminas\Form\Element` is 371 lines of exactly this plumbing plus one thing more:
 * every subclass of it also implements `InputProviderInterface` and hands back an input
 * specification — an `InArray` for a select, a `Csrf` validator, a `Uri`, a `Regex` for a
 * number. That half is **gone**. Since #239 the application validates through
 * {@see \SionModel\Form\Validation\InputFilter}, which reads the form's own specification
 * and nothing else, and the helpers that used to be needed to keep the two halves agreeing
 * — `ChoiceDomain`, `CheckboxDomain`, `CsrfSpec`, `InputTypeRules` — read an element and
 * write plain data into that specification. An element here answers questions. It does not
 * decide whether a submission is valid.
 *
 * What is left is the surface `BootstrapFormRenderer` and the specification helpers
 * actually ask for, recorded across all 441 elements in `test/Element/element-surface.php`
 * before a line of this was written.
 *
 * ## One interface, not four
 *
 * It implemented `Laminas\Form\ElementInterface` plus `LabelAwareInterface`,
 * `ElementAttributeRemovalInterface` and `Laminas\Stdlib\InitializableInterface` while the
 * form model was still laminas', because `Fieldset::add()` type-hinted the first and the
 * other three were how laminas split the rest up. {@see \SionModel\Form\ElementInterface}
 * is the whole surface now: the label methods are on it because every element here has a
 * label and the renderer asks all of them, and `init()` is on it because the factory calls
 * it. The nine label-attribute methods below are still here and still work; nothing
 * type-hints an element in order to call them, so the interface does not name them.
 *
 * ## Two behaviours here are load-bearing and easy to lose
 *
 * `setAttribute('value', …)` **diverts to `setValue()`** rather than storing an attribute.
 * 99 element definitions across 40 form files write `'attributes' => ['value' => …]` — every
 * submit button's caption among them — and `BootstrapFormRenderer` reads `getValue()` rather
 * than the attribute. Store it as an attribute instead and all 99 render empty.
 *
 * `getOption()` uses `isset()`, so an option explicitly set to `null` reads as absent. That
 * is laminas' behaviour and the renderer is written against it (`getOption('form-group')`,
 * `getOption('help-block')`); "fixing" it would change which fields render a help block.
 *
 * ## One flag is deliberately not reproduced
 *
 * `Laminas\Form\Element` keeps a `hasValue` boolean and offers `hasValue()`. Measured
 * across laminas-form's own source and all of `module/` and `src/`: **nothing reads it** —
 * not Fieldset, not Collection, not a view helper, not this application. It is also the
 * flag `Laminas\Form\Element\Checkbox` forgets to set, since its `setValue()` assigns the
 * property directly, so anything that did read it would already be getting the wrong answer
 * for 42 elements here. Carrying it forward would be carrying a bug nobody can observe.
 */
class Element implements ElementInterface
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    protected ?string $label = null;

    /** @var array<string, mixed> */
    protected array $labelAttributes = [];

    /** @var array<string, mixed> */
    protected array $labelOptions = [];

    /** @var array<string, mixed> */
    protected array $messages = [];

    /** @var array<string, mixed> */
    protected array $options = [];

    protected mixed $value = null;

    /**
     * @param null|int|string $name
     * @param iterable<string, mixed> $options
     */
    public function __construct($name = null, iterable $options = [])
    {
        if (null !== $name) {
            $this->setName((string) $name);
        }

        //`! empty()` rather than `[] !== $options`: a Traversable is never empty by that
        //test, and laminas' own constructor skips the call for an empty array. Matching it
        //matters because setOptions() is overridden all the way down this hierarchy.
        if (! empty($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Called by the element manager after construction. Nothing here needs it; a subclass
     * that builds sub-elements might.
     */
    public function init(): void
    {
    }

    public function setName(string $name): static
    {
        $this->setAttribute('name', $name);

        return $this;
    }

    public function getName(): ?string
    {
        $name = $this->attributes['name'] ?? null;

        return null === $name ? null : (string) $name;
    }

    /**
     * The three keys laminas promotes out of the options array, and then the array itself.
     *
     * @param iterable<string, mixed> $options
     */
    public function setOptions(iterable $options): static
    {
        $options = self::toArray($options);

        if (isset($options['label'])) {
            $this->setLabel($options['label']);
        }

        if (isset($options['label_attributes'])) {
            $this->setLabelAttributes($options['label_attributes']);
        }

        if (isset($options['label_options'])) {
            $this->setLabelOptions($options['label_options']);
        }

        //Assignment, not a merge: a second setOptions() call replaces the first, which is
        //what `Laminas\Form\Factory` relies on when it applies a specification.
        $this->options = $options;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** An option set to null reads as absent — see the class docblock. */
    public function getOption(string $option): mixed
    {
        return $this->options[$option] ?? null;
    }

    public function setOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    /** `value` is diverted to setValue() — see the class docblock. */
    public function setAttribute(string $key, mixed $value): static
    {
        if ('value' === $key) {
            $this->setValue($value);

            return $this;
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function removeAttribute(string $key): static
    {
        unset($this->attributes[$key]);

        return $this;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /** @param iterable<string, mixed> $arrayOrTraversable */
    public function setAttributes(iterable $arrayOrTraversable): static
    {
        foreach ($arrayOrTraversable as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param list<string> $keys
     */
    public function removeAttributes(array $keys): static
    {
        foreach ($keys as $key) {
            unset($this->attributes[$key]);
        }

        return $this;
    }

    public function clearAttributes(): static
    {
        $this->attributes = [];

        return $this;
    }

    public function setValue(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    /** A non-string label is ignored rather than cast — laminas' behaviour. */
    public function setLabel(?string $label): static
    {
        if (null !== $label) {
            $this->label = $label;
        }

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** @param array<string, mixed> $labelAttributes */
    public function setLabelAttributes(array $labelAttributes): static
    {
        $this->labelAttributes = $labelAttributes;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getLabelAttributes(): array
    {
        return $this->labelAttributes;
    }

    /** @param iterable<string, mixed> $arrayOrTraversable */
    public function setLabelOptions(iterable $arrayOrTraversable): static
    {
        foreach ($arrayOrTraversable as $key => $value) {
            $this->setLabelOption((string) $key, $value);
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function getLabelOptions(): array
    {
        return $this->labelOptions;
    }

    public function setLabelOption(string $key, mixed $value): static
    {
        $this->labelOptions[$key] = $value;

        return $this;
    }

    /** @param int|string $key */
    public function getLabelOption($key): mixed
    {
        return $this->labelOptions[$key] ?? null;
    }

    public function hasLabelOption(string $key): bool
    {
        return array_key_exists($key, $this->labelOptions);
    }

    public function removeLabelOption(string $key): static
    {
        unset($this->labelOptions[$key]);

        return $this;
    }

    /** @param list<string> $keys */
    public function removeLabelOptions(array $keys): static
    {
        foreach ($keys as $key) {
            unset($this->labelOptions[$key]);
        }

        return $this;
    }

    public function clearLabelOptions(): static
    {
        $this->labelOptions = [];

        return $this;
    }

    /** @param iterable<string, mixed> $messages */
    public function setMessages(iterable $messages): static
    {
        $this->messages = self::toArray($messages);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param iterable<string, mixed> $iterable
     * @return array<string, mixed>
     */
    final protected static function toArray(iterable $iterable): array
    {
        return is_array($iterable) ? $iterable : iterator_to_array($iterable);
    }
}
