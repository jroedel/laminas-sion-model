<?php

namespace SionModel\Text;

use InvalidArgumentException;

/**
 * String helpers for view scripts, ported from Cake\Utility\Text (CakePHP 3.10).
 *
 * cakephp/utility declares `php >=5.6.0,<8.0.0`, so it blocked every PHP 8 target
 * while being used for nothing but four string functions. This is a deliberate
 * drop-in: the signatures and default behavior match Cake's, so call sites only
 * had to change their `use` statement.
 *
 * The `html` option of Cake's truncate()/highlight() is NOT implemented — no
 * caller used it, and it is the hairiest part of the original. Passing it throws
 * rather than silently returning differently-shaped output.
 */
class Text
{
    /**
     * Truncate a string to $length characters, appending an ellipsis.
     *
     * Options: `ellipsis` (default '...'), `exact` (default true — when false,
     * don't cut mid-word), `trimWidth` (default false — measure display width
     * rather than character count). $length INCLUDES the ellipsis.
     *
     * @param string $text
     * @param int $length
     * @param array $options
     * @return string
     */
    public static function truncate($text, $length = 100, array $options = [])
    {
        $options += [
            'ellipsis'  => '...',
            'exact'     => true,
            'trimWidth' => false,
        ];
        self::assertNoHtmlOption($options);

        if (self::strlen($text, $options) <= $length) {
            return $text;
        }
        $ellipsisLength = self::strlen($options['ellipsis'], $options);

        $result = self::substr($text, 0, $length - $ellipsisLength, $options);

        if (! $options['exact']) {
            if (self::substr($text, $length - $ellipsisLength, 1, $options) !== ' ') {
                $result = self::removeLastWord($result);
            }
            // If the result is empty, we don't need to count the ellipsis in the cut.
            if (! strlen($result)) {
                $result = self::substr($text, 0, $length, $options);
            }
        }

        return $result . $options['ellipsis'];
    }

    /**
     * Truncate a string to $length display columns rather than characters.
     *
     * @param string $text
     * @param int $length
     * @param array $options
     * @return string
     */
    public static function truncateByWidth($text, $length = 100, array $options = [])
    {
        return static::truncate($text, $length, ['trimWidth' => true] + $options);
    }

    /**
     * Wrap every occurrence of $phrase in $text with the `format` template,
     * where \1 is the matched text. $phrase may be an array of phrases, in
     * which case `format` may be a matching array of templates.
     *
     * Options: `format` (default '<span class="highlight">\1</span>'),
     * `regex` (default '|%s|iu'), `limit` (default -1, meaning no limit).
     *
     * @param string $text
     * @param string|array $phrase
     * @param array $options
     * @return string
     */
    public static function highlight($text, $phrase, array $options = [])
    {
        if (empty($phrase)) {
            return $text;
        }

        $options += [
            'format' => '<span class="highlight">\1</span>',
            'regex'  => '|%s|iu',
            'limit'  => -1,
        ];
        self::assertNoHtmlOption($options);

        if (is_array($phrase)) {
            $replace = [];
            $with    = [];
            foreach ($phrase as $key => $segment) {
                $with[]    = is_array($options['format']) ? $options['format'][$key] : $options['format'];
                $replace[] = sprintf($options['regex'], '(' . preg_quote($segment, '|') . ')');
            }

            return preg_replace($replace, $with, $text, $options['limit']);
        }

        return preg_replace(
            sprintf($options['regex'], '(' . preg_quote($phrase, '|') . ')'),
            $options['format'],
            $text,
            $options['limit']
        );
    }

    /**
     * Extract the text surrounding $phrase, with $radius characters on each side.
     *
     * @param string $text
     * @param string $phrase
     * @param int $radius
     * @param string $ellipsis
     * @return string
     */
    public static function excerpt($text, $phrase, $radius = 100, $ellipsis = '...')
    {
        if (empty($text) || empty($phrase)) {
            return static::truncate($text, $radius * 2, ['ellipsis' => $ellipsis]);
        }

        $append = $prepend = $ellipsis;

        $phraseLen = mb_strlen($phrase);
        $textLen   = mb_strlen($text);

        $pos = mb_stripos($text, $phrase);
        if ($pos === false) {
            return mb_substr($text, 0, $radius) . $ellipsis;
        }

        $startPos = $pos - $radius;
        if ($startPos <= 0) {
            $startPos = 0;
            $prepend  = '';
        }

        $endPos = $pos + $phraseLen + $radius;
        if ($endPos >= $textLen) {
            $endPos = $textLen;
            $append = '';
        }

        return $prepend . mb_substr($text, $startPos, $endPos - $startPos) . $append;
    }

    /**
     * Character count, or display width when the trimWidth option is set.
     *
     * @param string $text
     * @param array $options
     * @return int
     */
    protected static function strlen($text, array $options)
    {
        return empty($options['trimWidth']) ? mb_strlen($text) : mb_strwidth($text);
    }

    /**
     * Substring by character position, or by display width when trimWidth is set.
     *
     * @param string $text
     * @param int $start
     * @param int|null $length
     * @param array $options
     * @return string
     */
    protected static function substr($text, $start, $length, array $options)
    {
        $substr = empty($options['trimWidth']) ? 'mb_substr' : 'mb_strimwidth';

        // The maximum position is always a character count, even when measuring width.
        $maxPosition = self::strlen($text, ['trimWidth' => false] + $options);
        if ($start < 0) {
            $start += $maxPosition;
            if ($start < 0) {
                $start = 0;
            }
        }
        if ($start >= $maxPosition) {
            return '';
        }

        if ($length === null) {
            $length = self::strlen($text, $options);
        }
        if ($length < 0) {
            $text   = self::substr($text, $start, null, $options);
            $start  = 0;
            $length += self::strlen($text, $options);
        }
        if ($length <= 0) {
            return '';
        }

        return (string)$substr($text, $start, $length);
    }

    /**
     * Drop the trailing partial word left behind by an inexact cut.
     *
     * NOTE: this diverges from Cake 3.10 deliberately. The original did
     * `$lastWord = mb_strrpos($text, $spacepos)` — passing a position where a
     * needle belongs, so it searched for the offset's digits as a literal and
     * measured whatever that returned. This implements the documented intent
     * instead. No caller in this codebase passes `exact => false`, so nothing
     * depended on the original's behavior.
     *
     * @param string $text
     * @return string
     */
    protected static function removeLastWord($text)
    {
        $spacepos = mb_strrpos($text, ' ');
        if ($spacepos === false) {
            return '';
        }

        $lastWord = mb_substr($text, $spacepos + 1);

        // Some languages are written without word separation; a run containing
        // full-width characters is not a "word" we may strip.
        if (mb_strwidth($lastWord) === mb_strlen($lastWord)) {
            return mb_substr($text, 0, $spacepos);
        }

        return $text;
    }

    /**
     * @param array $options
     * @throws InvalidArgumentException
     */
    private static function assertNoHtmlOption(array $options)
    {
        if (! empty($options['html'])) {
            throw new InvalidArgumentException(
                'The "html" option of Cake\Utility\Text was not ported to '
                . self::class . '; no caller used it. Implement it here if you need it.'
            );
        }
    }
}
