<?php

namespace SionModel\View\Helper;

use Closure;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;

/**
 * The little pencil that links an entity to its edit route.
 *
 * A plain class since 2026-09. The two collaborators it used to reach through the
 * renderer — `$this->view->isAllowed()` and `$this->view->url()` — are injected as
 * closures, because there is no renderer to reach them through any more.
 */
class EditPencil
{
    /**
     * @var Entity[] $entities
     */
    protected $entities = [];

    /**
     * @param EntitiesService $entityService
     * @param Closure(string): bool|null $isAllowed the `isAllowed` helper. Null keeps the
     *        original's posture for a host with no route permissions configured: allow.
     * @param Closure(string, array): string|null $url the `url` helper. Without one there
     *        is no href to write, so the pencil is omitted rather than rendered dead.
     */
    public function __construct(
        $entityService,
        private readonly ?Closure $isAllowed = null,
        private readonly ?Closure $url = null
    ) {
        $this->entities = $entityService->getEntities();
    }

    /**
     *
     * @param string $entityType
     * @param int $id
     */
    public function __invoke($entityType, $id, $openInNewTab = false)
    {
        //if there's not enough info we won't do anything
        if (
            ! $id || $id == '' || ! isset($this->entities[$entityType]) ||
            ! $this->entities[$entityType]->editRoute ||
            (! $this->entities[$entityType]->editRouteKey) //&& !$this->entities[$entityType]->editRouteParams
//                 && !$this->entities[$entityType]->defaultRouteParams)
        ) {
            return '';
        }

//         $entitySpec = $this->entities[$entityType];
        $isAllowed = true; //if there is an exception, we'll assume there's no route permissions configured
        try {
            if (null !== $this->isAllowed) {
                $isAllowed = ($this->isAllowed)('route/' . $this->entities[$entityType]->editRoute);
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

        /*
         * @todo find url according to the following priority:
         * 1. using editRouteParams
         * 2. using editRouteKey/Field
         * 3. using defaultParams
         *
         * In order to do this we need to receive more info. Major BC break
         */

        $pattern = ' <a href="%s" %s><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
        $finalMarkup = sprintf(
            $pattern,
            ($this->url)(
                $this->entities[$entityType]->editRoute,
                [$this->entities[$entityType]->editRouteKey => $id]
            ),
            $otherAttributes
        );
        return $finalMarkup;
    }
}
