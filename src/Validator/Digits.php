<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function is_float;
use function is_int;
use function is_string;
use function preg_replace;

/**
 * Digits and nothing else.
 *
 * Six uses. The implementation is laminas': strip everything that is not a digit and see
 * whether anything was stripped, rather than `ctype_digit`, which answers differently for
 * an empty string and for a string of digits longer than an int.
 *
 * `[:digit:]` under a non-Unicode pattern is ASCII `0-9` — the Arabic-Indic digits are not
 * accepted, and should not be: these fields end up in numeric columns.
 */
final class Digits extends AbstractValidator
{
    public const NOT_DIGITS   = 'notDigits';
    public const STRING_EMPTY = 'digitsStringEmpty';
    public const INVALID      = 'digitsInvalid';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::NOT_DIGITS   => 'The input must contain only digits',
        self::STRING_EMPTY => 'The input is an empty string',
        self::INVALID      => 'Invalid type given. String, integer or float expected',
    ];

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $this->error(self::INVALID);

            return false;
        }

        $this->setValue((string) $value);

        if ('' === $this->getValue()) {
            $this->error(self::STRING_EMPTY);

            return false;
        }

        if ($this->getValue() !== preg_replace('/[^[:digit:]]/', '', (string) $this->getValue())) {
            $this->error(self::NOT_DIGITS);

            return false;
        }

        return true;
    }
}
