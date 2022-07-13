<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use BjyAuthorize\View\Helper\IsAllowed;
use DateTime;
use IntlDateFormatter;
use InvalidArgumentException;
use Laminas\View\Helper\AbstractHelper;
use SionModel\Controller\SionController;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function count;
use function is_bool;
use function sprintf;
use function strlen;

class FormatEntity extends AbstractHelper
{
    /** @var Entity[] $entities */
    protected array $entities = [];

    public function __construct(EntitiesService $entityService)
    {
        $this->entities = $entityService->getEntities();
    }

    /**
     * @param array $options displayAsLink(bool), displayEditPencil(bool), displayFlag(bool)
     */
    public function __invoke(string $entityType, array $data, array $options = []): string
    {
        if (! isset($entityType) || ! array_key_exists($entityType, $this->entities)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('Unknown entity type passed: ' . $entityType);
            }
        }
        $isDeleted  = isset($data['isDeleted']) && $data['isDeleted'];
        $entitySpec = $this->entities[$entityType];

        //forward request to registered view helper if we have one
        if (isset($entitySpec->formatViewHelper) && ! $isDeleted) {
            $viewHelperName = $entitySpec->formatViewHelper;
            return $this->view->$viewHelperName($entityType, $data, $options);
        }

        //set default options
        $options = [
            'displayFlag'          => ! isset($options['displayFlag']) || $options['displayFlag'],
            'displayAsLink'        => ! isset($options['displayAsLink']) || $options['displayAsLink'],
            'displayEditPencil'    => ! isset($options['displayEditPencil']) || $options['displayEditPencil'],
            'failSilently'         => ! isset($options['failSilently']) || $options['failSilently'],
            'displayInactiveLabel' => isset($options['displayInactiveLabel']) && $options['displayInactiveLabel'],
        ];
        if ($isDeleted) {
            $options['displayAsLink']     = false;
            $options['displayEditPencil'] = false;
            $options['displayFlag']       = false;
        }

        $finalMarkup = '';
        if (
            $options['displayFlag'] &&
            $entitySpec->countryField &&
            isset($data[$entitySpec->countryField]) &&
            2 === strlen($data[$entitySpec->countryField])
        ) {
            $finalMarkup .= $this->view->flag($data[$entitySpec->countryField]) . "&nbsp;";
        }

        //if our name field is a date, format it as a medium date
        if ($data[$entitySpec->nameField] instanceof DateTime) {
            $name = $this->view->dateFormat(
                $data[$entitySpec->nameField],
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::NONE
            );
        } else {
            $name = $data[$entitySpec->nameField];
            if ($entitySpec->nameFieldIsTranslatable) {
                $name = $this->view->translate($name);
            }
        }

        if ($options['displayAsLink']) {
            $finalMarkup .= $this->wrapAsLink($entityType, $data, $this->view->escapeHtml($name));
        } else {
            $finalMarkup .= $this->view->escapeHtml($name);
        }

        if ($options['displayEditPencil'] && isset($entitySpec->editRoute)) {
            $editRoute = $entitySpec->editRoute;
            $editParams = SionController::assembleRouteParamValues($entitySpec, 'edit', $data);
            $finalMarkup .= $this->view->editPencilNew($editRoute, $editParams);
        }
        if (
            $options['displayInactiveLabel'] &&
            (isset($data['isActive']) && is_bool($active = $data['isActive']) ||
            isset($data['active']) && is_bool($active = $data['active']))
        ) {
            if (! $active) {
                $finalMarkup .= ' <span class="label label-warning">' . $this->view->translate('Inactive') . '</span>';
            }
        }
        return $finalMarkup;
    }

    protected function wrapAsLink(string $entityType, array $data, string $linkText): string
    {
        $entitySpec = $this->entities[$entityType];
        $route      = $entitySpec->showRoute;
        if (! isset($route) || ! $this->isActionAllowed('show', $entityType, $data)) {
            return $linkText;
        }
        if (! empty($entitySpec->showRouteParams)) {
            $params = [];
            foreach ($entitySpec->showRouteParams as $routeParam => $entityField) {
                Assert::keyExists(
                    $data,
                    $entityField,
                    "showRouteParams for entity `$entityType` mentions an unknown field `$entityField`"
                );
                $params[$routeParam] = $data[$entityField];
            }
            Assert::count($params, count($entitySpec->showRouteParams));
            return sprintf('<a href="%s">%s</a>', $this->view->url($route, $params), $linkText);
        }
        if (! empty($entitySpec->defaultRouteParams)) {
            $params = [];
            foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                Assert::keyExists(
                    $data,
                    $entityField,
                    "Unable to find entityField `$entityField` for `$entityType`"
                );
                $params[$routeParam] = $data[$entityField];
            }
            if (count($params) === count($entitySpec->defaultRouteParams)) {
                return sprintf('<a href="%s">%s</a>', $this->view->url($route, $params), $linkText);
            }
        }
        return $linkText;
    }

    protected function isActionAllowed(string $action, string $entityType, array $object): bool
    {
        if (! isset(Entity::IS_ACTION_ALLOWED_PERMISSION_PROPERTIES[$action])) {
            throw new InvalidArgumentException('Invalid action parameter');
        }
        $entitySpec = $this->entities[$entityType];

        /** @var IsAllowed $isAllowedPlugin */
        $isAllowedPlugin = $this->view->plugin('isAllowed');
        Assert::isCallable($isAllowedPlugin);

        //check the route permissions of BjyAuthorize
        $routeProperty = array_key_exists($action, Entity::ACTION_ROUTE_PROPERTIES)
            ? Entity::ACTION_ROUTE_PROPERTIES[$action]
            : null;
        if (
            isset($routeProperty) && isset($entitySpec->$routeProperty) &&
            ! $isAllowedPlugin('route/' . $entitySpec->$routeProperty)
        ) {
            return false;
        }

        if (! isset($entitySpec->aclResourceIdField)) {
            return true;
        }

        $permissionProperty = Entity::IS_ACTION_ALLOWED_PERMISSION_PROPERTIES[$action];
        if (! isset($entitySpec->$permissionProperty)) {
            //we don't need the permission, just the resourceId
            return $isAllowedPlugin($object[$entitySpec->aclResourceIdField]);
        }

        return $isAllowedPlugin($object[$entitySpec->aclResourceIdField], $entitySpec->$permissionProperty);
    }
}
