<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

/**
 * A dropdown: a list of options, and the answers the renderer needs to draw it.
 *
 * 136 of these, by far the most common element on the site, and the one whose state is
 * genuinely its own rather than a type attribute. Most of them are filled at runtime by a
 * form factory — 496 associations, 325 publications — so the list is not in the form's
 * source and cannot be reasoned about from it.
 *
 * ## What went, and where it went to
 *
 * `Laminas\Form\Element\Select` built an `InArray` over its own option keys, wrapped it in
 * an `Explode` when the select was multiple, and handed the pair to the input filter
 * through `getInputSpecification()`. `SionModel\Form\ChoiceDomain` writes exactly that pair
 * into a form specification instead, reading `getValueOptions()` and the `multiple`
 * attribute from here. The one behavioural difference is `$fallbackHaystack`, which lets a
 * form state the domain for a select whose options only exist per request — laminas had
 * nothing for that case and validated against an empty haystack, rejecting everything.
 *
 * `disable_inarray_validator` survives as an option and a flag with **no reader left in
 * this class**: it used to suppress the validator that no longer lives here. 63 elements
 * set it, and `ChoiceDomain`'s docblock is where the decision it stands for is now argued.
 * It is kept rather than deleted because deleting it would silently change what those 63
 * definitions mean to a reader, and because `test/Element/element-surface.php` records it.
 *
 * ## What is not reproduced
 *
 * Two of laminas' paths are unused here and are gone: the `options` alias for
 * `value_options`, and the deprecated `setAttribute('options', …)`. Neither appears among
 * the 24 option keys and 29 attribute keys the element census found. Grouped options —
 * `['label' => …, 'options' => [...]]` as an option *value* — are equally absent, which is
 * why `getValueOptions()` is read flat here and by `BootstrapFormRenderer` alike.
 */
class Select extends Element
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'select',
    ];

    /** @var array<int|string, mixed> */
    protected array $valueOptions = [];

    /** Null means no empty option is drawn at all; an empty string is a real, blank option. */
    protected string|array|null $emptyOption = null;

    protected bool $disableInArrayValidator = false;

    protected bool $useHiddenElement = false;

    protected string $unselectedValue = '';

    /** @return array<int|string, mixed> */
    public function getValueOptions(): array
    {
        return $this->valueOptions;
    }

    /**
     * @param array<int|string, mixed> $options
     */
    public function setValueOptions(array $options): static
    {
        $this->valueOptions = $options;

        return $this;
    }

    public function unsetValueOption(string $key): static
    {
        unset($this->valueOptions[$key]);

        return $this;
    }

    /** @param iterable<string, mixed> $options */
    public function setOptions(iterable $options): static
    {
        parent::setOptions($options);

        if (isset($this->options['value_options'])) {
            /** @var array<int|string, mixed> $valueOptions */
            $valueOptions = $this->options['value_options'];
            $this->setValueOptions($valueOptions);
        }

        //isset(), so `'empty_option' => null` means "no empty option" rather than "an
        //option labelled nothing" — the distinction 83 selects here are written against.
        if (isset($this->options['empty_option'])) {
            /** @var string|array<int|string, mixed> $emptyOption */
            $emptyOption = $this->options['empty_option'];
            $this->setEmptyOption($emptyOption);
        }

        if (isset($this->options['disable_inarray_validator'])) {
            $this->setDisableInArrayValidator((bool) $this->options['disable_inarray_validator']);
        }

        if (isset($this->options['use_hidden_element'])) {
            $this->setUseHiddenElement((bool) $this->options['use_hidden_element']);
        }

        if (isset($this->options['unselected_value'])) {
            $this->setUnselectedValue((string) $this->options['unselected_value']);
        }

        return $this;
    }

    /** @param null|string|array<int|string, mixed> $emptyOption */
    public function setEmptyOption(string|array|null $emptyOption): static
    {
        $this->emptyOption = $emptyOption;

        return $this;
    }

    /** @return null|string|array<int|string, mixed> */
    public function getEmptyOption(): string|array|null
    {
        return $this->emptyOption;
    }

    public function setDisableInArrayValidator(bool $disableOption): static
    {
        $this->disableInArrayValidator = $disableOption;

        return $this;
    }

    public function disableInArrayValidator(): bool
    {
        return $this->disableInArrayValidator;
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

    public function setUnselectedValue(string $unselectedValue): static
    {
        $this->unselectedValue = $unselectedValue;

        return $this;
    }

    public function getUnselectedValue(): string
    {
        return $this->unselectedValue;
    }

    /**
     * Both spellings of the attribute, because HTML says `multiple="multiple"` and a form
     * here writes `'multiple' => true`. 19 elements carry it.
     */
    public function isMultiple(): bool
    {
        $multiple = $this->attributes['multiple'] ?? null;

        return true === $multiple || 'multiple' === $multiple;
    }
}
