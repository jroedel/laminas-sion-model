<?php

declare(strict_types=1);

namespace SionModel\Validator;

use SionModel\Validator\Exception\InvalidArgumentException;

use function class_exists;
use function is_a;
use function sprintf;

/**
 * The one place a validator's name becomes a validator.
 *
 * The twin of {@see \SionModel\Filter\Registry}, and there are two of them for the reason
 * laminas had two plugin managers: a specification names a filter and a validator the same
 * way — a short name and an options array — and only the position in the specification says
 * which kind is meant.
 *
 * `get()` falls through to its argument when the map has no entry, so a specification may
 * also name any class implementing {@see ValidatorInterface} — which is how the twelve
 * validators with no short name, SionModel's own and Schoenstatt's alike, are reached.
 */
final class Registry
{
    /**
     * Short name => class, for the names a specification is allowed to write short.
     *
     * @var array<string, class-string<ValidatorInterface>>
     */
    private const NAMES = [
        'Csrf'           => Csrf::class,
        'Date'           => Date::class,
        'Digits'         => Digits::class,
        'EmailAddress'   => EmailAddress::class,
        'Explode'        => Explode::class,
        'GpsPoint'       => GpsPoint::class,
        'GreaterThan'    => GreaterThan::class,
        'Identical'      => Identical::class,
        'InArray'        => InArray::class,
        'LessThan'       => LessThan::class,
        'NotEmpty'       => NotEmpty::class,
        'Regex'          => Regex::class,
        'Step'           => Step::class,
        'StringLength'   => StringLength::class,
        'Timezone'       => Timezone::class,
        'Uri'            => Uri::class,
        'NoRecordExists' => Db\NoRecordExists::class,
        'RecordExists'   => Db\RecordExists::class,
    ];

    /**
     * @param array<string, mixed> $options
     * @throws InvalidArgumentException
     */
    public static function get(string $name, array $options = []): ValidatorInterface
    {
        $class = self::NAMES[$name] ?? $name;

        if (! class_exists($class) || ! is_a($class, ValidatorInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a validator this application knows. A specification may name one of '
                . 'the short names in %s, or any class implementing %s.',
                $name,
                self::class,
                ValidatorInterface::class
            ));
        }

        //`Regex` is the one validator whose constructor is mandatory — it has no usable
        //default — so an empty options array still goes to `new`.
        return Regex::class === $class ? new $class($options) : ([] === $options ? new $class() : new $class($options));
    }
}
