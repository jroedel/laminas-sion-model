<?php

declare(strict_types=1);

namespace SionModel\Twig;

use SionModel\Form\BootstrapFormRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The laminas form view helpers, as Twig functions, for the ported form routes.
 *
 * One function per helper the `.phtml` partials call, named the same, so a ported
 * template can be read line-by-line against the original it replaces — which is how
 * the association edit port was reviewed, and the reason these are not collapsed
 * into a single `form(form)` call. A single call would have been shorter and would
 * have made the diff against `fields-partial.phtml` unreadable.
 *
 * Every one of them returns markup, so every one is declared `is_safe: html`. A host
 * builds its Twig environment with autoescaping on (schoenstatt.link does, in
 * App\Twig\TwigFactory) and without the declaration the whole form arrives on screen
 * as escaped source.
 *
 * The rendering itself is SionModel\Form\BootstrapFormRenderer's; this class is only
 * the Twig binding, and it lives here rather than in the host so that any project
 * rendering this package's forms gets the same twelve function names.
 */
final class FormExtension extends AbstractExtension
{
    public function __construct(private readonly BootstrapFormRenderer $renderer)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        $html = ['is_safe' => ['html']];

        return [
            new TwigFunction('form_open', $this->renderer->open(...), $html),
            new TwigFunction('form_close', $this->renderer->close(...), $html),
            new TwigFunction('form_row', $this->renderer->row(...), $html),
            new TwigFunction('form_label', $this->renderer->label(...), $html),
            new TwigFunction('form_element', $this->renderer->element(...), $html),
            new TwigFunction('form_errors', $this->renderer->errors(...), $html),
            //`formSelectWithoutOptions` in the .phtml. Only the publication form's five
            //selectize pickers use it; see the method's docblock for why they cannot
            //render their options.
            new TwigFunction('form_select_without_options', $this->renderer->selectWithoutOptions(...), $html),
            new TwigFunction('help_block', $this->renderer->helpBlock(...), $html),
            new TwigFunction('form_text', $this->renderer->text(...), $html),
            new TwigFunction('form_hidden', $this->renderer->hidden(...), $html),
            new TwigFunction('form_submit', $this->renderer->submit(...), $html),
            new TwigFunction('form_button', $this->renderer->button(...), $html),
        ];
    }
}
