<?php

namespace SionModel\Validator;

use Laminas\Validator\AbstractValidator;
use Laminas\Validator\Exception\InvalidArgumentException;

use function is_float;
use function is_int;
use function is_string;
use function preg_match;

/**
 * Base for validators that pair one fixed regular expression with a
 * human-readable failure message.
 *
 * These all used to extend Laminas\Validator\Regex. laminas marked Regex
 * `@final` (it is soft-deprecated for inheritance and closes in 3.0), so they
 * were moved down onto AbstractValidator, which stays open. That keeps
 * everything the plugin manager and InputFilter rely on — translator
 * awareness, message templates, option handling — while dropping the
 * inheritance laminas no longer supports.
 *
 * The behaviour of isValid() and the three error keys are reproduced from
 * Regex verbatim; see test/Integration/PatternValidatorContractTest.php in the
 * application repo, which pins all six subclasses.
 */
abstract class AbstractPatternValidator extends AbstractValidator
{
    /**
     * Error keys are Laminas\Validator\Regex's own strings, kept byte-identical
     * so anything reading message keys — rather than message text — keeps
     * working across the base-class change.
     */
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
    protected $messageVariables = [
        'pattern' => 'pattern',
    ];

    /**
     * The regular expression this validator matches against.
     *
     * @var non-empty-string
     */
    protected $pattern;

    /**
     * @param non-empty-string $pattern
     * @param string|null      $message Replaces all three templates when given —
     *                                  subclasses exist to say something more
     *                                  useful than "does not match pattern".
     * @throws InvalidArgumentException If the pattern is not a usable regex.
     */
    public function __construct(string $pattern, ?string $message = null)
    {
        $this->setPattern($pattern);

        if (null !== $message) {
            $this->messageTemplates[self::INVALID]   = $message;
            $this->messageTemplates[self::NOT_MATCH] = $message;
            $this->messageTemplates[self::ERROROUS]  = $message;
        }

        parent::__construct([]);
    }

    /**
     * @return non-empty-string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @param non-empty-string $pattern
     * @throws InvalidArgumentException If the pattern is not a usable regex.
     */
    protected function setPattern(string $pattern): void
    {
        // preg_match() emits a warning and returns false for an unusable
        // pattern; suppress the warning and report it as an exception, the way
        // Regex::setPattern() did.
        if (false === @preg_match($pattern, 'Test')) {
            throw new InvalidArgumentException("Internal error parsing the pattern '{$pattern}'");
        }

        $this->pattern = $pattern;
    }

    /**
     * Returns true if and only if $value matches against the pattern.
     *
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value)
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

        if (! $status) {
            $this->error(self::NOT_MATCH);
            return false;
        }

        return true;
    }
}
