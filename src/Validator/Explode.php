<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function array_unique;
use function explode;
use function is_array;
use function is_string;

use const SORT_REGULAR;

/**
 * One validator applied to each of many values.
 *
 * Six uses, all from `SionModel\Form\{ChoiceDomain,InputTypeRules}`: a multiple `<select>`
 * posts an array and `InArray` judges one value, so `Explode` is what puts the two
 * together; an `Email` element with `multiple` splits a comma-separated list the same way.
 *
 * ## The messages nest, and the engine flattens them
 *
 * laminas appends each failing inner validator's **whole message array** under a numeric
 * key, so `getMessages()` returns a list of arrays rather than key => string. That shape
 * reaches `test/Form/engine-surface.php` and `BootstrapFormRenderer`, so it is reproduced
 * exactly rather than flattened here.
 */
final class Explode extends AbstractValidator
{
    public const INVALID = 'explodeInvalid';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID => 'Invalid type given',
    ];

    private ?string $valueDelimiter = ',';

    private ?ValidatorInterface $validator = null;

    private bool $breakOnFirstFailure = false;

    /** @var array<array-key, mixed> */
    private array $innerMessages = [];

    public function setValueDelimiter(mixed $delimiter): static
    {
        $this->valueDelimiter = null === $delimiter ? null : (string) $delimiter;

        return $this;
    }

    /**
     * A validator instance, or the `['name' => …, 'options' => …]` a specification writes.
     */
    public function setValidator(mixed $validator): static
    {
        if (is_array($validator)) {
            if (! isset($validator['name']) || ! is_string($validator['name'])) {
                throw new Exception\RuntimeException(
                    'Invalid validator specification provided; does not include "name" key'
                );
            }

            /** @var array<string, mixed> $options */
            $options   = $validator['options'] ?? [];
            $validator = Registry::get($validator['name'], $options);
        }

        if (! $validator instanceof ValidatorInterface) {
            throw new Exception\RuntimeException('Invalid validator given');
        }

        $this->validator = $validator;

        return $this;
    }

    public function setBreakOnFirstFailure(mixed $break): static
    {
        $this->breakOnFirstFailure = (bool) $break;

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->setValue($value);
        $this->innerMessages = [];

        if (null === $this->validator) {
            throw new Exception\RuntimeException('Explode expects a validator to be set; none given');
        }

        if (is_array($value)) {
            $values = $value;
        } elseif (is_string($value)) {
            //A null delimiter means "do not split": the field is an array when there are
            //several values and a bare string when there is one.
            $values = null !== $this->valueDelimiter ? explode($this->valueDelimiter, $value) : [$value];
        } else {
            $values = [$value];
        }

        foreach ($values as $each) {
            if ($this->validator->isValid($each, $context)) {
                continue;
            }

            $this->innerMessages[] = $this->validator->getMessages();

            if ($this->breakOnFirstFailure) {
                return false;
            }
        }

        return [] === $this->innerMessages;
    }

    /**
     * The inner validator's messages, one entry per failing value — **de-duplicated**.
     *
     * `AbstractValidator::getMessages()` ends in `array_unique(..., SORT_REGULAR)`, and on
     * a list of arrays that is not a tidying step: two values failing the same rule produce
     * two identical message arrays and the visitor is shown one. A multiple select with
     * three bad options says "The input was not found in the haystack" once, not three
     * times.
     *
     * @return array<array-key, mixed>
     */
    public function getMessages()
    {
        if ([] === $this->innerMessages) {
            return parent::getMessages();
        }

        return array_unique($this->innerMessages, SORT_REGULAR);
    }
}
