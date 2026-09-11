<?php

declare(strict_types=1);

namespace SionModel\Form;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An element that holds other elements.
 *
 * Replaces `Laminas\Form\FieldsetInterface`, minus everything this application does not
 * do — and the omissions are measured rather than assumed. Nothing binds an object to a
 * form, so `setObject()`, `getObject()`, `bindValues()`, `allowValueBinding()`,
 * `setHydrator()` and `getHydrator()` are absent, along with laminas-hydrator behind them;
 * `SionModel\Form\Form::isValid()` has refused a bound object since #239 and no caller has
 * noticed. Nothing sets a priority on an element, so `setPriority()` is absent and children
 * keep the order they were added in — which is the order laminas' `PriorityList` gives them
 * when no priority is ever set, and the order both baselines record.
 *
 * @extends IteratorAggregate<string, ElementInterface>
 */
interface FieldsetInterface extends ElementInterface, Countable, IteratorAggregate
{
    /**
     * Add an element, a fieldset, or a specification for either.
     *
     * @param ElementInterface|array<string, mixed>|Traversable<string, mixed> $elementOrSpec
     * @param array{name?: string} $flags
     */
    public function add(ElementInterface|array|Traversable $elementOrSpec, array $flags = []): static;

    public function has(string $name): bool;

    /** @throws Exception\ElementNotFound when nothing of that name is here. */
    public function get(string $name): ElementInterface;

    public function remove(string $name): static;

    /**
     * The children that are not fieldsets, in the order they were added.
     *
     * @return array<string, ElementInterface>
     */
    public function getElements(): array;

    /**
     * The children that are, in the order they were added.
     *
     * @return array<string, FieldsetInterface>
     */
    public function getFieldsets(): array;

    /** @param iterable<string, mixed> $data */
    public function populateValues(iterable $data): void;
}
