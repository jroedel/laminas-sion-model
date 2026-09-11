<?php

declare(strict_types=1);

namespace SionModel\Form;

/**
 * A fieldset that can be submitted.
 *
 * Replaces `Laminas\Form\FormInterface`. Four of laminas' methods are gone with the object
 * binding (`bind()`, `bindValues()`, `setBindOnValidate()`, `bindOnValidate()`) and four
 * more with the input filter (`setInputFilter()`, `getInputFilter()`,
 * `setUseInputFilterDefaults()`, `useInputFilterDefaults()`) — validation has run on
 * {@see \SionModel\Form\Validation\InputFilter} since #239, and the only callers of
 * `getInputFilter()` left by then were tests measuring an object no request reached.
 *
 * `getData()` takes no `$flag`. laminas' `VALUES_RAW` answers from the input filter's raw
 * values, which the engine does not keep — it holds the submitted data and the filtered
 * result, and a caller wanting the former can read the request. Nothing asks.
 */
interface FormInterface extends FieldsetInterface
{
    /** @param iterable<string, mixed> $data */
    public function setData(iterable $data): static;

    /**
     * The filtered values of the last validation.
     *
     * @return array<string, mixed>
     * @throws Exception\DomainException when validation has not run.
     */
    public function getData(): array;

    public function isValid(): bool;

    /** Materialises what a rendering needs: names, tokens, collection rows. */
    public function prepare(): static;

    /**
     * Validate only these fields.
     *
     * @param list<string> $group
     */
    public function setValidationGroup(array $group): static;

    /** @return list<string>|null */
    public function getValidationGroup(): ?array;
}
