<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A checkbox: two values, and a rule for turning anything into one of them.
 *
 * ## `setValue()` is a filter, and the site depends on it
 *
 * An unchecked box posts nothing at all, and a checked one posts whatever the markup says.
 * `setValue()` therefore does not store what it is given: it compares the value against the
 * checked value as a string and stores the checked or the unchecked value accordingly. That
 * is why `'on'`, `'1'`, `1` and `true` all arrive in the database as `1`, and why an absent
 * field arrives as `0` rather than as null.
 *
 * 42 checkboxes rely on it. It is also what makes `SionModel\Form\CheckboxDomain`'s
 * `InArray` over the two values unfailable in practice — which is a reason to keep the rule
 * stated, not a reason to drop it: the pairs are not always `1`/`0`, and a form is free to
 * set them.
 *
 * ## `use_hidden_element` defaults to true here and false on a select
 *
 * That asymmetry is laminas' and it is deliberate: a checkbox needs a hidden twin to post
 * its unchecked value at all, and a select always posts something. `BootstrapFormRenderer`
 * reads the flag when it renders the pair.
 */
class Checkbox extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'checkbox',
    ];

    protected bool $useHiddenElement = true;

    protected ?string $uncheckedValue = '0';

    protected string $checkedValue = '1';

    /** @param iterable<string, mixed> $options */
    public function setOptions(iterable $options): static
    {
        parent::setOptions($options);

        if (isset($this->options['use_hidden_element'])) {
            $this->setUseHiddenElement((bool) $this->options['use_hidden_element']);
        }

        if (isset($this->options['unchecked_value'])) {
            $this->setUncheckedValue((string) $this->options['unchecked_value']);
        }

        if (isset($this->options['checked_value'])) {
            $this->setCheckedValue((string) $this->options['checked_value']);
        }

        return $this;
    }

    public function setUseHiddenElement(bool $useHiddenElement): static
    {
        $this->useHiddenElement = $useHiddenElement;

        return $this;
    }

    public function useHiddenElement(): bool
    {
        return $this->useHiddenElement;
    }

    public function setUncheckedValue(?string $uncheckedValue): static
    {
        $this->uncheckedValue = $uncheckedValue;

        return $this;
    }

    public function getUncheckedValue(): ?string
    {
        return $this->uncheckedValue;
    }

    public function setCheckedValue(string $checkedValue): static
    {
        $this->checkedValue = $checkedValue;

        return $this;
    }

    public function getCheckedValue(): string
    {
        return $this->checkedValue;
    }

    /** Anything at all becomes one of the two values — see the class docblock. */
    public function setValue(mixed $value): static
    {
        $checked = (string) $value === $this->getCheckedValue();

        return parent::setValue($checked ? $this->getCheckedValue() : $this->getUncheckedValue());
    }

    public function isChecked(): bool
    {
        return $this->value === $this->getCheckedValue();
    }

    public function setChecked(bool $value): static
    {
        return parent::setValue($value ? $this->getCheckedValue() : $this->getUncheckedValue());
    }
}
