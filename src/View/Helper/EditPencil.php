<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use BjyAuthorize\View\Helper\IsAllowed;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Url;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;
use Webmozart\Assert\Assert;

use function sprintf;

class EditPencil extends AbstractHelper
{
    /** @var Entity[] $entities */
    protected array $entities;

    public function __construct(EntitiesService $entityService)
    {
        $this->entities = $entityService->getEntities();
    }

    /**
     * Return an HTML string showing a pencil which will link to an edit form
     */
    public function __invoke(string $entityType, int $id, bool $openInNewTab = false): string
    {
        Assert::keyExists($this->entities, $entityType, "Unknown entity `$entityType`");
        Assert::notNull($this->entities[$entityType]->editRoute);
        Assert::greaterThan($id, 0);
        Assert::true(
            isset($this->entities[$entityType]->editRouteParams)
            || isset($this->entities[$entityType]->defaultRouteParams),
            "The EditPencil view helper can't be called without having set the Entity's (`$entityType`) "
            . "editRouteParams or defaultRouteParams."
        );
        /** @var IsAllowed $isAllowedPlugin */
        $isAllowedPlugin = $this->view->plugin('isAllowed');
        /** @var Url $urlPlugin */
        $urlPlugin = $this->view->plugin('url');
        Assert::isCallable($isAllowedPlugin);
        Assert::isCallable($urlPlugin);
        $params = ! empty($this->entities[$entityType]->editRouteParams)
            ? $this->entities[$entityType]->editRouteParams
            : $this->entities[$entityType]->defaultRouteParams;
        Assert::count(
            $params,
            1,
            "EditPencil view helper is only compatible with entities that take only one parameter. "
                . "`$entityType` doesn't comply."
        );
        $isAllowed = $isAllowedPlugin('route/' . $this->entities[$entityType]->editRoute);
        if (! $isAllowed) {
            return '';
        }
        $otherAttributes = $openInNewTab ? ' target="_blank"' : '';

        $editRouteParams = [];
        foreach ($params as $key => $param) {
            $editRouteParams[$key] = $id;
            break;
        }
        $pattern = ' <a href="%s"%s><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
        return sprintf(
            $pattern,
            $urlPlugin($this->entities[$entityType]->editRoute, $editRouteParams),
            $otherAttributes
        );
    }
}
