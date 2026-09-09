<?php

namespace SionModel\View\Helper;

use Closure;

/**
 * {@see EditPencil} for an entity whose edit route takes named parameters rather than a
 * single key. A plain class since 2026-09; `isAllowed` and `url` used to come from the
 * renderer and are injected now.
 */
class EditPencilNew
{
    /**
     * @param Closure(string): bool|null $isAllowed the `isAllowed` helper; null means the
     *        host configured no route permissions, which the original treated as allow.
     * @param Closure(string, array): string|null $url the `url` helper; without one there
     *        is no href, so nothing is rendered.
     */
    public function __construct(
        private readonly ?Closure $isAllowed = null,
        private readonly ?Closure $url = null
    ) {
    }

    /**
     *
     * @param string $editRoute
     * @param array $params
     */
    public function __invoke($editRoute, $params, $openInNewTab = false)
    {
        //if there's not enough info we won't do anything
        if (! $params || ! $editRoute) {
            return '';
        }

        $isAllowed = true; //if there is an exception, we'll assume there's no route permissions configured
        try {
            if (null !== $this->isAllowed) {
                $isAllowed = ($this->isAllowed)("route/{$editRoute}");
            }
        } catch (\Exception $e) {
        }
        if (! $isAllowed) {
            return '';
        }
        if (null === $this->url) {
            return '';
        }
        $otherAttributes = $openInNewTab ? 'target="_blank"' : '';
        $pattern = ' <a href="%s" %s><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
        $finalMarkup = sprintf(
            $pattern,
            ($this->url)($editRoute, $params),
            $otherAttributes
        );
        return $finalMarkup;
    }
}
