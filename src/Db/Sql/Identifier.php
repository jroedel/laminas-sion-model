<?php

declare(strict_types=1);

namespace SionModel\Db\Sql;

use function array_map;
use function array_flip;
use function preg_split;
use function strtolower;
use function explode;
use function implode;
use function str_contains;
use function str_replace;

/**
 * Quoting, and the one rule that matters: a backtick inside a name is doubled.
 *
 * MariaDB's own escape for an identifier delimiter is the delimiter twice, which is what
 * `Laminas\Db\Adapter\Platform\Mysql` did and what this reproduces. No name in this
 * application contains one; the doubling is here because the day one does, the alternative
 * is a syntax error at best.
 *
 * A dotted name is quoted segment by segment — `a.b` becomes `` `a`.`b` ``, not `` `a.b` `` —
 * and `*` is never quoted, because it is not a name.
 */
final class Identifier
{
    public static function quote(string $name): string
    {
        if ('*' === $name) {
            return $name;
        }

        if (str_contains($name, '.')) {
            return implode('.', array_map(
                static fn(string $part): string => self::quote($part),
                explode('.', $name)
            ));
        }

        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * Quote the names inside a fragment that also contains operators — a join's `ON`.
     *
     * `Laminas\Db\Adapter\Platform\Mysql::quoteIdentifierInFragment()`. The fragment is
     * split on everything that cannot appear in a name, and each piece is quoted unless it is
     * one of the words the caller has declared safe. It exists because four join conditions
     * in this application are written as SQL strings rather than as predicates; a fragment
     * containing anything else — a function call, a literal — comes back quoted as a name and
     * is a syntax error, which is the honest outcome for a string this was never meant to parse.
     *
     * @param list<string> $safeWords
     */
    public static function quoteFragment(string $fragment, array $safeWords = []): string
    {
        $safe = array_flip(array_map(strtolower(...), $safeWords)) + ['*' => 0, ' ' => 0, '.' => 0, 'as' => 0];

        $parts  = preg_split('/([^0-9,a-z,A-Z$_\-:])/i', $fragment, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $quoted = '';

        foreach ($parts ?: [] as $part) {
            $quoted .= isset($safe[strtolower($part)]) ? $part : '`' . str_replace('`', '``', $part) . '`';
        }

        return $quoted;
    }
}
