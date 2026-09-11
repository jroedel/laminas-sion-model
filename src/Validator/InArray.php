<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * The value is one of a fixed set.
 *
 * 132 specification entries, and the set is almost always the same one a `<select>` renders
 * — `SionModel\Form\ChoiceDomain` builds both from the element, which is what keeps the
 * offered options and the accepted options from drifting apart.
 *
 * ## The three comparison modes, and why the middle one is the default
 *
 * `COMPARE_NOT_STRICT` is `in_array($v, $haystack, false)`, and in PHP 7 that made
 * `'anything'` equal to `0`, so a haystack of integers accepted any string at all. PHP 8
 * fixed the comparison, but the default here remains
 * `COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY`: it compares as strings when
 * either side is numeric, which is what a form actually needs — a `<select>` posts `"7"`
 * and the haystack holds `7`.
 */
final class InArray extends AbstractValidator
{
    public const NOT_IN_ARRAY = 'notInArray';

    public const COMPARE_STRICT                                        = 1;
    public const COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY = 0;
    public const COMPARE_NOT_STRICT                                    = -1;

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::NOT_IN_ARRAY => 'The input was not found in the haystack',
    ];

    /** @var array<array-key, mixed>|null */
    private ?array $haystack = null;

    private int $strict = self::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY;

    /** @param array<array-key, mixed> $haystack */
    public function setHaystack(mixed $haystack): static
    {
        if (! is_array($haystack)) {
            throw new Exception\InvalidArgumentException('An InArray haystack must be an array');
        }

        $this->haystack = $haystack;

        return $this;
    }

    /**
     * The configured domain, for the one caller that has to read it back.
     *
     * `ConstrainedChoiceFieldsFitTheirDataTest` asks every constrained choice field whether
     * the values already stored in its column are still offered — a question that cannot be
     * answered from the specification, because `ChoiceDomain` wraps a multiple select's
     * `InArray` in an `Explode` as an **instance**. Nothing in the request path reads this.
     *
     * @return array<array-key, mixed>|null
     */
    public function getHaystack(): ?array
    {
        return $this->haystack;
    }

    public function setStrict(mixed $strict): static
    {
        if (is_bool($strict)) {
            $strict = $strict ? self::COMPARE_STRICT : self::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY;
        }

        if (
            self::COMPARE_STRICT !== $strict
            && self::COMPARE_NOT_STRICT !== $strict
            && self::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY !== $strict
        ) {
            throw new Exception\InvalidArgumentException('Strict option must be one of the COMPARE_ constants');
        }

        $this->strict = (int) $strict;

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        if (null === $this->haystack) {
            throw new Exception\RuntimeException('haystack option is mandatory');
        }

        $haystack = $this->haystack;

        if (
            self::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY === $this->strict
            && (is_int($value) || is_float($value))
        ) {
            $value = (string) $value;
        }

        $this->setValue($value);

        if (
            self::COMPARE_NOT_STRICT_AND_PREVENT_STR_TO_INT_VULNERABILITY === $this->strict
            && is_string($value)
        ) {
            foreach ($haystack as &$entry) {
                if (is_int($entry) || is_float($entry)) {
                    $entry = (string) $entry;
                }
            }
            unset($entry);

            if (in_array($value, $haystack, false)) {
                return true;
            }

            $this->error(self::NOT_IN_ARRAY);

            return false;
        }

        if (in_array($value, $haystack, self::COMPARE_STRICT === $this->strict)) {
            return true;
        }

        //`COMPARE_NOT_STRICT` accepts anything it could not find, which reads as a bug and
        //is laminas' behaviour; nothing here selects that mode.
        if (self::COMPARE_NOT_STRICT === $this->strict) {
            return true;
        }

        $this->error(self::NOT_IN_ARRAY);

        return false;
    }
}
