<?php

namespace SionModel\Error;

/**
 * Privacy filters applied to captured request state.
 *
 * Deliberately dependency-free: the exception store is fetched off the
 * production server and mailed around, so what these methods drop is the
 * difference between a debugging aid and a personal-data spill. Keeping the
 * rules in a class with no framework imports means the unit tests can require
 * this one file and assert on them without a booted application.
 */
class Redactor
{
    /** Placeholder written in place of a submitted value. */
    public const REDACTED = '<redacted>';

    /**
     * Reduce a client address to something that still distinguishes "one
     * visitor keeps hitting this" from "everyone hits this", without storing a
     * personal identifier.
     *
     * 'truncate' zeroes the last IPv4 octet (/24) and keeps only the first
     * three IPv6 hextets (/48). 'full' stores the address verbatim — only
     * appropriate where you have a lawful basis for it. 'none' drops it.
     *
     * @param string|null $ip
     * @param string      $mode one of truncate|full|none
     * @return string|null
     */
    public static function ip($ip, $mode = 'truncate')
    {
        if (! is_string($ip) || '' === $ip || 'none' === $mode) {
            return null;
        }
        if ('full' === $mode) {
            return $ip;
        }
        if (false !== strpos($ip, ':')) {
            $hextets = explode(':', $ip);
            return implode(':', array_slice($hextets, 0, 3)) . '::/48';
        }
        $octets = explode('.', $ip);
        if (4 !== count($octets)) {
            //not an address we recognise; don't guess, drop it
            return null;
        }
        $octets[3] = '0';
        return implode('.', $octets) . '/24';
    }

    /**
     * Record which parameters were submitted without recording what was in
     * them.
     *
     * The login form posts a password and the exception mail is not a safe
     * place for it, so 'keys' (the default) keeps every key — including nested
     * ones, flattened to dotted paths — and replaces each scalar with a
     * redaction marker carrying only the value's length. That is enough to
     * tell an empty field from a filled one, or a 4-character search term from
     * a 400-character paste, which is usually what you need to reproduce.
     *
     * @param array  $params
     * @param string $mode one of keys|full|none
     * @return array
     */
    public static function params(array $params, $mode = 'keys')
    {
        if ('none' === $mode) {
            return [];
        }
        $flat = self::flatten($params);
        if ('full' === $mode) {
            return $flat;
        }
        $redacted = [];
        foreach ($flat as $key => $value) {
            $redacted[$key] = self::describe($value);
        }
        return $redacted;
    }

    /**
     * Flatten a nested parameter array to dotted paths, so a write-up stays a
     * flat readable list instead of a var_dump.
     *
     * @param array  $params
     * @param string $prefix
     * @return array
     */
    public static function flatten(array $params, $prefix = '')
    {
        $flat = [];
        foreach ($params as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                if ([] === $value) {
                    $flat[$path] = '<empty array>';
                    continue;
                }
                $flat = array_merge($flat, self::flatten($value, $path));
                continue;
            }
            $flat[$path] = $value;
        }
        return $flat;
    }

    /**
     * Describe a single submitted value without disclosing it.
     *
     * @param mixed $value
     * @return string
     */
    public static function describe($value)
    {
        if (null === $value) {
            return '<null>';
        }
        if (is_bool($value)) {
            return $value ? '<true>' : '<false>';
        }
        if (is_object($value)) {
            //an uploaded file, typically
            return '<' . get_class($value) . '>';
        }
        if (! is_scalar($value)) {
            return '<' . gettype($value) . '>';
        }
        $string = (string) $value;
        if ('' === $string) {
            return '<empty>';
        }
        return self::REDACTED . ':' . strlen($string);
    }
}
