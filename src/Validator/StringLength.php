<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function function_exists;
use function grapheme_strlen;
use function is_int;
use function is_string;
use function max;
use function mb_strlen;
use function strtoupper;

/**
 * The value fits between a minimum and a maximum number of **characters**.
 *
 * 123 specification entries, almost all of them a `max` matching a column's width — which
 * is what makes the encoding load-bearing rather than decorative. `mb_strlen` with UTF-8
 * counts characters; `strlen` counts bytes; and MySQL's `varchar(200)` counts characters
 * too. Get that wrong and a name with four accents is rejected at 196 letters, or accepted
 * at 200 and truncated on insert.
 *
 * A non-string fails outright rather than being cast, which is what keeps an array posted
 * to a text field from passing a length check as the string "Array".
 */
final class StringLength extends AbstractValidator
{
    public const INVALID   = 'stringLengthInvalid';
    public const TOO_SHORT = 'stringLengthTooShort';
    public const TOO_LONG  = 'stringLengthTooLong';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID   => 'Invalid type given. String expected',
        self::TOO_SHORT => 'The input is less than %min% characters long',
        self::TOO_LONG  => 'The input is more than %max% characters long',
    ];

    /** @var array<string, array<string, string>> */
    protected $messageVariables = [
        'min'    => ['options' => 'min'],
        'max'    => ['options' => 'max'],
        'length' => ['options' => 'length'],
    ];

    /** @var array{min: int, max: int|null, encoding: string, length: int|null} */
    protected $options = [
        'min'      => 0,
        'max'      => null,
        'encoding' => 'UTF-8',
        'length'   => 0,
    ];

    public function setMin(mixed $min): static
    {
        $max = $this->options['max'];

        if (null !== $max && (int) $min > $max) {
            throw new Exception\InvalidArgumentException(
                "The minimum must be less than or equal to the maximum length, but {$min} > {$max}"
            );
        }

        $this->options['min'] = max(0, (int) $min);

        return $this;
    }

    public function setMax(mixed $max): static
    {
        if (null === $max) {
            $this->options['max'] = null;

            return $this;
        }

        if ((int) $max < $this->options['min']) {
            throw new Exception\InvalidArgumentException(
                "The maximum must be greater than or equal to the minimum length, but {$max} < {$this->options['min']}"
            );
        }

        $this->options['max'] = (int) $max;

        return $this;
    }

    public function setEncoding(mixed $encoding): static
    {
        $this->options['encoding'] = is_string($encoding) && '' !== $encoding ? $encoding : 'UTF-8';

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        if (! is_string($value)) {
            $this->error(self::INVALID);

            return false;
        }

        $this->setValue($value);
        $this->options['length'] = self::lengthOf($value, $this->options['encoding']);

        //Both bounds are reported, not the first: a value can be neither long enough nor
        //short enough only in a range, but a chain that stopped at the first would hide the
        //second the day one is added.
        if ($this->options['length'] < $this->options['min']) {
            $this->error(self::TOO_SHORT);
        }

        if (null !== $this->options['max'] && $this->options['max'] < $this->options['length']) {
            $this->error(self::TOO_LONG);
        }

        return [] === $this->getMessages();
    }

    /**
     * How long a string is, counted the way laminas counted it.
     *
     * `Laminas\Stdlib\StringUtils::getWrapper()` tries the **Intl** wrapper first, and it
     * counts `grapheme_strlen()` — user-perceived characters — not code points. The two
     * disagree on exactly the values a person pastes: `"e\u{0301}"` is one grapheme and two
     * code points, so a `min => 4` bound rejects a four-letter accented name that
     * `mb_strlen` would have passed.
     *
     * `grapheme_strlen()` also answers `null` for invalid UTF-8, and that null is kept: it
     * compares below every minimum and above no maximum, which is what laminas recorded and
     * what `test/Rules/rule-surface.php` holds for the three broken-byte corpus values.
     *
     * Only for UTF-8. Any other encoding falls to `mb_strlen`, which is the second wrapper
     * in laminas' list.
     */
    private static function lengthOf(string $value, string $encoding): ?int
    {
        if ('UTF-8' === strtoupper($encoding) && function_exists('grapheme_strlen')) {
            $length = grapheme_strlen($value);

            return is_int($length) ? $length : null;
        }

        return mb_strlen($value, $encoding);
    }
}
