<?php

declare(strict_types=1);

namespace SionModel\Data;

use function array_key_exists;
use function is_array;
use function is_int;

/**
 * Merge two arrays the way configuration and problem reports need it merged.
 *
 * `Laminas\Stdlib\ArrayUtils::merge()` until 2026-09-22, less the two marker-object
 * branches. `MergeReplaceKey` and `MergeRemoveKey` are used by no config file in this
 * application or in the three submodules — checked at the cutover — and they were the
 * only reason the rule needed laminas-stdlib's classes rather than the rule itself.
 *
 * **The clause that matters is the integer one: a list under a shared key appends.**
 * That is the whole difference from the three built-ins, and every caller depends on it:
 *
 * | `['a' => [1]]` + `['a' => [2]]` | result |
 * | --- | --- |
 * | `array_merge` | `['a' => [2]]` — the first list is gone |
 * | `array_merge_recursive` | `['a' => [1, 2]]`, but it also turns two *scalars* under one key into a list |
 * | `array_replace_recursive` | `['a' => [2]]` — element-wise, so a shorter list leaves the longer one's tail behind |
 * | this | `['a' => [1, 2]]` |
 *
 * Two callers, and each would break differently without it: merged module configuration,
 * where a second module's `factories` must join the first's rather than replace them; and
 * {@see \SionModel\Service\ProblemService}, where two providers reporting on the same
 * entity must both be heard.
 */
final class ArrayMerge
{
    /**
     * @param array<array-key, mixed> $a
     * @param array<array-key, mixed> $b
     * @return array<array-key, mixed>
     */
    public static function merge(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            if (array_key_exists($key, $a)) {
                if (is_int($key)) {
                    $a[] = $value;
                } elseif (is_array($value) && is_array($a[$key])) {
                    $a[$key] = self::merge($a[$key], $value);
                } else {
                    $a[$key] = $value;
                }
            } else {
                $a[$key] = $value;
            }
        }

        return $a;
    }
}
