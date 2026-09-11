<?php

declare(strict_types=1);

namespace SionModel\Filter;

use function is_string;
use function mb_strtoupper;
use function preg_quote;
use function preg_replace_callback;

/**
 * `some words here` → `SomeWordsHere`.
 *
 * Exists for one subclass, {@see MixedCase}, which lowercases the first letter again to
 * produce `someWordsHere` — the shape `SionTable` uses for an entity's field names.
 *
 * `\P{Z}` under `/u` rather than `\S`: laminas branched on whether PCRE had Unicode support
 * and took the ASCII path otherwise. PCRE here is built with it — every PHP this has ever
 * run on has been — so the branch is not reproduced and the Unicode form is the only one.
 */
class SeparatorToCamelCase extends AbstractFilter
{
    /** @var array{separator: string} */
    protected $options = ['separator' => ' '];

    /**
     * The separator, or an options array carrying one.
     *
     * Both spellings, because both are in the source: `new MixedCase('_')` in
     * `SionModel\Entity\Entity` and `Schoenstatt\Model\AssociationKind`, and the options
     * array everywhere a name is resolved. laminas' `AbstractSeparator` took the same two
     * and dropping the string form turned every entity's field-name derivation into a
     * "expects an array of options; received string" the moment the container was built.
     *
     * @param array{separator?: string}|string|null $separatorOrOptions
     */
    public function __construct($separatorOrOptions = ' ')
    {
        if (is_string($separatorOrOptions)) {
            $this->setSeparator($separatorOrOptions);

            return;
        }

        parent::__construct($separatorOrOptions);
    }

    public function setSeparator(mixed $separator): static
    {
        $this->options['separator'] = is_string($separator) ? $separator : ' ';

        return $this;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $separator = preg_quote($this->options['separator'], '#');

        $value = preg_replace_callback(
            '#(' . $separator . ')(\P{Z}{1})#u',
            static fn(array $matches): string => mb_strtoupper($matches[2], 'UTF-8'),
            $value
        ) ?? $value;

        return preg_replace_callback(
            '#(^\P{Z}{1})#u',
            static fn(array $matches): string => mb_strtoupper($matches[1], 'UTF-8'),
            $value
        ) ?? $value;
    }
}
