<?php

declare(strict_types=1);

namespace SionModel\Validator;

/**
 * The value is below a ceiling. The mirror of {@see GreaterThan}, including the untyped
 * comparison and the inclusive flag that every one of the six uses sets.
 *
 * Note the message: "The input is not less or equal than '%max%'". It is ungrammatical and
 * it is laminas', and it is in five translation catalogs under exactly that key.
 */
final class LessThan extends AbstractValidator
{
    public const NOT_LESS           = 'notLessThan';
    public const NOT_LESS_INCLUSIVE = 'notLessThanInclusive';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::NOT_LESS           => "The input is not less than '%max%'",
        self::NOT_LESS_INCLUSIVE => "The input is not less or equal than '%max%'",
    ];

    /** @var array<string, string> */
    protected $messageVariables = ['max' => 'max'];

    /** @var mixed */
    protected mixed $max = null;

    private bool $inclusive = false;

    public function setMax(mixed $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function setInclusive(mixed $inclusive): static
    {
        $this->inclusive = (bool) $inclusive;

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->setValue($value);

        if ($this->inclusive) {
            if ($value > $this->max) {
                $this->error(self::NOT_LESS_INCLUSIVE);

                return false;
            }

            return true;
        }

        if ($value >= $this->max) {
            $this->error(self::NOT_LESS);

            return false;
        }

        return true;
    }
}
