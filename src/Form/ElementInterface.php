<?php

declare(strict_types=1);

namespace SionModel\Form;

/**
 * What a form element answers.
 *
 * ## Why this is ours and why it is this size
 *
 * It replaces `Laminas\Form\ElementInterface`, and it is deliberately not a transcription
 * of it. The questions listed here are the ones something actually asks: `test/Element/element-surface.php`
 * recorded every one of them across all 441 elements in the application before
 * `SionModel\Form\Element\Element` was written, and this is that list with a type on it.
 *
 * What laminas' interface has and this does not: `setLabelAttributes()`,
 * `getLabelAttributes()`, `setLabelOptions()` and their six relatives. `Element` still
 * implements all of them — they are real methods with real behaviour, and a form is free
 * to call them — but nothing type-hints an element in order to ask, so declaring them here
 * would oblige every future element to answer a question no caller poses.
 *
 * Label handling sits on the interface rather than beside it in a `LabelAwareInterface`,
 * because every element here has a label and the renderer asks all of them.
 */
interface ElementInterface
{
    /**
     * Called once, by {@see \SionModel\Form\Factory}, after the element is built and
     * configured.
     *
     * `Laminas\Stdlib\InitializableInterface` under the only caller that ever used it —
     * `FormElementManager` — kept because a fieldset that builds its own children needs a
     * point after `setOptions()` at which to do it.
     */
    public function init(): void;

    public function setName(string $name): static;

    public function getName(): ?string;

    /** @param iterable<string, mixed> $options */
    public function setOptions(iterable $options): static;

    /** @return array<string, mixed> */
    public function getOptions(): array;

    /** Null when the option is absent **or** explicitly null; see `Element::getOption()`. */
    public function getOption(string $option): mixed;

    public function setOption(string $key, mixed $value): static;

    /** `value` is diverted to {@see setValue()}, as laminas diverts it. */
    public function setAttribute(string $key, mixed $value): static;

    public function getAttribute(string $key): mixed;

    public function hasAttribute(string $key): bool;

    /** @param iterable<string, mixed> $arrayOrTraversable */
    public function setAttributes(iterable $arrayOrTraversable): static;

    /** @return array<string, mixed> in the order the element declared them, which the renderer emits */
    public function getAttributes(): array;

    public function setValue(mixed $value): static;

    public function getValue(): mixed;

    public function setLabel(?string $label): static;

    public function getLabel(): ?string;

    /** @param iterable<string, mixed> $messages */
    public function setMessages(iterable $messages): static;

    /** @return array<string, mixed> */
    public function getMessages(): array;
}
