<?php

declare(strict_types=1);

namespace SionModel\View;

use Twig\Runtime\EscaperRuntime;

use function hexdec;
use function htmlspecialchars;
use function mb_convert_encoding;
use function preg_match;
use function preg_replace_callback;
use function sprintf;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * HTML escaping for code that builds markup outside a template.
 *
 * Replaces `Laminas\Escaper\Escaper`, and reproduces it rather than improvising:
 * `escapeHtml()` was `htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $encoding)`
 * and that is what this is, character for character. `ENT_SUBSTITUTE` is the flag that
 * matters and the one easiest to leave off — without it an invalid UTF-8 sequence makes
 * `htmlspecialchars()` return an **empty string**, so a single bad byte in a database
 * field silently erases the value it was supposed to escape rather than showing it.
 *
 * ## Why a class for one function call
 *
 * Because it is the seam. Twenty helpers escape, and having them all reach one place is
 * what let laminas-escaper leave without auditing twenty call sites for the flags. It is
 * also where attribute escaping will go when the helpers that need it are ported —
 * `escapeHtmlAttr()` is a different and stricter algorithm, and Twig ships the same one,
 * so it will delegate there rather than being written again.
 *
 * ## This is not for templates
 *
 * Twig escapes what it renders. This is for the classes that return finished HTML to a
 * template that then prints it with `|raw`, which is the arrangement every ported view
 * helper is in.
 */
final class Escape
{
    /** The encoding laminas-escaper defaulted to, and the only one this application serves. */
    public const ENCODING = 'utf-8';

    public static function html(?string $value): string
    {
        return null === $value
            ? ''
            : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, self::ENCODING);
    }

    /**
     * Escape a value going into an HTML **attribute**.
     *
     * A different and much stricter algorithm than {@see html()}: everything outside
     * `[a-z0-9,.\-_]` becomes a numeric entity, so the value stays inert even in an
     * unquoted attribute. Twig ships that algorithm and this delegates to it — writing a
     * second copy of an escaper is how a subtle difference becomes an injection.
     *
     * **The guard is the reason this is not a one-liner.** Twig *throws* on a string that
     * is not valid UTF-8; `Laminas\Escaper\Escaper::escapeHtmlAttr()` converted instead and
     * returned something. A page whose only fault is one bad byte in a database field must
     * not die, so invalid sequences are substituted first — the same substitution
     * `ENT_SUBSTITUTE` performs for {@see html()} — and Twig then sees valid input.
     *
     * The runtime is built once and needs nothing but a charset; it is not the Twig
     * environment that renders templates and shares no state with it.
     */
    public static function htmlAttr(?string $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }
        if (1 !== preg_match('//u', $value)) {
            //not valid UTF-8: replace the invalid sequences rather than raise
            $value = (string) mb_convert_encoding($value, self::ENCODING, self::ENCODING);
        }

        self::$escaper ??= new EscaperRuntime(self::ENCODING);

        return self::laminasEntityWidth((string) self::$escaper->escape($value, 'html_attr'));
    }

    /**
     * Twig writes every numeric entity four digits wide; laminas wrote two below U+0100
     * and four above (`sprintf('&#x%02X;')` / `'&#x%04X;'`).
     *
     * Both are the same character to every browser, and normalising is still worth six
     * lines: this application's data is full of non-ASCII — German, Spanish and Portuguese
     * names in tooltips and titles — so without it every such attribute would change bytes
     * on the deploy that ports these helpers. Byte-identical output is what makes the
     * golden-master tests and a diff against the live site mean anything.
     */
    private static function laminasEntityWidth(string $escaped): string
    {
        return (string) preg_replace_callback(
            '/&#x([0-9A-F]+);/',
            static function (array $m): string {
                $ord = (int) hexdec($m[1]);

                return sprintf($ord > 255 ? '&#x%04X;' : '&#x%02X;', $ord);
            },
            $escaped
        );
    }

    private static ?EscaperRuntime $escaper = null;
}
