<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use BjyAuthorize\View\Helper\IsAllowed;
use InvalidArgumentException;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Url;
use SionModel\Entity\Entity;
use SionModel\Form\TouchForm;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function is_numeric;
use function is_string;

class TouchButton extends AbstractHelper
{
    public bool $firstRun = true;

    /**
     * @param Entity[] $entities
     */
    public function __construct(public array $entities)
    {
    }

    public function __invoke(string $entity, string|int $id, string $text = 'Confirm'): string
    {
        //validate input
        if (is_string($id) || ! is_numeric($id)) {
            throw new InvalidArgumentException("Invalid id passed to touchButton");
        }
        if (! array_key_exists($entity, $this->entities)) {
            throw new InvalidArgumentException("No configuration found for entity $entity");
        }
        $entitySpec = $this->entities[$entity];
        if (
            ! isset($entitySpec->touchJsonRoute)
            || (! isset($entitySpec->touchRouteParams) && ! isset($entitySpec->defaultRouteParams))
        ) {
            throw new InvalidArgumentException(
                "Please set touch_json_route and touch_json_route_key to use the touchButton view helper for '$entity'"
            );
        }
        /** @var IsAllowed $isAllowedPlugin */
        $isAllowedPlugin = $this->view->plugin('isAllowed');
        /** @var Url $urlPlugin */
        $urlPlugin = $this->view->plugin('url');
        Assert::isCallable($isAllowedPlugin);
        Assert::isCallable($urlPlugin);

        $params = ! empty($entitySpec->touchJsonRouteParams)
            ? $entitySpec->touchJsonRouteParams
            : $entitySpec->defaultRouteParams;
        Assert::count(
            $params,
            1,
            "EditPencil view helper is only compatible with entities that take only one parameter. "
            . "`$entity` doesn't comply."
        );
        $isAllowed = $isAllowedPlugin('route/' . $entitySpec->touchJsonRoute);
        if (! $isAllowed) {
            return '';
        }
        $touchRouteParams = [];
        foreach ($params as $key => $param) {
            $touchRouteParams[$key] = $id;
            break;
        }

        $form = new TouchForm();
        $url  = $urlPlugin($entitySpec->touchJsonRoute, $touchRouteParams);

        $finalMarkup    = $this->view->partial('sion-model/sion-model/touch-button', [
            'form'        => $form,
            'url'         => $url,
            'buttonText'  => $text,
            'outputModal' => $this->firstRun,
        ]);
        $this->firstRun = false;

        return $finalMarkup;
    }
}
