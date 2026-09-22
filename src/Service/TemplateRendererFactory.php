<?php

declare(strict_types=1);

namespace SionModel\Service;

use SionModel\I18n\TranslatesMessages;
use Psr\Container\ContainerInterface;
use SionModel\Mailing\TwigTemplateRenderer;
use SionModel\Twig\MailExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function is_array;
use function is_string;

/**
 * The Twig environment mail bodies render with.
 *
 * Separate from any environment the host renders pages with, on purpose: a page
 * environment carries the site chrome, the request, the CSP nonce — none of which a mail
 * has — and mail is sent from console commands as readily as from a request. Templates
 * are addressed by namespace; this package's live under `@sion-model/…`, and a host adds
 * its own directories under `sion_model.mail_template_paths` (`namespace => directory`).
 *
 * No compile cache: a notice run renders a few dozen bodies from two templates.
 *
 * The translator comes from the host under `TranslatesMessages::class` — the one it
 * configures with its catalogs — which is what the `translate` view helper used inside the
 * `.phtml` originals.
 */
final class TemplateRendererFactory
{
    public const TEMPLATE_NAMESPACE = 'sion-model';

    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): TwigTemplateRenderer {
        $config = $container->get('Config');
        $paths  = is_array($config['sion_model']['mail_template_paths'] ?? null)
            ? $config['sion_model']['mail_template_paths']
            : [];

        $loader = new FilesystemLoader();
        $loader->addPath(self::templatePath(), self::TEMPLATE_NAMESPACE);
        foreach ($paths as $namespace => $directory) {
            if (is_string($namespace) && is_string($directory)) {
                $loader->addPath($directory, $namespace);
            }
        }

        $twig = new Environment($loader, [
            'autoescape'       => 'html',
            'strict_variables' => true,
            'cache'            => false,
        ]);
        /** @var TranslatesMessages $translator */
        $translator = $container->get(TranslatesMessages::class);
        $twig->addExtension(new MailExtension($translator));

        return new TwigTemplateRenderer($twig);
    }

    public static function templatePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }
}
