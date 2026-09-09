<?php

declare(strict_types=1);

namespace SionModel\Mailing;

use Twig\Environment;

/**
 * {@see TemplateRendererInterface} over a Twig environment.
 *
 * The environment is the caller's: `SionModel\Service\TemplateRendererFactory` builds
 * one for mail, with this package's templates under `@sion-model/…` and whatever paths
 * the host adds under `sion_model.mail_template_paths`, and
 * {@see \SionModel\Twig\MailExtension} for the two functions the templates call.
 */
final class TwigTemplateRenderer implements TemplateRendererInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(string $template, array $params): string
    {
        return $this->twig->render($template, $params);
    }
}
