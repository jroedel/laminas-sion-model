<?php

declare(strict_types=1);

namespace SionModel\Validator;

use SionModel\Uri\Http;
use Throwable;

use function is_string;

/**
 * The value is a URL.
 *
 * Fourteen uses, every one of them `allowAbsolute => true, allowRelative => false`.
 * laminas also took a `uriHandler`, and needed it: given none it validated through the
 * generic `Laminas\Uri\Uri`, which accepts `javascript:` and `mailto:` as perfectly good
 * URIs. {@see \SionModel\Uri\Http} is what this class is built on and there is no generic
 * mode to fall into, so the fourteen elements that carried the option lose it.
 *
 * ## It accepts more than it looks like it accepts
 *
 * `this is not a url` is refused only because it is not absolute. Turn `allowRelative` on
 * and it passes, and `SionModel\Db\Model\SionTable::filterUrl()` — which has no validator
 * in front of it on the API path — stores it as `this%20is%20not%20a%20url`. That is
 * recorded in `test/Rules/uri-surface.php` rather than fixed here: what a URL field should
 * accept is a decision about the forms and the API, not about this class.
 */
final class Uri extends AbstractValidator
{
    public const INVALID = 'uriInvalid';
    public const NOT_URI = 'notUri';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID => 'Invalid type given. String expected',
        self::NOT_URI => 'The input does not appear to be a valid Uri',
    ];

    private bool $allowAbsolute = true;

    private bool $allowRelative = true;

    public function setAllowAbsolute(mixed $allow): static
    {
        $this->allowAbsolute = (bool) $allow;

        return $this;
    }

    public function setAllowRelative(mixed $allow): static
    {
        $this->allowRelative = (bool) $allow;

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

        try {
            $uri = new Http($value);

            if ($uri->isValid()) {
                if (
                    ($this->allowRelative && $this->allowAbsolute)
                    || ($this->allowAbsolute && $uri->isAbsolute())
                    || ($this->allowRelative && $uri->isValidRelative())
                ) {
                    return true;
                }
            }
        } catch (Throwable) {
            //A scheme this class refuses — `mailto:`, `javascript:` — arrives as an
            //exception and is simply not a valid URI. Catching Throwable rather than the
            //URI exception: a malformed value can also raise from the encoder, and the
            //answer is the same either way.
        }

        $this->error(self::NOT_URI);

        return false;
    }
}
