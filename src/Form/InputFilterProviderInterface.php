<?php

declare(strict_types=1);

namespace SionModel\Form;

/**
 * A form or fieldset that declares its own validation rules.
 *
 * 46 classes implement this. It is the whole of what a form says about validation:
 * `SionModel\Form\Validation\FormSpecification` asks every fieldset for its specification
 * and assembles one nested array, and `SionModel\Form\Validation\InputFilter` reads that
 * array and nothing else.
 *
 * The shape is `SionModel\Form\InputFilterProviderInterface`'s, unchanged, because 46
 * implementations and one assembler is the wrong ratio for an improvement — and because
 * what a specification *contains* is documented where it is read, not here.
 */
interface InputFilterProviderInterface
{
    /**
     * Field name => `['required' => bool, 'filters' => […], 'validators' => […]]`.
     *
     * A nested fieldset contributes its own under its own name; a field absent from the
     * array gets `required => false` and no rules at all, which is the quietest way there
     * is to lose a check.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification();
}
