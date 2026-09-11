<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function count;
use function explode;
use function function_exists;
use function idn_to_ascii;
use function is_string;
use function mb_strlen;
use function preg_match;
use function str_contains;
use function strlen;

use const INTL_IDNA_VARIANT_UTS46;

/**
 * An email address: a local part, an `@`, and a hostname.
 *
 * ## The one place this library is deliberately not a reproduction
 *
 * `SionModel\Validator\EmailAddress` is 598 lines and delegates the hostname to
 * `Laminas\Validator\Hostname`, which is 2,279 — and roughly two thousand of those are a
 * **table of every top-level domain**, in ASCII and in a dozen scripts. Carrying it would
 * make this library a thing that has to be maintained against ICANN; not carrying it is a
 * decision taken deliberately on 2026-09-11 and recorded here rather than discovered later.
 *
 * So the local part is transcribed exactly — the dot-atom and quoted-string productions of
 * RFC 5321, character for character — and the hostname is checked for **shape**: labels of
 * letters, digits and hyphens, at least two of them, a TLD of two to sixty-three
 * characters. What that changes:
 *
 * - `user@example.invalidtld` is **accepted** here and was rejected there. A well-formed
 *   address at a TLD younger than the table is the case the table gets wrong, and the cost
 *   of being wrong the other way — refusing a real address — is borne by a person who
 *   cannot then be contacted.
 * - Nothing else in the corpus moves; `test/Rules/rule-surface.php` is the record.
 *
 * The MX and deep-MX checks are not reproduced either: both default to off, neither is
 * switched on anywhere, and both make a validator perform a DNS lookup while a visitor
 * waits.
 *
 * ## The message keys are unchanged
 *
 * `emailAddressInvalidFormat` is what `test/Form/engine-surface.php` records for seven
 * fields and what five catalogs translate, so the keys and their English text stay as they
 * are even where the check behind them has narrowed.
 */
final class EmailAddress extends AbstractValidator
{
    public const INVALID            = 'emailAddressInvalid';
    public const INVALID_FORMAT     = 'emailAddressInvalidFormat';
    public const INVALID_HOSTNAME   = 'emailAddressInvalidHostname';
    public const INVALID_LOCAL_PART = 'emailAddressInvalidLocalPart';
    public const DOT_ATOM           = 'emailAddressDotAtom';
    public const QUOTED_STRING      = 'emailAddressQuotedString';
    public const LENGTH_EXCEEDED    = 'emailAddressLengthExceeded';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID            => 'Invalid type given. String expected',
        self::INVALID_FORMAT     => 'The input is not a valid email address. Use the basic format local-part@hostname',
        self::INVALID_HOSTNAME   => "'%hostname%' is not a valid hostname for the email address",
        self::DOT_ATOM           => "'%localPart%' can not be matched against dot-atom format",
        self::QUOTED_STRING      => "'%localPart%' can not be matched against quoted-string format",
        self::INVALID_LOCAL_PART => "'%localPart%' is not a valid local part for the email address",
        self::LENGTH_EXCEEDED    => 'The input exceeds the allowed length',
    ];

    /** @var array<string, string> */
    protected $messageVariables = [
        'hostname'  => 'hostname',
        'localPart' => 'localPart',
    ];

    protected string $hostname = '';

    protected string $localPart = '';

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

        //'..' is refused outright rather than by the local-part production, because it is
        //also how a hostname is made to look like a shorter one.
        if (str_contains($value, '..') || ! preg_match('/^(.+)@([^@]+)$/', $value, $matches)) {
            $this->error(self::INVALID_FORMAT);

            return false;
        }

        $this->localPart = $matches[1];
        $this->hostname  = self::toAscii($matches[2]);

        $local = $this->isValidLocalPart();
        $host  = $this->isValidHostname();

        return $local && $host;
    }

    /**
     * The dot-atom production, then the quoted-string one.
     *
     * Both are transcribed from RFC 5321 as laminas transcribed them, including the three
     * messages a failure reports — the general one and the two specific ones — because a
     * form renders whichever it is given.
     */
    private function isValidLocalPart(): bool
    {
        $atext = 'a-zA-Z0-9\x21\x23\x24\x25\x26\x27\x2a\x2b\x2d\x2f\x3d\x3f\x5e\x5f\x60\x7b\x7c\x7d\x7e';

        if (preg_match('/^[' . $atext . ']+(\x2e+[' . $atext . ']+)*$/', $this->localPart)) {
            return true;
        }

        $qtext      = '\x20-\x21\x23-\x5b\x5d-\x7e';
        $quotedPair = '\x20-\x7e';

        if (preg_match('/^"([' . $qtext . ']|\x5c[' . $quotedPair . '])*"$/', $this->localPart)) {
            return true;
        }

        $this->error(self::DOT_ATOM);
        $this->error(self::QUOTED_STRING);
        $this->error(self::INVALID_LOCAL_PART);

        return false;
    }

    /**
     * At least two labels, each well formed, and a plausible top-level one.
     *
     * The bounds are laminas': 4 to 254 characters overall, 1 to 63 per label, 2 to 63 for
     * the last. What is missing, on purpose, is the check that the last label is a
     * registered TLD.
     */
    private function isValidHostname(): bool
    {
        $length = mb_strlen($this->hostname, 'UTF-8');

        if ($length < 4 || $length > 254) {
            $this->error(self::INVALID_HOSTNAME);

            return false;
        }

        $labels = explode('.', $this->hostname);

        if (count($labels) < 2) {
            $this->error(self::INVALID_HOSTNAME);

            return false;
        }

        foreach ($labels as $label) {
            if (! preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', $label)) {
                $this->error(self::INVALID_HOSTNAME);

                return false;
            }
        }

        if (! preg_match('/^[a-zA-Z]{2,63}$/', $labels[count($labels) - 1])) {
            $this->error(self::INVALID_HOSTNAME);

            return false;
        }

        return true;
    }

    /**
     * An internationalised hostname as punycode, so the label check can be ASCII.
     *
     * Without intl — which production and the capsule both have — the name is left as it
     * came and the label check refuses it, which is laminas' behaviour too.
     */
    private static function toAscii(string $hostname): string
    {
        if (! function_exists('idn_to_ascii')) {
            return $hostname;
        }

        $ascii = idn_to_ascii($hostname, 0, INTL_IDNA_VARIANT_UTS46);

        return false === $ascii ? $hostname : $ascii;
    }
}
