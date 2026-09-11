<?php

declare(strict_types=1);

namespace SionModel\Filter;

/**
 * A filter turns a submitted value into the value that is stored.
 *
 * The same shape as `Laminas\Filter\FilterInterface`, deliberately: 23 classes across four
 * repositories implement it, and an untyped `filter($value)` is what lets every one of them
 * change one `use` line and nothing else. Typing it `mixed $value): mixed` would be a nicer
 * interface and a worse migration — every implementation would have to be touched, and each
 * touch is a chance to change behaviour while claiming not to.
 */
interface FilterInterface
{
    /**
     * The filtered value.
     *
     * A filter that cannot make sense of a value **returns it unchanged**; it does not
     * throw and it does not report. Rejecting a value is a validator's job, and the two
     * halves running in that order — every filter, then every validator, on the filtered
     * value — is the engine's contract.
     *
     * @param mixed $value
     * @return mixed
     */
    public function filter($value);
}
