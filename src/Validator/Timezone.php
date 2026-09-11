<?php

declare(strict_types=1);

namespace SionModel\Validator;

use DateTimeZone;

use function array_key_exists;
use function in_array;
use function is_string;

/**
 * A timezone, named either as a location or as an abbreviation.
 *
 * One use, in `Schoenstatt\Model\SchoenstattTable`, checking what a shrine's row claims
 * before it is handed to `DateTimeZone`. Both kinds are accepted, which is laminas'
 * default: `Europe/Berlin` is a location, `CET` an abbreviation, and the two lists are
 * different questions to PHP.
 *
 * The `type` option is not reproduced — the single use takes the default — so the two
 * narrower messages are unreachable and are kept only because they are in the catalogs.
 */
final class Timezone extends AbstractValidator
{
    public const INVALID                       = 'invalidTimezone';
    public const INVALID_TIMEZONE_LOCATION     = 'invalidTimezoneLocation';
    public const INVALID_TIMEZONE_ABBREVIATION = 'invalidTimezoneAbbreviation';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::INVALID                       => 'Invalid timezone given.',
        self::INVALID_TIMEZONE_LOCATION     => 'Invalid timezone location given.',
        self::INVALID_TIMEZONE_ABBREVIATION => 'Invalid timezone abbreviation given.',
    ];

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        if (null !== $value && ! is_string($value)) {
            $this->error(self::INVALID);

            return false;
        }

        $this->setValue($value);

        if (
            ! array_key_exists((string) $value, DateTimeZone::listAbbreviations())
            && ! in_array($value, DateTimeZone::listIdentifiers(), false)
        ) {
            $this->error(self::INVALID);

            return false;
        }

        return true;
    }
}
