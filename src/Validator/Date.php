<?php

declare(strict_types=1);

namespace SionModel\Validator;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * The value is a date.
 *
 * Ten uses, six of them naming `format => 'Y-m-d'`, which is also the default. The format
 * is how the value is **parsed**, not a shape the value must match: `strict` is what makes
 * it a shape check, and nothing here sets it.
 *
 * The subtlety worth keeping is the second half of the string case:
 * `DateTime::createFromFormat()` returns an object for `'2007-02-99'` and reports the
 * problem only through `getLastErrors()`. Without that check, the ninety-ninth of February
 * validates and reaches a DATE column as the ninth of May.
 */
final class Date extends AbstractValidator
{
    public const INVALID      = 'dateInvalid';
    public const INVALID_DATE = 'dateInvalidDate';
    public const FALSEFORMAT  = 'dateFalseFormat';

    public const FORMAT_DEFAULT = 'Y-m-d';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID      => 'Invalid type given. String, integer, array or DateTime expected',
        self::INVALID_DATE => 'The input does not appear to be a valid date',
        self::FALSEFORMAT  => "The input does not fit the date format '%format%'",
    ];

    /** @var array<string, string> */
    protected $messageVariables = ['format' => 'format'];

    protected string $format = self::FORMAT_DEFAULT;

    private bool $strict = false;

    public function setFormat(mixed $format): static
    {
        $this->format = null === $format || '' === $format ? self::FORMAT_DEFAULT : (string) $format;

        return $this;
    }

    public function setStrict(mixed $strict): static
    {
        $this->strict = (bool) $strict;

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->setValue($value);

        $date = $this->toDateTime($value);

        if (! $date instanceof DateTimeInterface) {
            $this->error(self::INVALID_DATE);

            return false;
        }

        if ($this->strict && $date->format($this->format) !== $value) {
            $this->error(self::FALSEFORMAT);

            return false;
        }

        return true;
    }

    private function toDateTime(mixed $value): DateTimeInterface|false
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if ($value instanceof DateTimeImmutable) {
            return DateTime::createFromImmutable($value);
        }

        if (is_int($value) || is_float($value)) {
            return DateTime::createFromFormat('U', (string) $value);
        }

        if (is_string($value)) {
            return $this->fromString($value);
        }

        if (is_array($value)) {
            //`implode('-')`, which is all laminas does with an array — no key check, no
            //`checkdate`. A nested array therefore reaches `implode` and raises "Array to
            //string conversion"; that diagnostic is laminas', and the verdict is the same
            //without it.
            return $this->fromString(implode('-', $value));
        }

        $this->error(self::INVALID);

        return false;
    }

    /**
     * A string in the declared format.
     *
     * Two failures, and they are reported differently. `createFromFormat()` returning false
     * is simply not a date. `createFromFormat()` **succeeding with warnings** is the
     * dangerous one — `'2007-02-99'` and `'2020-03-15 14:30:00'` both yield an object — so a
     * warning is treated as a failure and reported as `dateFalseFormat` **in addition to**
     * the `dateInvalidDate` `isValid()` then adds. Both messages reach the form; that is
     * what the recording holds.
     */
    private function fromString(string $value): DateTimeInterface|false
    {
        $date   = DateTime::createFromFormat($this->format, $value);
        $errors = DateTime::getLastErrors();

        if (false === $errors) {
            return $date;
        }

        if ($errors['warning_count'] > 0) {
            $this->error(self::FALSEFORMAT);

            return false;
        }

        return $date;
    }
}
