<?php

declare(strict_types=1);

namespace SionModel\Validator;

use function explode;
use function is_numeric;
use function is_object;
use function is_scalar;
use function method_exists;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function str_replace;

/**
 * A latitude and a longitude, comma-separated, in decimal degrees or in degrees-minutes-seconds.
 *
 * One use — a shrine's coordinates — paired with `SionModel\Filter\ToGeoPoint`, which
 * converts the same two formats into a `GeoPoint`. The pairing is the whole reason both
 * exist: the filter runs first and its output is what the validator judges.
 *
 * ## It raises on a non-string, and that is recorded rather than repaired
 *
 * `str_contains(null, ',')` is a TypeError, and `SionModel\Validator\GpsPoint` never guarded
 * it. `test/Integration/ToGeoPointFilterContractTest` says so in as many words and
 * `test/Rules/rule-surface.php` records the TypeError for eleven corpus values. Guarding it
 * here would be a better validator and a different one; the field is reached only after
 * `ToGeoPoint`, which returns a string or a `GeoPoint`.
 */
final class GpsPoint extends AbstractValidator
{
    public const OUT_OF_BOUNDS         = 'gpsPointOutOfBounds';
    public const CONVERT_ERROR         = 'gpsPointConvertError';
    public const INCOMPLETE_COORDINATE = 'gpsPointIncompleteCoordinate';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::OUT_OF_BOUNDS         => '%value% is out of Bounds.',
        self::CONVERT_ERROR         => '%value% can not converted into a Decimal Degree Value.',
        self::INCOMPLETE_COORDINATE => '%value% did not provided a complete Coordinate',
    ];

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        //`SionModel\Validator\GpsPoint` declares no `strict_types`, so anything PHP can turn
        //into a string did so on its way into `str_contains()`, and only an array or an
        //object without `__toString` raised. The coercion is reproduced here rather than
        //inherited, because this file does declare strict types and every other rule in the
        //library is better for it.
        //
        //`SionModel\Db\GeoPoint` is why the object case is not an afterthought: the paired
        //`ToGeoPoint` filter runs first and returns one, so on the association form this
        //validator is handed an object on every successful submission.
        if (null === $value || is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            $value = (string) $value;
        }

        if (! str_contains($value, ',')) {
            $this->error(self::INCOMPLETE_COORDINATE, $value);

            return false;
        }

        [$latitude, $longitude] = explode(',', $value);

        return $this->isValidCoordinate($latitude, 90.0) && $this->isValidCoordinate($longitude, 180.0);
    }

    private function isValidCoordinate(string $value, float $boundary): bool
    {
        //Assigned rather than passed through setValue(): the message must name the half
        //that failed, and setValue() would clear the other half's message.
        $this->value = $value;

        $value = preg_replace('/\s/', '', $value) ?? $value;

        $converted = self::isDegreesMinutesSeconds($value)
            ? self::fromDegreesMinutesSeconds($value)
            : str_replace('°', '', $value);

        if (false === $converted) {
            $this->error(self::CONVERT_ERROR);

            return false;
        }

        $degrees = (float) $converted;

        if (! is_numeric($converted) && 0.0 === $degrees) {
            $this->error(self::CONVERT_ERROR);

            return false;
        }

        if ($degrees < -$boundary || $degrees > $boundary) {
            $this->error(self::OUT_OF_BOUNDS);

            return false;
        }

        return true;
    }

    private static function isDegreesMinutesSeconds(string $value): bool
    {
        return preg_match('/([°\'"]+[NESW])/', $value) > 0;
    }

    private static function fromDegreesMinutesSeconds(string $value): float|false
    {
        $matches = [];
        $found   = preg_match_all('/(\d{1,3})°(\d{1,2})\'(\d{1,2}[\.\d]{0,6})"[NESW]/i', $value, $matches);

        if (false === $found || 0 === $found) {
            return false;
        }

        return $matches[1][0] + $matches[2][0] / 60 + ((float) $matches[3][0]) / 3600;
    }
}
