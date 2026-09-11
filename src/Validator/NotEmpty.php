<?php

declare(strict_types=1);

namespace SionModel\Validator;

use Countable;

use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;

/**
 * The value is not one of the kinds of empty this field cares about.
 *
 * The most consequential validator in the application, because most fields never name it:
 * `SionModel\Form\Validation\InputFilter` **injects** one in front of the chain for any
 * field that neither allows empty nor continues if empty, and breaks the chain on it — so
 * "Value is required and can't be empty" is what an empty required field says, rather than
 * whatever a length or format rule would have said about a value that should not have got
 * that far.
 *
 * ## The default type, and the two entries in it that surprise people
 *
 * laminas' default is `OBJECT | SPACE | NULL | EMPTY_ARRAY | STRING | BOOLEAN` — note what
 * is **not** there: `INTEGER` and `ZERO`. So `0` and `'0'` are **not empty** by default,
 * which is the right answer for a quantity field and the reason nobody has had to write an
 * exception for one.
 *
 * `SPACE` is the entry that catches people: a field of nothing but whitespace is empty, so
 * a visitor cannot satisfy a required field with a space bar. `OBJECT` reads backwards and
 * means "an object is *not* empty" — the flag's absence is what would make one empty.
 */
final class NotEmpty extends AbstractValidator
{
    public const BOOLEAN       = 0b000000000001;
    public const INTEGER       = 0b000000000010;
    public const FLOAT         = 0b000000000100;
    public const STRING        = 0b000000001000;
    public const ZERO          = 0b000000010000;
    public const EMPTY_ARRAY   = 0b000000100000;
    public const NULL          = 0b000001000000;
    public const PHP           = 0b000001111111;
    public const SPACE         = 0b000010000000;
    public const OBJECT        = 0b000100000000;
    public const OBJECT_STRING = 0b001000000000;
    public const OBJECT_COUNT  = 0b010000000000;
    public const ALL           = 0b011111111111;

    public const INVALID  = 'notEmptyInvalid';
    public const IS_EMPTY = 'isEmpty';

    private const DEFAULT_TYPE = self::OBJECT | self::SPACE | self::NULL
        | self::EMPTY_ARRAY | self::STRING | self::BOOLEAN;

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::IS_EMPTY => "Value is required and can't be empty",
        self::INVALID  => 'Invalid type given. String, integer, float, boolean or array expected',
    ];

    /** @var array{type: int} */
    protected $options = ['type' => self::DEFAULT_TYPE];

    public function setType(mixed $type): static
    {
        if (! is_int($type) || $type < 0 || $type > self::ALL) {
            throw new Exception\InvalidArgumentException('NotEmpty takes a type mask');
        }

        $this->options['type'] = $type;

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        if (
            null !== $value
            && ! is_string($value)
            && ! is_int($value)
            && ! is_float($value)
            && ! is_bool($value)
            && ! is_array($value)
            && ! is_object($value)
        ) {
            $this->error(self::INVALID);

            return false;
        }

        $type    = $this->options['type'];
        $this->setValue($value);
        $isEmpty = false;

        if (($type & self::OBJECT) === 0 && is_object($value)) {
            //Reads backwards on purpose: with OBJECT set, an object is never empty. It is
            //laminas' flag and its sense is inverted from every other one here.
            $isEmpty = true;
        }

        if (($type & self::OBJECT_STRING) && is_object($value) && ! method_exists($value, '__toString')) {
            $isEmpty = true;
        }

        if (($type & self::OBJECT_COUNT) && is_object($value) && $value instanceof Countable && 0 === count($value)) {
            $isEmpty = true;
        }

        if (($type & self::SPACE) && is_string($value) && preg_match('/^\s+$/s', $value)) {
            $isEmpty = true;
        }

        if (($type & self::NULL) && null === $value) {
            $isEmpty = true;
        }

        if (($type & self::EMPTY_ARRAY) && is_array($value) && [] === $value) {
            $isEmpty = true;
        }

        if (($type & self::ZERO) && is_string($value) && '0' === $value) {
            $isEmpty = true;
        }

        if (($type & self::STRING) && is_string($value) && '' === $value) {
            $isEmpty = true;
        }

        if (($type & self::FLOAT) && is_float($value) && 0.0 === $value) {
            $isEmpty = true;
        }

        if (($type & self::INTEGER) && is_int($value) && 0 === $value) {
            $isEmpty = true;
        }

        if (($type & self::BOOLEAN) && is_bool($value) && false === $value) {
            $isEmpty = true;
        }

        if ($isEmpty) {
            $this->error(self::IS_EMPTY);

            return false;
        }

        return true;
    }
}
