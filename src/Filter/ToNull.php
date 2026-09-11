<?php

declare(strict_types=1);

namespace SionModel\Filter;

use SionModel\Filter\Exception\InvalidArgumentException;

use function get_debug_type;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * An empty value as `null`, so the column holds NULL rather than an empty string.
 *
 * The most-used filter in the application — 191 specification entries — and the one whose
 * answer no rendered-markup recording can see, because `value=""` is what both `''` and
 * `null` produce. What it decides is whether a row says "nobody entered this" or "somebody
 * entered nothing", and the two are different questions to ask the database later.
 *
 * ## The type mask
 *
 * Which kinds of emptiness become null. Reproduced from `SionModel\Filter\ToNull` bit for bit
 * — the values are in 191 declarations and changing one would change what a column holds.
 * The default is `TYPE_ALL`, which is what 112 of those declarations take.
 *
 * The mask is an **int** here and laminas also accepted the strings (`'string'`, `'all'`)
 * and lists of them. No specification uses either — measured 2026-09-11, the only options
 * present are `type => 2` and `type => 8` — so the string form is not reproduced and asking
 * for it throws.
 */
final class ToNull extends AbstractFilter
{
    public const TYPE_BOOLEAN     = 1;
    public const TYPE_INTEGER     = 2;
    public const TYPE_EMPTY_ARRAY = 4;
    public const TYPE_STRING      = 8;
    public const TYPE_ZERO_STRING = 16;
    public const TYPE_FLOAT       = 32;
    public const TYPE_ALL         = 63;

    /** @var array{type: int} */
    protected $options = ['type' => self::TYPE_ALL];

    /** @throws InvalidArgumentException */
    public function setType(mixed $type): static
    {
        if (! is_int($type) || $type < 0 || $type > self::TYPE_ALL) {
            throw new InvalidArgumentException(sprintf(
                'ToNull takes a type mask between 0 and %d; received %s',
                self::TYPE_ALL,
                is_int($type) ? (string) $type : get_debug_type($type)
            ));
        }

        $this->options['type'] = $type;

        return $this;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        $type = $this->options['type'];

        //Each test is on the type as well as the value, and that is the whole point: `0`,
        //`'0'`, `''`, `0.0`, `[]` and `false` are six different kinds of empty and a
        //specification says which of them it means.
        if (($type & self::TYPE_FLOAT) && is_float($value) && 0.0 === $value) {
            return null;
        }

        if (($type & self::TYPE_ZERO_STRING) && is_string($value) && '0' === $value) {
            return null;
        }

        if (($type & self::TYPE_STRING) && is_string($value) && '' === $value) {
            return null;
        }

        if (($type & self::TYPE_EMPTY_ARRAY) && is_array($value) && [] === $value) {
            return null;
        }

        if (($type & self::TYPE_INTEGER) && is_int($value) && 0 === $value) {
            return null;
        }

        if (($type & self::TYPE_BOOLEAN) && is_bool($value) && false === $value) {
            return null;
        }

        return $value;
    }
}
