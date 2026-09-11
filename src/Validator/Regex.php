<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function is_float;
use function is_int;
use function is_string;
use function preg_match;

/**
 * The value matches a pattern.
 *
 * 19 specification entries, and four of them override `regexNotMatch` with wording a
 * visitor can act on — "Please begin with '+' and the country code…" rather than "The input
 * does not match against pattern '/…/'". The default template prints the pattern, which is
 * developer-facing text that has been reaching visitors for years; it is reproduced rather
 * than improved, because improving it is a decision about fifteen forms.
 */
final class Regex extends AbstractValidator
{
    public const INVALID   = 'regexInvalid';
    public const NOT_MATCH = 'regexNotMatch';
    public const ERROROUS  = 'regexErrorous';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID   => 'Invalid type given. String, integer or float expected',
        self::NOT_MATCH => "The input does not match against pattern '%pattern%'",
        self::ERROROUS  => "There was an internal error while using the pattern '%pattern%'",
    ];

    /** @var array<string, string> */
    protected $messageVariables = ['pattern' => 'pattern'];

    protected string $pattern = '';

    /**
     * A pattern, or an options array carrying one.
     *
     * Both spellings appear: `new Regex($pattern)` inside `InputTypeRules`, and
     * `['name' => Regex::class, 'options' => ['pattern' => …]]` in every specification.
     *
     * @param array<string, mixed>|string $patternOrOptions
     */
    public function __construct($patternOrOptions)
    {
        if (is_string($patternOrOptions)) {
            $this->setPattern($patternOrOptions);

            return;
        }

        if (! isset($patternOrOptions['pattern']) || ! is_string($patternOrOptions['pattern'])) {
            throw new Exception\InvalidArgumentException("Missing option 'pattern'");
        }

        parent::__construct($patternOrOptions);
    }

    public function setPattern(mixed $pattern): static
    {
        $pattern = (string) $pattern;

        //Compiled once, here, so that a broken pattern is a fatal at form-construction
        //time rather than a silently failing check at submission time. `@` because a bad
        //pattern warns as well as returning false, and the exception is the report.
        if (false === @preg_match($pattern, 'Test')) {
            throw new Exception\InvalidArgumentException("Internal error parsing the pattern '{$pattern}'");
        }

        $this->pattern = $pattern;

        return $this;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

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

        $this->setValue($value);

        $status = @preg_match($this->pattern, (string) $value);

        if (false === $status) {
            $this->error(self::ERROROUS);

            return false;
        }

        if (0 === $status) {
            $this->error(self::NOT_MATCH);

            return false;
        }

        return true;
    }
}
