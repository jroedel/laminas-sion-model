<?php

declare(strict_types=1);

namespace SionModel\Mailing;

/**
 * Renders a mail body from a named template.
 *
 * What {@see Mailer} needs of a template engine, and nothing more. The one
 * implementation is {@see TwigTemplateRenderer}; the interface exists so a host can
 * hand the mailer a differently configured environment, or a stub in a test, without
 * this package knowing.
 */
interface TemplateRendererInterface
{
    /**
     * @param string $template a name the renderer's loader resolves, e.g.
     *        `@sion-model/mailing/action-email.html.twig`
     * @param array<string, mixed> $params
     */
    public function render(string $template, array $params): string;
}
