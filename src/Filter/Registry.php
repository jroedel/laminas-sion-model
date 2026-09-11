<?php

declare(strict_types=1);

namespace SionModel\Filter;

use SionModel\Filter\Exception\InvalidArgumentException;

use function class_exists;
use function is_a;
use function sprintf;

/**
 * The one place a filter's name becomes a filter.
 *
 * ## Why a flat map and not a plugin manager
 *
 * `Laminas\Filter\FilterPluginManager` is a `ServiceManager` with 60 aliases, an
 * abstract factory, an initializer and a plugin-manager-aware interface, and every one of
 * those exists to let an application add a filter through configuration. Nothing here does:
 * a specification names a filter by short name or by class, and both spellings are in the
 * source. So this is the map, and the map is readable.
 *
 * It also means an input filter no longer depends on module loading — which is what lets
 * the associations API validate with no merged config, no container and no session.
 *
 * ## A name that is not in the map is a class name
 *
 * `get()` falls through to the argument itself, so the ninety specification entries that
 * name a filter by class — and SionModel's own, which have no short name — need no entry
 * here. The map exists for the short names a specification is allowed to write.
 */
final class Registry
{
    /**
     * Short name => class. `Int` was laminas' own alias for `ToInt`, and one specification
     * still writes it.
     *
     * @var array<string, class-string<FilterInterface>>
     */
    private const NAMES = [
        'Boolean'       => Boolean::class,
        'Callback'      => Callback::class,
        'DateSelect'    => DateSelect::class,
        'Int'           => ToInt::class,
        'PregReplace'   => PregReplace::class,
        'StringToLower' => StringToLower::class,
        'StringToUpper' => StringToUpper::class,
        'StringTrim'    => StringTrim::class,
        'StripNewlines' => StripNewlines::class,
        'StripTags'     => StripTags::class,
        'ToInt'         => ToInt::class,
        'ToNull'        => ToNull::class,
    ];

    /**
     * @param array<string, mixed> $options
     * @throws InvalidArgumentException
     */
    public static function get(string $name, array $options = []): FilterInterface
    {
        $class = self::NAMES[$name] ?? $name;

        if (! class_exists($class) || ! is_a($class, FilterInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a filter this application knows. A specification may name one of '
                . 'the short names in %s, or any class implementing %s.',
                $name,
                self::class,
                FilterInterface::class
            ));
        }

        //No argument rather than an empty array, which is what laminas' InvokableFactory
        //does — and it is not cosmetic: several filters here take no constructor argument
        //at all, and handing one to `new` is only harmless because PHP ignores extra
        //arguments to a userland constructor.
        return [] === $options ? new $class() : new $class($options);
    }
}
