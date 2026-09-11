<?php

declare(strict_types=1);

namespace SionModel\Validator;

/**
 * The value is above a floor.
 *
 * 16 uses, every one of them `inclusive => true` and most of them a year: `min => 1800` on
 * a birth year, `min => 1` on a count. The comparison is PHP's `>`, untyped, so a date
 * string compares against a date string and a number against a number — `min => '1900-01-01'`
 * is one of the sixteen and works for exactly that reason.
 */
final class GreaterThan extends AbstractValidator
{
    public const NOT_GREATER           = 'notGreaterThan';
    public const NOT_GREATER_INCLUSIVE = 'notGreaterThanInclusive';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::NOT_GREATER           => "The input is not greater than '%min%'",
        self::NOT_GREATER_INCLUSIVE => "The input is not greater than or equal to '%min%'",
    ];

    /** @var array<string, string> */
    protected $messageVariables = ['min' => 'min'];

    /** @var mixed */
    protected mixed $min = null;

    private bool $inclusive = false;

    public function setMin(mixed $min): static
    {
        $this->min = $min;

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
            if ($this->min > $value) {
                $this->error(self::NOT_GREATER_INCLUSIVE);

                return false;
            }

            return true;
        }

        if ($this->min >= $value) {
            $this->error(self::NOT_GREATER);

            return false;
        }

        return true;
    }
}
