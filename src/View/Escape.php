<?php

declare(strict_types=1);

namespace SionModel\View;

use function htmlspecialchars;

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
}
