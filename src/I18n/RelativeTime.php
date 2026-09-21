<?php

declare(strict_types=1);

namespace SionModel\I18n;

use DateTimeImmutable;
use DateTimeInterface;

use function abs;
use function intdiv;
use function str_replace;
use function strtolower;
use function strcspn;
use function substr;

/**
 * "3 days ago", in the five languages this site serves.
 *
 * What `Carbon::diffForHumans()` did for one view helper. `nesbot/carbon` cost six packages
 * — `carbonphp/carbon-doctrine-types`, `psr/clock`, `symfony/polyfill-php80`, and
 * `symfony/translation` with its contracts, because `CarbonInterval::forHumans()` type-hints
 * Symfony's translator. That put a copy of a translation library this project measured and
 * **rejected** (docs/translation.md) into vendor, to serve a comment timestamp.
 *
 * ## Why this is locale data and not a phrase catalogue
 *
 * The obvious route — JTranslate phrases — is wrong here, and the reason is day one rather
 * than plurals. A phrase starts untranslated and the `.lang.php` catalogs are build output
 * compiled from the database, so the first deploy would render English on `/es/`, `/de/`,
 * `/pt/` and `/it/` until somebody translated four locales by hand, and docs/translation.md
 * forbids seeding the table to avoid that. Carbon renders all five correctly today; a
 * replacement that does not is a public regression.
 *
 * So the 45 strings below are **transcribed** from `vendor/nesbot/carbon/src/Carbon/Lang/`
 * while the package was still installed — nothing is authored and nothing is translated.
 * This is the same category as the language table laminas-exit.md §8 proposes in place of
 * `matriphe/iso-639`: locale data belonging to the code, not content belonging to editors.
 *
 * ## The plural rule, and the limit of it
 *
 * Every configured locale — `en_US`, `es_ES`, `de_DE`, `pt_BR`, `it_IT` — is a CLDR
 * two-form language, so `1 === $count` selects between `one` and `other` and is exactly
 * right for all five. It is **not** a CLDR implementation, and it would be wrong for a
 * Slavic or Arabic locale. `SionModelTest\Unit\RelativeTimeTest` fails if a sixth locale
 * appears without an entry here, and at that point the answer is ext/intl's
 * `MessageFormatter`, which does implement plural rules.
 */
final class RelativeTime
{
    /** The locale used when the requested one has no table — English, the source language. */
    private const FALLBACK = 'en';

    /**
     * Unit names in singular and plural, plus the two framings, per language.
     *
     * Transcribed verbatim from Carbon's `Lang/{en,es,de,pt_BR,it}.php`. `:count` and
     * `:time` are its placeholders and are kept so the strings stay comparable with the
     * source they came from.
     *
     * @var array<string, array<string, array{string, string}|string>>
     */
    private const TABLE = [
        'en' => [
            'year'   => [':count year', ':count years'],
            'month'  => [':count month', ':count months'],
            'week'   => [':count week', ':count weeks'],
            'day'    => [':count day', ':count days'],
            'hour'   => [':count hour', ':count hours'],
            'minute' => [':count minute', ':count minutes'],
            'second' => [':count second', ':count seconds'],
            'ago'      => ':time ago',
            'from_now' => ':time from now',
        ],
        'es' => [
            'year'   => [':count año', ':count años'],
            'month'  => [':count mes', ':count meses'],
            'week'   => [':count semana', ':count semanas'],
            'day'    => [':count día', ':count días'],
            'hour'   => [':count hora', ':count horas'],
            'minute' => [':count minuto', ':count minutos'],
            'second' => [':count segundo', ':count segundos'],
            'ago'      => 'hace :time',
            'from_now' => 'en :time',
        ],
        'de' => [
            //Dative plurals. Carbon carries both — `year` is `:count Jahre` and
            //`year_ago`/`year_from_now` are `:count Jahren` — because German declines
            //after `vor` and `in`. Every value this class produces is framed by one of
            //those two prepositions, so the nominative forms are unreachable here and
            //only the declined ones are kept.
            'year'   => [':count Jahr', ':count Jahren'],
            'month'  => [':count Monat', ':count Monaten'],
            'week'   => [':count Woche', ':count Wochen'],
            'day'    => [':count Tag', ':count Tagen'],
            'hour'   => [':count Stunde', ':count Stunden'],
            'minute' => [':count Minute', ':count Minuten'],
            'second' => [':count Sekunde', ':count Sekunden'],
            'ago'      => 'vor :time',
            'from_now' => 'in :time',
        ],
        'pt' => [
            'year'   => [':count ano', ':count anos'],
            'month'  => [':count mês', ':count meses'],
            'week'   => [':count semana', ':count semanas'],
            'day'    => [':count dia', ':count dias'],
            'hour'   => [':count hora', ':count horas'],
            'minute' => [':count minuto', ':count minutos'],
            'second' => [':count segundo', ':count segundos'],
            'ago'      => 'há :time',
            'from_now' => 'em :time',
        ],
        'it' => [
            'year'   => [':count anno', ':count anni'],
            'month'  => [':count mese', ':count mesi'],
            'week'   => [':count settimana', ':count settimane'],
            'day'    => [':count giorno', ':count giorni'],
            'hour'   => [':count ora', ':count ore'],
            'minute' => [':count minuto', ':count minuti'],
            'second' => [':count secondo', ':count secondi'],
            'ago'      => ':time fa',
            //Carbon's Italian `from_now` is a closure choosing `tra` for a value that
            //starts with a digit and `in` otherwise. Every value this produces starts with
            //its count, so the branch is settled and the closure collapses to this.
            'from_now' => 'tra :time',
        ],
    ];

    /**
     * The distance from now to `$date`, in words.
     *
     * @param string $locale an ICU locale or language tag; only the language matters here,
     *                       because the five configured locales are five distinct languages
     */
    public static function format(
        DateTimeInterface $date,
        string $locale,
        ?DateTimeInterface $now = null
    ): string {
        $now   = $now ?? new DateTimeImmutable();
        $table = self::TABLE[self::language($locale)] ?? self::TABLE[self::FALLBACK];

        [$unit, $count] = self::unit($date, $now);

        /** @var array{string, string} $forms */
        $forms = $table[$unit];
        $time  = str_replace(':count', (string) $count, 1 === $count ? $forms[0] : $forms[1]);

        /** @var string $framing */
        $framing = $date > $now ? $table['from_now'] : $table['ago'];

        return str_replace(':time', $time, $framing);
    }

    /**
     * The largest unit that fits, as Carbon chose it.
     *
     * Years and months come from the calendar difference rather than from a day count, so
     * 364 days is 11 months and 365 is one year; weeks appear only between 7 and 30 days;
     * and a zero difference reads as one second rather than as nothing at all.
     *
     * @return array{string, int}
     */
    private static function unit(DateTimeInterface $date, DateTimeInterface $now): array
    {
        $diff = $now->diff($date);

        return match (true) {
            $diff->y > 0  => ['year', $diff->y],
            $diff->m > 0  => ['month', $diff->m],
            $diff->d >= 7 => ['week', intdiv($diff->d, 7)],
            $diff->d > 0  => ['day', $diff->d],
            $diff->h > 0  => ['hour', $diff->h],
            $diff->i > 0  => ['minute', $diff->i],
            default       => ['second', abs($diff->s) > 0 ? $diff->s : 1],
        };
    }

    /** `de_DE` and `de` alike answer `de`; `en_US_POSIX`, which the CLI reports, answers `en`. */
    private static function language(string $locale): string
    {
        $language = strtolower($locale);
        $break    = strcspn($language, '_-');

        return substr($language, 0, $break);
    }
}
