<?php

declare(strict_types=1);

namespace SionModel\Validator;

/**
 * A validator answers whether a value may be stored, and says why not.
 *
 * The same untyped shape as `SionModel\Validator\ValidatorInterface`, for the reason
 * {@see \SionModel\Filter\FilterInterface} gives: twenty-one classes implement it and a
 * one-line change per class is a migration that can be read.
 */
interface ValidatorInterface
{
    /**
     * Whether the value passes, with the sibling values for a rule that needs them.
     *
     * `$context` is the second parameter laminas never declared on the interface and every
     * cross-field validator relied on. It is declared here, because a contract that only
     * some implementations honour is how five ordered date rules sat inert for a month.
     *
     * @param mixed $value
     * @param array<string, mixed>|null $context the raw sibling values, as
     *        `SionModel\Form\Validation\InputFilter` assembles them
     * @return bool
     */
    public function isValid($value, $context = null);

    /**
     * Message key => message, for the most recent `isValid()` that returned false.
     *
     * Empty otherwise, and empty before the first call. The keys are part of the contract:
     * `SionModel\Form\BootstrapFormRenderer` renders them and `test/Form/engine-surface.php`
     * records them.
     *
     * @return array<string, string>
     */
    public function getMessages();
}
