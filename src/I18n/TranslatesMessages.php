<?php

declare(strict_types=1);

namespace SionModel\I18n;

/**
 * Translates a message in a text domain.
 *
 * **What this package needs of a translator, and nothing more** — the same kind of contract
 * as {@see \SionModel\Mailing\TemplateRendererInterface}, which states what `Mailer` needs
 * of a template engine without this package being one. It is not a claim on translation:
 * the implementation lives wherever the host's translator lives, and on schoenstatt.link
 * that is `JTranslate\I18n\Translator\Translator`, which this package cannot see and does
 * not require.
 *
 * `Laminas\Translator\TranslatorInterface` was this contract until 2026-09-21 — a
 * zero-dependency, single-file interface package, and the cheapest dependency in the tree,
 * which is why it was the last laminas package that nothing forced. It could not simply
 * move into JTranslate: SionModel and JTranslate are peers, neither requires the other, and
 * five of the six consumers are here.
 *
 * Two deliberate differences from the interface it replaces:
 *
 * - **`translatePlural()` is gone.** It had no callers in the application or in any of the
 *   three shared libraries, measured twice — at the laminas-i18n removal and again here —
 *   and the catalogs hold no plural forms to answer with. The only implementation applied
 *   the English rule to every locale. Nothing but the interface ever required it.
 * - **The parameters are typed.** The laminas interface predates scalar type declarations
 *   and declared none, so every implementation and every call site was unchecked.
 *
 * The name says what an implementation does rather than what it is, which is the point:
 * a class satisfies this by translating, not by belonging to a translation package.
 */
interface TranslatesMessages
{
    /** The domain a message belongs to when nothing says otherwise. laminas-i18n's name for it. */
    public const DEFAULT_TEXT_DOMAIN = 'default';

    /**
     * @param string $message the source text, which is also the key
     * @param string $textDomain the catalog to look in
     * @param string|null $locale null for the translator's current locale
     * @return string the translation, or `$message` when there is none
     */
    public function translate(
        string $message,
        string $textDomain = self::DEFAULT_TEXT_DOMAIN,
        ?string $locale = null
    ): string;
}
