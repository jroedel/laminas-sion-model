<?php

declare(strict_types=1);

namespace SionModel\Text;

use function chr;
use function ord;
use function strlen;

/**
 * What `ForceUTF8\Encoding::toUTF8()` did for this application, in native PHP.
 *
 * One caller: `SionModel\Filter\ToAscii`, which hands the result straight to
 * `iconv('UTF-8', 'ASCII//TRANSLIT', …)`. That is the whole reason the step exists —
 * `iconv()` returns `false` on the first illegal byte, so a filter fed a stray Latin-1
 * byte would silently return an empty search term rather than a transliterated one.
 *
 * ## Why this is a transcription and not a rewrite
 *
 * The obvious native answer, `mb_convert_encoding($s, 'UTF-8', 'Windows-1252')`, re-encodes
 * the *whole* string, so a string mixing valid UTF-8 with one stray byte loses its valid
 * parts: `María\xffMaría` became `mara aymara a` where the original gives `mariaymaria`.
 * Measured against all 30,574 distinct production values `ToAscii` is reached with, the two
 * agree everywhere; they part only on malformed input, which is exactly the input this
 * exists to survive. So the byte loop below reproduces the original's decision rule rather
 * than approximating it.
 *
 * The rule: a byte ≥ `\xc0` that begins a well-formed 2-, 3- or 4-byte sequence is copied
 * through with its continuation bytes; anything else is re-encoded as a single CP1252 byte,
 * with `\x80`–`\x9f` going through {@see self::WIN1252} rather than through the Latin-1 C1
 * controls.
 *
 * Two consequences of that rule are bugs, faithfully kept because the corpus cannot tell
 * the difference and a search term is the only thing downstream: a surrogate (`\xed\xa0\x80`)
 * and an overlong sequence (`\xc0\xaf`) both *look* well-formed to it and are copied, so
 * `iconv()` rejects them and the filter yields an empty string. Changing that would be a
 * behaviour change dressed as a cleanup.
 */
final class Utf8Repair
{
    /**
     * `\x80`–`\x9f` read as CP1252 rather than as Latin-1 C1 controls — the euro sign,
     * the smart quotes and the dashes a Windows mail client emits.
     *
     * @var array<int, string>
     */
    private const WIN1252 = [
        0x80 => "\u{20ac}", 0x82 => "\u{201a}", 0x83 => "\u{0192}", 0x84 => "\u{201e}",
        0x85 => "\u{2026}", 0x86 => "\u{2020}", 0x87 => "\u{2021}", 0x88 => "\u{02c6}",
        0x89 => "\u{2030}", 0x8a => "\u{0160}", 0x8b => "\u{2039}", 0x8c => "\u{0152}",
        0x8e => "\u{017d}", 0x91 => "\u{2018}", 0x92 => "\u{2019}", 0x93 => "\u{201c}",
        0x94 => "\u{201d}", 0x95 => "\u{2022}", 0x96 => "\u{2013}", 0x97 => "\u{2014}",
        0x98 => "\u{02dc}", 0x99 => "\u{2122}", 0x9a => "\u{0161}", 0x9b => "\u{203a}",
        0x9c => "\u{0153}", 0x9e => "\u{017e}", 0x9f => "\u{0178}",
    ];

    /** Leave valid UTF-8 alone; read every other byte as Windows-1252. */
    public static function toUtf8(string $text): string
    {
        $max = strlen($text);
        $buffer = '';

        for ($i = 0; $i < $max; $i++) {
            $c1 = $text[$i];

            if ($c1 >= "\xc0") {
                $c2 = $i + 1 >= $max ? "\x00" : $text[$i + 1];
                $c3 = $i + 2 >= $max ? "\x00" : $text[$i + 2];
                $c4 = $i + 3 >= $max ? "\x00" : $text[$i + 3];

                $continuations = match (true) {
                    $c1 <= "\xdf" => 1,
                    $c1 <= "\xef" => 2,
                    $c1 <= "\xf7" => 3,
                    default       => 0,
                };
                $wellFormed = match ($continuations) {
                    1       => self::isContinuation($c2),
                    2       => self::isContinuation($c2) && self::isContinuation($c3),
                    3       => self::isContinuation($c2) && self::isContinuation($c3)
                        && self::isContinuation($c4),
                    default => false,
                };

                if ($wellFormed) {
                    $buffer .= substr($text, $i, $continuations + 1);
                    $i      += $continuations;
                    continue;
                }

                $buffer .= self::asLatin1($c1);
                continue;
            }

            if (($c1 & "\xc0") === "\x80") {
                $buffer .= self::WIN1252[ord($c1)] ?? self::asLatin1($c1);
                continue;
            }

            $buffer .= $c1;
        }

        return $buffer;
    }

    private static function isContinuation(string $byte): bool
    {
        return $byte >= "\x80" && $byte <= "\xbf";
    }

    /** The byte, encoded as the two-byte UTF-8 sequence for that Latin-1 code point. */
    private static function asLatin1(string $byte): string
    {
        return (chr(intdiv(ord($byte), 64)) | "\xc0") . (($byte & "\x3f") | "\x80");
    }
}
