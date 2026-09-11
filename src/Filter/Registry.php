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
 * ## The laminas aliases, and when they go
 *
 * Ninety specification entries still name `Laminas\Filter\StringTrim` and its siblings, and
 * they resolve here to ours. That is deliberate and temporary: it lets the rules be
 * replaced and measured in one commit, and the 110 files that spell the old names be
 * rewritten in another, so a behaviour change and a rename are never the same diff. They
 * leave with the package.
 */
final class Registry
{
    /**
     * Short name => class, and the laminas class names that mean the same thing.
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

        //`Int` is laminas' own alias for `ToInt`, and one specification uses it.

        'Laminas\Filter\Boolean'                   => Boolean::class,
        'Laminas\Filter\Callback'                  => Callback::class,
        'Laminas\Filter\DateSelect'                => DateSelect::class,
        'Laminas\Filter\PregReplace'               => PregReplace::class,
        'Laminas\Filter\StringToLower'             => StringToLower::class,
        'Laminas\Filter\StringToUpper'             => StringToUpper::class,
        'Laminas\Filter\StringTrim'                => StringTrim::class,
        'Laminas\Filter\StripNewlines'             => StripNewlines::class,
        'Laminas\Filter\StripTags'                 => StripTags::class,
        'Laminas\Filter\ToInt'                     => ToInt::class,
        'Laminas\Filter\ToNull'                    => ToNull::class,
        'Laminas\Filter\Word\SeparatorToCamelCase' => SeparatorToCamelCase::class,
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
