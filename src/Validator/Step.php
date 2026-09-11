<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function floor;
use function is_numeric;
use function round;
use function strlen;
use function strpos;
use function substr;

/**
 * The value sits on a step from a base — `<input type="number" step="1">`, checked server-side.
 *
 * Six uses, all `step => 1` over an integer base, so in practice this asks "is it a whole
 * number of steps from the base". The float arithmetic underneath is laminas' and is kept:
 * `fmod()` on floats is famously wrong at the last bit, so the modulus is computed by hand
 * at a precision derived from the decimal places of both operands.
 */
final class Step extends AbstractValidator
{
    public const INVALID  = 'typeInvalid';
    public const NOT_STEP = 'stepInvalid';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID  => 'Invalid value given. Scalar expected',
        self::NOT_STEP => 'The input is not a valid step',
    ];

    /** @var mixed */
    private mixed $baseValue = 0;

    private float $step = 1.0;

    public function setBaseValue(mixed $baseValue): static
    {
        $this->baseValue = $baseValue;

        return $this;
    }

    public function setStep(mixed $step): static
    {
        $this->step = (float) $step;

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        if (! is_numeric($value)) {
            $this->error(self::INVALID);

            return false;
        }

        $this->setValue($value);

        $remainder = self::modulus(self::subtract($value, $this->baseValue), $this->step);

        if (0.0 !== $remainder && $remainder !== $this->step) {
            $this->error(self::NOT_STEP);

            return false;
        }

        return true;
    }

    private static function modulus(float $x, float $y): float
    {
        if (0.0 === $y) {
            return 1.0;
        }

        return round($x - $y * floor($x / $y), self::precision($x) + self::precision($y));
    }

    private static function subtract(mixed $x, mixed $y): float
    {
        return round((float) $x - (float) $y, self::precision($x) + self::precision($y));
    }

    private static function precision(mixed $number): int
    {
        $position = strpos((string) $number, '.');

        return false === $position ? 0 : strlen(substr((string) $number, $position + 1));
    }
}
