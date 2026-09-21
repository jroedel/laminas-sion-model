<?php

declare(strict_types=1);

namespace SionModel\Mailing;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

use function array_merge;
use function count;
use function explode;
use function implode;
use function libxml_use_internal_errors;
use function mb_encode_numericentity;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtolower;
use function substr_count;
use function spl_object_id;
use function trim;
use function usort;

use const PREG_SET_ORDER;

/**
 * Mail stylesheet rules, written onto the elements they match.
 *
 * What `tijsverkoyen/css-to-inline-styles` did for this application, without it or
 * `symfony/css-selector`, which came with it. Mail clients strip `<style>`, so a rule only
 * reaches a recipient as a `style` attribute — that is the whole job.
 *
 * ## Why a closed set of selectors is enough
 *
 * The stylesheet is ours: `public/css/email-default.css`, 48 rules, and every selector in
 * it is one of `*`, a tag, a class, a tag-or-class with further classes attached, a
 * descendant chain of those, or a comma-separated list of them. There is one `@media`
 * block, which is dropped — the package dropped it too, and a media query cannot be
 * expressed as an inline style anyway.
 *
 * An unsupported selector is not ignored quietly. `parse()` throws, and
 * `test/Integration/MailStylesheetIsInlinableTest` runs every selector in the stylesheet
 * through it, so the day someone writes `:hover` or `>` a test fails instead of a notice
 * going out unstyled.
 *
 * ## Ordering
 *
 * Rules are applied in specificity order, ties by source order, and a declaration already
 * in the element's own `style` attribute beats anything the stylesheet says — the cascade,
 * restricted to what an inline style can express.
 */
final class CssInliner
{
    public static function inline(string $html, string $css): string
    {
        $rules = self::parse($css);
        if ([] === $rules) {
            return $html;
        }

        $document = self::load($html);
        $xpath    = new DOMXPath($document);

        /** @var array<int, array{DOMElement, array<string, string>}> $pending */
        $pending = [];
        $seen    = [];

        foreach ($rules as $rule) {
            $matches = $xpath->query($rule['xpath']);
            if (false === $matches) {
                continue;
            }
            foreach ($matches as $element) {
                if (! $element instanceof DOMElement) {
                    continue;
                }
                $key = spl_object_id($element);
                if (! isset($seen[$key])) {
                    $seen[$key]    = count($pending);
                    $pending[]     = [$element, []];
                }
                $at = $seen[$key];
                $pending[$at][1] = array_merge($pending[$at][1], $rule['declarations']);
            }
        }

        foreach ($pending as [$element, $declarations]) {
            self::write($element, $declarations);
        }

        return self::save($document);
    }

    /**
     * @return list<array{xpath: string, declarations: array<string, string>, order: int, specificity: int}>
     */
    public static function parse(string $css): array
    {
        //Comments and at-rule blocks go first: a media query cannot become an inline style.
        $css = (string) preg_replace('!/\*.*?\*/!s', '', $css);
        $css = (string) preg_replace('/@[a-z-]+[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/is', '', $css);

        $rules = [];
        $order = 0;

        preg_match_all('/(?<selectors>[^{}]+)\{(?<body>[^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            $declarations = self::declarations($block['body']);
            if ([] === $declarations) {
                continue;
            }

            foreach (explode(',', $block['selectors']) as $selector) {
                $selector = trim($selector);
                if ('' === $selector) {
                    continue;
                }

                $rules[] = [
                    'xpath'        => self::toXPath($selector),
                    'declarations' => $declarations,
                    'order'        => $order++,
                    'specificity'  => self::specificity($selector),
                ];
            }
        }

        usort($rules, static fn(array $a, array $b): int
            => [$a['specificity'], $a['order']] <=> [$b['specificity'], $b['order']]);

        return $rules;
    }

    /** @return array<string, string> */
    private static function declarations(string $body): array
    {
        $declarations = [];
        foreach (explode(';', $body) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $declaration, 2);
            $property           = strtolower(trim($property));
            $value              = trim($value);
            if ('' !== $property && '' !== $value) {
                $declarations[$property] = $value;
            }
        }

        return $declarations;
    }

    /**
     * `.footer p` becomes `//*[contains(concat(" ",normalize-space(@class)," ")," footer ")]//p`.
     *
     * @throws RuntimeException when the selector is outside the supported set
     */
    private static function toXPath(string $selector): string
    {
        $steps = preg_split('/\s+/', $selector) ?: [];
        $xpath = '';

        foreach ($steps as $step) {
            $shape = '/\A(?<element>\*|[a-zA-Z][a-zA-Z0-9]*)?(?<classes>(?:\.[a-zA-Z0-9_-]+)*)\z/';
            if (
                ! preg_match($shape, $step, $parts)
                || ('' === $parts['element'] && '' === $parts['classes'])
            ) {
                throw new RuntimeException(sprintf(
                    'Selector "%s" is outside what SionModel\Mailing\CssInliner supports; '
                    . 'add a rule for it or write the style inline.',
                    $selector
                ));
            }

            $element = '' === $parts['element'] ? '*' : $parts['element'];
            $xpath  .= '//' . $element;

            if ('' !== $parts['classes']) {
                foreach (explode('.', trim($parts['classes'], '.')) as $class) {
                    $xpath .= sprintf(
                        '[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
                        $class
                    );
                }
            }
        }

        return $xpath;
    }

    /** CSS specificity, flattened: classes count 10, elements 1, `*` nothing. */
    private static function specificity(string $selector): int
    {
        $classes  = substr_count($selector, '.');
        $elements = preg_match_all('/(?:\A|\s)(?!\*)[a-zA-Z][a-zA-Z0-9]*/', $selector);

        return $classes * 10 + (int) $elements;
    }

    /** @param array<string, string> $declarations */
    private static function write(DOMElement $element, array $declarations): void
    {
        //The element's own style attribute is the author's last word, so it is merged last.
        $inline = self::declarations($element->getAttribute('style'));

        $pairs = [];
        foreach (array_merge($declarations, $inline) as $property => $value) {
            $pairs[] = $property . ': ' . $value . ';';
        }

        $element->setAttribute('style', implode(' ', $pairs));
    }

    private static function load(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        //Non-ASCII becomes numeric entities first: DOMDocument::loadHTML assumes Latin-1
        //for a document with no meta charset, and a mail body is full of accented names.
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML(mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8'));
        libxml_use_internal_errors($internalErrors);

        $document->formatOutput = true;

        return $document;
    }

    private static function save(DOMDocument $document): string
    {
        $root = $document->documentElement;
        if (null === $root) {
            throw new RuntimeException('The mail body produced an empty document.');
        }

        $html = $document->saveHTML($root);
        if (false === $html) {
            throw new RuntimeException('The mail body could not be serialised.');
        }
        $html = trim($html);

        $document->removeChild($root);
        $doctype = $document->saveHTML();
        $doctype = false === $doctype ? '' : trim($doctype);
        if ('<!DOCTYPE html>' === $doctype) {
            $doctype = strtolower($doctype);
        }

        return $doctype . "\n" . $html;
    }
}
