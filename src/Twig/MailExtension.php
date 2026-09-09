<?php

declare(strict_types=1);

namespace SionModel\Twig;

use IntlDateFormatter;
use InvalidArgumentException;
use Laminas\I18n\Translator\TranslatorInterface;
use Locale;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function date_default_timezone_get;
use function htmlspecialchars;
use function is_array;
use function is_bool;
use function is_string;
use function strtoupper;
use function vsprintf;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * The functions the mail templates call, standing in for the view helpers the `.phtml`
 * originals called.
 *
 * - `translate(message, domain, locale)` is the `translate` view helper: the translator
 *   decides the locale when none is given.
 * - `format_date(date, dateType, timeType)` is the `dateFormat` view helper with its
 *   defaults — the process's default locale and time zone — which is what the overdue
 *   notice's due dates were always formatted with.
 * - `mail_content(paragraph)` is the body of `paragraph-partial.phtml`: translate, then
 *   escape, then interpolate the parameters. That order is the original's. Interpolating
 *   after escaping means the **parameters are not escaped** — a borrower's first name
 *   arrives in the salutation verbatim. Reproduced so the port can be byte-compared;
 *   worth changing on its own, deliberately.
 */
final class MailExtension extends AbstractExtension
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('translate', $this->translate(...)),
            new TwigFunction('format_date', $this->formatDate(...)),
            new TwigFunction('mail_content', $this->content(...), ['is_safe' => ['html']]),
        ];
    }

    public function translate(string $message, string $textDomain = 'default', ?string $locale = null): string
    {
        return $this->translator->translate($message, $textDomain, $locale);
    }

    /**
     * @param mixed $date anything IntlDateFormatter::format() accepts: a DateTimeInterface,
     *        a timestamp, a numeric string
     * @param string $dateType none|short|medium|long|full
     * @param string $timeType none|short|medium|long|full
     */
    public function formatDate(mixed $date, string $dateType = 'medium', string $timeType = 'none'): string
    {
        $formatter = new IntlDateFormatter(
            Locale::getDefault(),
            self::style($dateType),
            self::style($timeType),
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN
        );
        $formatted = $formatter->format($date);

        return false === $formatted ? '' : $formatted;
    }

    /**
     * A paragraph's text, ready to print: translated, escaped, interpolated — in that
     * order, as the `.phtml` did it.
     *
     * @param array<string, mixed> $paragraph keys content, isTranslatorEnabled, textDomain,
     *        locale, shouldEscape, isContentParameterized, contentParams
     */
    public function content(array $paragraph): string
    {
        if (! isset($paragraph['content'])) {
            return '';
        }
        $content    = (string) $paragraph['content'];
        $translate  = is_bool($paragraph['isTranslatorEnabled'] ?? null) ? $paragraph['isTranslatorEnabled'] : true;
        $escape     = is_bool($paragraph['shouldEscape'] ?? null) ? $paragraph['shouldEscape'] : true;
        $interpolate = is_bool($paragraph['isContentParameterized'] ?? null)
            ? $paragraph['isContentParameterized']
            : false;
        $domain = is_string($paragraph['textDomain'] ?? null) ? $paragraph['textDomain'] : 'default';
        $locale = is_string($paragraph['locale'] ?? null) ? $paragraph['locale'] : null;

        if ($translate) {
            $content = $this->translator->translate($content, $domain, $locale);
        }
        if ($escape) {
            $content = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($interpolate) {
            if (! is_array($paragraph['contentParams'] ?? null)) {
                throw new InvalidArgumentException('Parameterized content requires the contentParams var to be set.');
            }
            $content = vsprintf($content, $paragraph['contentParams']);
        }

        return $content;
    }

    private static function style(string $name): int
    {
        return match (strtoupper($name)) {
            'NONE'   => IntlDateFormatter::NONE,
            'SHORT'  => IntlDateFormatter::SHORT,
            'MEDIUM' => IntlDateFormatter::MEDIUM,
            'LONG'   => IntlDateFormatter::LONG,
            'FULL'   => IntlDateFormatter::FULL,
            default  => throw new InvalidArgumentException("Unknown date style '$name'"),
        };
    }
}
