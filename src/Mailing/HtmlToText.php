<?php

declare(strict_types=1);

namespace SionModel\Mailing;

use function array_keys;
use function array_values;
use function explode;
use function html_entity_decode;
use function htmlspecialchars;
use function htmlspecialchars_decode;
use function implode;
use function mb_strtoupper;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function is_string;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

/**
 * The `text/plain` alternative of an HTML mail, in our own code.
 *
 * What `voku/html2text` did for this application. That package is **GPL-2.0-or-later**
 * inside a BSD-3-Clause tree, and it pulled in four more packages — `voku/portable-utf8`,
 * `voku/portable-ascii`, `symfony/polyfill-iconv` and `symfony/polyfill-php72` — so it cost
 * five to do one job in three places.
 *
 * ## What this is, and what it deliberately is not
 *
 * It is not a general HTML-to-text engine, and it does not claim byte parity with one. The
 * package it replaces carries nesting handlers for `<blockquote>`, `<pre>` and lists, a
 * link-table mode, image placeholders, word wrapping and a BBCode writer, none of which
 * this application ever reached: `do_links` is `inline`, `width` is 0, and the HTML fed to
 * it is our own — two Twig mail templates and Parsedown's output.
 *
 * So the rules below are transcribed from that package for the tags that actually occur,
 * and the contract is `test/Mail/html-to-text-surface.php`: every distinct HTML shape in
 * the 2,756 stored documents and in both mail templates, with the answers recorded while
 * the package was still installed.
 *
 * ## The one rule that must never regress
 *
 * `<head>`, `<script>` and `<style>` are removed **whole, first**, before anything else
 * looks at the document. `module/SionModel/templates/mailing/action-email.html.twig` embeds
 * a JSON-LD `<script>`; miss this and every notice's plain part opens with a block of JSON.
 *
 * The other rule worth naming is the link format — `display [url]`, collapsing to the bare
 * URL when the two are equal. That is how a magic-link and the "my books" button survive
 * into a text part at all, and it is the part of a notice a phone actually shows.
 */
final class HtmlToText
{
    /** Stand-in for `&nbsp;`, so a non-breaking space survives the run-of-spaces collapse. */
    private const SPACE_PLACEHOLDER = '|+|_sion_space|+|';

    /** Link schemes rendered as their text alone, with no URL appended. */
    private const IGNORED_LINK_SCHEMES = 'javascript:|mailto:|#';

    /**
     * Tags whose content is wrapped, cased, or replaced outright.
     *
     * `case` uppercases the text between any inner tags; `content` replaces the value;
     * `prepend`/`append` surround it. Transcribed from the package's default options.
     *
     * @var array<string, array{case?: bool, content?: string, prepend?: string, append?: string}>
     */
    private const ELEMENTS = [
        'h1'   => ['case' => true, 'prepend' => "\n\n", 'append' => "\n\n"],
        'h2'   => ['case' => true, 'prepend' => "\n\n", 'append' => "\n\n"],
        'h3'   => ['case' => true, 'prepend' => "\n\n", 'append' => "\n\n"],
        'h4'   => ['case' => true],
        'h5'   => ['case' => true, 'prepend' => "\n\n", 'append' => "\n\n"],
        'h6'   => ['case' => true, 'prepend' => "\n\n", 'append' => "\n\n"],
        'th'   => ['case' => true, 'prepend' => "\t\t", 'append' => "\n"],
        'hr'   => ['content' => '-------------------------', 'prepend' => "\n", 'append' => "\n"],
        'strong' => ['case' => true],
        'b'    => ['case' => true],
        'td'   => ['append' => "\n"],
        'dt'   => ['case' => true, 'prepend' => "\n\n", 'append' => "\n"],
        'dd'   => ['prepend' => '* ', 'append' => "\n"],
        'code' => ['prepend' => "\n\n```", 'append' => "```\n\n"],
        'ins'  => ['prepend' => '_', 'append' => '_'],
        'del'  => ['prepend' => '~~', 'append' => '~~'],
        'li'   => ['prepend' => '* ', 'append' => "\n"],
        'i'    => ['prepend' => '_', 'append' => '_'],
        'em'   => ['prepend' => '_', 'append' => '_'],
        'pre'  => [],
    ];

    /** Structural rewrites, applied in order, before any element is handled. */
    private const STRUCTURE = [
        "/\r\n/"                                => "\n",
        "/\r/"                                  => '',
        "/[\n\t]+/"                             => ' ',
        '/<head(?: [^>]*)?>.*?<\/head>/is'      => '',
        '/<script(?: [^>]*)?>.*?<\/script>/is'  => '',
        '/<style(?: [^>]*)?>.*?<\/style>/is'    => '',
        '/<div(?: [^>]*)?>/i'                   => "<div>\n",
        '/(<table(?: [^>]*)?>|<\/table>)/i'     => "\n\n",
        '/(<tr(?: [^>]*)?>|<\/tr>)/i'           => "\n",
    ];

    public static function convert(string $html): string
    {
        return self::whitespace(self::pipeline(self::normalizeSpaces($html)));
    }

    /**
     * Unicode spaces become ordinary ones before anything measures a run of them.
     *
     * A non-breaking space pasted into a document — and the corpus has hundreds, mostly
     * between a name and its initial — is a space in a plain-text mail, not a character to
     * carry through. `&nbsp;` is handled separately, in {@see self::entities()}, because it
     * has to survive the run-of-spaces collapse to get here.
     */
    private static function normalizeSpaces(string $html): string
    {
        return (string) preg_replace(
            '/[\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]/u',
            ' ',
            $html
        );
    }

    /** Everything but the final whitespace pass, which lists re-enter for their contents. */
    private static function pipeline(string $html): string
    {
        $text = self::lists($html);
        $text = self::preformatted($text);
        $text = self::structure($text);
        $text = self::elements($text);

        //Anything still carrying angle brackets is markup we have no rule for; drop the
        //tag and keep its text, which is what strip_tags would do but without eating
        //comments' contents.
        $text = (string) preg_replace('/<[\/!]?\w+[^>]*>|<!--.*?-->/s', '', $text);

        //Normalising blank lines and trimming here, rather than only at the end, is what
        //keeps a list's first item level with the rest: its body is converted by this same
        //method, and an untrimmed leading newline would become an extra space of indent.
        return trim((string) preg_replace("/\n\s+\n|[\n]{3,}/", "\n\n", self::entities($text)));
    }

    /**
     * `<pre>` means the spaces are the content, so they are hidden from every later pass.
     *
     * Each run of whitespace becomes a placeholder and each newline a `<br>`, which is the
     * only way indentation survives the run-of-spaces collapse and the per-line trim. Two
     * documents in the corpus depend on this — a pair of tables laid out with spaces.
     */
    private static function preformatted(string $html): string
    {
        return (string) preg_replace_callback(
            '/<pre[^>]*>(?<body>.*?)<\/pre>/is',
            static function (array $matches): string {
                $body = (string) preg_replace('/<br(?: [^>]*)?>/i', "\n", $matches['body']);
                $body = self::elements($body);
                $body = str_replace(
                    ["\n", "\t", ' '],
                    ['<br>', self::SPACE_PLACEHOLDER . self::SPACE_PLACEHOLDER, self::SPACE_PLACEHOLDER],
                    $body
                );

                return '<div><br>' . $body . '<br></div>';
            },
            $html
        );
    }

    /**
     * A list is converted on its own and then indented, which is why it runs first.
     *
     * The package did this by converting the block, prefixing each line with a tab, and
     * hiding the result in a `<pre>` so the later passes left it alone; the tab became two
     * non-breaking spaces and then two ordinary ones. The effect is a two-space indent per
     * nesting level, and it is reproduced here directly — with the indent written as the
     * space placeholder, because every line is trimmed further down and a literal space
     * would not survive that.
     *
     * The converted text is escaped before it goes back into the document, so a `<` that
     * came from `&lt;` is not mistaken for a tag by the strip that follows. The final
     * entity decode puts it back.
     */
    private static function lists(string $html): string
    {
        $pattern = '/<(?<tag>ul|ol|dl)(?: [^>]*)?>(?<body>(?:(?!<(?:ul|ol|dl)[ >]).)*?)<\/\g{tag}>/is';

        //Innermost first, repeatedly, so a nested list is indented once per level.
        for ($pass = 0; $pass < 10; $pass++) {
            $converted = preg_replace_callback(
                $pattern,
                static function (array $matches): string {
                    $body   = htmlspecialchars(self::pipeline($matches['body']), ENT_QUOTES, 'UTF-8');
                    $indent = self::SPACE_PLACEHOLDER . self::SPACE_PLACEHOLDER;
                    $body   = $indent . str_replace("\n", "\n" . $indent, $body);

                    //The newlines this block just earned would be collapsed by the
                    //structural pass that follows, which turns every newline into a space.
                    //`<br>` survives it and becomes a newline again at the element pass —
                    //the same trick the package played with `<pre>`.
                    return '<br>' . str_replace("\n", '<br>', $body) . '<br>';
                },
                $html
            );

            if (! is_string($converted) || $converted === $html) {
                return $html;
            }
            $html = $converted;
        }

        return $html;
    }

    private static function structure(string $html): string
    {
        //Whitespace between tags is layout, not content: without this a Twig template's
        //indentation becomes a paragraph of spaces.
        $text = (string) preg_replace('/>\s+</', '><', $html);
        $text = htmlspecialchars_decode($text, ENT_QUOTES);

        return (string) preg_replace(array_keys(self::STRUCTURE), array_values(self::STRUCTURE), $text);
    }

    private static function elements(string $text): string
    {
        $patterns = [
            '/<(?<element>h[123456])(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/[ ]*<(?<element>p)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>[ ]*/si',
            '/<(?<element>li)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>li)(?: [^>]*)?>/i',
            '/<(?<element>hr)(?: [^>]*)?>/i',
            '/<(?<element>b)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>strong)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>dt)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>dd)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>th)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>td)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>a) [^>]*href=("|\')([^"\']+)\2([^>]*)>(.*?)<\/\g{element}>/i',
            '/<(?<element>i)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>em)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>ins)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>del)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>code)(?: [^>]*)?>(?<value>.*?)<\/\g{element}>/i',
            '/<(?<element>br)[^>]*>[ ]*/i',
            '/<(?<element>img)(?:.*?)alt=["|\'](?<alt>.*?)["|\'](?:.*?)src=["|\'](?<src>.*?)["|\'](?:.*?)>/i',
            '/<(?<element>img)(?:.*?)src=["|\'](?<src>.*?)["|\'](?:.*?)alt=["|\'](?<alt>.*?)["|\'](?:.*?)>/i',
        ];

        return (string) preg_replace_callback($patterns, self::handle(...), $text);
    }

    /** @param array<string|int, string> $matches */
    private static function handle(array $matches): string
    {
        $element = strtolower($matches['element']);

        return match ($element) {
            'p'   => "\n\n" . str_replace("\n", ' ', $matches['value']) . "\n\n",
            'br'  => "\n",
            'img' => self::image($matches),
            'a'   => self::link(str_replace(' ', '', $matches[3]), $matches[5]),
            default => self::wrap($matches['value'] ?? '', $element),
        };
    }

    /** @param array<string|int, string> $matches */
    private static function image(array $matches): string
    {
        $alt = $matches['alt'] ?? '';
        if ('' === $alt) {
            return '';
        }

        $src      = $matches['src'] ?? '';
        $isRemote = '' !== $src
            && ! str_contains($src, 'cid:')
            && (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')
                || str_starts_with($src, '//'));

        return $isRemote ? ' Image: "' . $alt . '" [' . $src . '] ' : ' Image: "' . $alt . '" ';
    }

    /**
     * `display [url]`, and the bare URL when the two are the same.
     *
     * A `mailto:`, `javascript:` or in-page link renders as its text alone: appending
     * `[mailto:…]` to an address that is already written out helps nobody.
     */
    private static function link(string $url, string $display): string
    {
        if (preg_match('!^(?:' . self::IGNORED_LINK_SCHEMES . ')!i', $url)) {
            return $display;
        }

        return $url === $display ? ' ' . $url . ' ' : ' ' . $display . ' [' . $url . '] ';
    }

    private static function wrap(string $value, string $element): string
    {
        $options = self::ELEMENTS[$element] ?? null;
        if (null === $options) {
            return $value;
        }

        if ($options['case'] ?? false) {
            $value = self::uppercaseText($value);
        }
        if (isset($options['content']) && '' !== $options['content']) {
            $value = $options['content'];
        }

        return ($options['prepend'] ?? '') . $value . ($options['append'] ?? '');
    }

    /** Uppercase the text, leaving any tags inside it alone. */
    private static function uppercaseText(string $value): string
    {
        $chunks = preg_split('/(<[^>]*>)/', $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (false === $chunks) {
            return $value;
        }

        foreach ($chunks as $i => $chunk) {
            if ('' !== $chunk && '<' !== $chunk[0]) {
                //`mb_strtoupper('ß')` is `SS`, which lengthens the word. The package
                //uppercased without changing length, giving `ẞ` — and this corpus is
                //largely German, so the difference is on 180 of 2,756 documents.
                $text       = str_replace('ß', 'ẞ', self::decode($chunk));
                $chunks[$i] = mb_strtoupper($text, 'UTF-8');
            }
        }

        return implode('', $chunks);
    }

    private static function entities(string $text): string
    {
        //Windows-1252 numeric entities a mail client may have written, then the two
        //placeholders that have to outlive the space collapse below.
        $text = str_replace(
            ['&#153;', '&#151;', '&nbsp;', '&NBSP;'],
            ['™', '—', self::SPACE_PLACEHOLDER, self::SPACE_PLACEHOLDER],
            $text
        );
        $text = (string) preg_replace('/[ ]{2,}/', ' ', $text);

        return self::decode($text);
    }

    private static function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function whitespace(string $text): string
    {
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $lines[$i] = trim($line);
        }
        $text = implode("\n", $lines);

        $text = str_replace(self::SPACE_PLACEHOLDER, ' ', $text);
        $text = (string) preg_replace('# +$#m', '', $text);
        $text = (string) preg_replace("/\n\s+\n|[\n]{3,}/", "\n\n", $text);

        return trim($text, "\r\n");
    }
}
