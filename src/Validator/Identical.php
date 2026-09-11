<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function array_key_exists;
use function is_array;
use function var_export;

/**
 * The value equals a token.
 *
 * Two uses, both `literal => true`: the library-deletion form makes you type the library's
 * name, and one text form matches a fixed key. `literal` is what makes the token a value
 * rather than a **context key** — without it, laminas looks the token up among the sibling
 * fields, which is how a "confirm your password" pair is normally written.
 *
 * Both spellings are honoured because both are in the contract, and the context lookup is
 * reproduced even though nothing uses it today: it is two lines, and a rule that silently
 * compares against the literal string `'password'` is a security hole rather than a bug.
 */
final class Identical extends AbstractValidator
{
    public const NOT_SAME      = 'notSame';
    public const MISSING_TOKEN = 'missingToken';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::NOT_SAME      => 'The two given tokens do not match',
        self::MISSING_TOKEN => 'No token was provided to match against',
    ];

    /** @var array<string, string> */
    protected $messageVariables = ['token' => 'tokenString'];

    protected string $tokenString = '';

    /** @var mixed */
    private mixed $token = null;

    private bool $strict = true;

    private bool $literal = false;

    public function setToken(mixed $token): static
    {
        $this->tokenString = is_array($token) ? var_export($token, true) : (string) $token;
        $this->token       = $token;

        return $this;
    }

    public function setStrict(mixed $strict): static
    {
        $this->strict = (bool) $strict;

        return $this;
    }

    public function setLiteral(mixed $literal): static
    {
        $this->literal = (bool) $literal;

        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        $this->setValue($value);

        $token = $this->token;

        if (! $this->literal && is_array($context)) {
            //A token naming a sibling field: `['token' => 'passwordVerify']` compares this
            //field against that one's submitted value. A dotted path walks nested
            //fieldsets, which is how laminas spelled it.
            if (array_key_exists((string) $token, $context)) {
                $token = $context[(string) $token];
            }
        }

        if (null === $token) {
            $this->error(self::MISSING_TOKEN);

            return false;
        }

        $same = $this->strict ? $value === $token : $value == $token;

        if (! $same) {
            $this->error(self::NOT_SAME);

            return false;
        }

        return true;
    }
}
