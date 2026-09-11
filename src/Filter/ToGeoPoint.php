<?php

namespace SionModel\Filter;

use Laminas\Validator\GpsPoint;
use SionModel\Db\GeoPoint;

use function is_string;
use function preg_match_all;
use function trim;

/**
 * Turn a submitted "latitude, longitude" string into a GeoPoint.
 *
 * Paired with Laminas\Validator\GpsPoint on the same input, and the pairing only
 * works because of what this filter hands back when it cannot convert.
 *
 * It used to return null for *anything* it could not parse, which made a bad
 * coordinate invisible rather than wrong. Walking through what that did on the
 * association form, whose geoPoint field is the only user of this filter and is
 * where a shrine's location is recorded:
 *
 *   'asdf' -> null -> the input is `required => false`, so laminas treats the
 *   empty filtered value as "not submitted" and drops the key from getData()
 *   entirely -> SionTable::updateHelper() only writes keys that are present, so
 *   the *previous* coordinate stays in the database -> and no validator ever sees
 *   a value to complain about, so there is no message.
 *
 * The moderator types a new coordinate, the form reports success, and the map
 * still shows the old location. On a create it is stored as nothing at all. That
 * is the worst shape a validation failure can take: silent, and it looks like it
 * worked.
 *
 * So a non-empty value this filter cannot convert is returned **unchanged**, and
 * GpsPoint then rejects it and the field gets an error. Only genuinely empty
 * input becomes null, which is what "no location recorded" means.
 *
 * The same division of labour between a filter that must be total and a validator
 * that reports is written out at length in SionModel\Filter\DateTimeParser.
 */
class ToGeoPoint extends AbstractFilter
{
    /**
     * Never throws: a filter runs inside InputFilter::isValid(), so anything
     * thrown here escapes as an uncaught exception — a 500 with the user's whole
     * submission lost.
     *
     * @param  mixed $value
     * @return GeoPoint|null|mixed
     */
    public function filter($value)
    {
        //A non-string is nulled rather than handed on, and the asymmetry with a
        //bad *string* below is deliberate. Laminas\Validator\GpsPoint raises a
        //TypeError on an array, which would escape isValid() as a 500 — the very
        //failure the rest of this filter exists to avoid — and unlike 'asdf' an
        //array is not something a person typed into a coordinate box. Only
        //`geoPoint[]=x` in a crafted request produces one, so there is no user to
        //show a message to. Same reasoning as SionForm's DateSelect blanking.
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        static $validator;
        if (! isset($validator)) {
            $validator = new GpsPoint();
        }
        if (! $validator->isValid($trimmed)) {
            //Unconvertible, but the user typed something. Give it back so the
            //GpsPoint validator on this input can report it.
            return $value;
        }

        //Only reached for a string GpsPoint has already accepted, which is what
        //makes this loose numeric extraction safe: both numbers are known to be
        //present and in range.
        $matches = null;
        preg_match_all('/[0-9\.-]+/u', $trimmed, $matches, PREG_SET_ORDER, 0);

        $latitude  = isset($matches[0]) ? $matches[0][0] : 0;
        $longitude = isset($matches[1]) ? $matches[1][0] : 0;

        //GeoPoint takes longitude first and stringifies as "latitude,longitude",
        //which is why GpsPoint accepts the object this returns as well as the
        //string it came from.
        return new GeoPoint($longitude, $latitude);
    }
}
