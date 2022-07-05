<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use DateTime;
use Exception;
use IntlDateFormatter;
use InvalidArgumentException;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\HelperInterface;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;

use function array_key_exists;
use function assert;
use function count;
use function is_bool;
use function is_callable;
use function sprintf;
use function strlen;

class FormatEntity extends AbstractHelper
{
    /** @var Entity[] $entities */
    protected $entities = [];

    /** @var bool $routePermissionCheckingEnabled */
    protected $routePermissionCheckingEnabled = false;

    /**
     * @param EntitiesService $entityService
     */
    public function __construct($entityService, $routePermissionCheckingEnabled = false)
    {
        $this->entities = $entityService->getEntities();
        $this->setRoutePermissionCheckingEnabled($routePermissionCheckingEnabled);
    }

    /**
     * @param mixed[] $data
     * @param array $options
     * Available options: displayAsLink(bool), displayEditPencil(bool), displayFlag(bool)
     * @throws InvalidArgumentException
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

        if (
            ! $entitySpec->entityKeyField ||
            ! isset($data[$entitySpec->entityKeyField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('Id field not set for entity ' . $entityType);
            }
        }
        if (
            ! $entitySpec->nameField ||
            ! isset($data[$entitySpec->nameField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('Name field not set for entity ' . $entityType);
            }
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
            if ($entitySpec->editRouteParams) {
                $editParams = [];
                foreach ($entitySpec->editRouteParams as $routeParam => $entityField) {
                    if (! isset($data[$entityField])) {
                        //@todo log this
//                     throw new \Exception(
                    //"Error while redirecting after a successful edit. Missing param `$entityField`"
                    //);
                    } else {
                        $editParams[$routeParam] = $data[$entityField];
                    }
                }
                if (count($editParams) === count($entitySpec->editRouteParams)) {
                    $finalMarkup .= $this->view->editPencilNew($editRoute, $editParams);
                }
            } elseif (
                $entitySpec->editRouteKeyField &&
                isset($data[$entitySpec->editRouteKeyField])
            ) {
                $editId       = $data[$entitySpec->editRouteKeyField];
                $finalMarkup .= $this->view->editPencil($entityType, $editId);
            } elseif ($entitySpec->defaultRouteParams) {
                $editParams = [];
                foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                    if (! isset($data[$entityField])) {
                        //@todo log this
                        //                     throw new \Exception("Error while redirecting after a successful edit. Missing param `$entityField`");
                    } else {
                        $editParams[$routeParam] = $data[$entityField];
                    }
                }
                if (count($editParams) === count($entitySpec->defaultRouteParams)) {
                    $finalMarkup .= $this->view->editPencilNew($editRoute, $editParams);
                }
            }
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

    /**
     * @param string $entityType
     * @param array $data
     * @param string $linkText
     * @return string
     */
    protected function wrapAsLink($entityType, $data, $linkText)
    {
        $entitySpec = $this->entities[$entityType];
        $route      = $entitySpec->showRoute;
        if (! isset($route) || ! $this->isActionAllowed('show', $entityType, $data)) {
            return $linkText;
        }
        if (! empty($entitySpec->showRouteParams)) {
            $params = [];
            foreach ($entitySpec->showRouteParams as $routeParam => $entityField) {
                if (! isset($data[$entityField])) {
                    //@todo log this
//                     throw new \Exception("Error while redirecting after a successful edit. Missing param `$entityField`");
                } else {
                    $params[$routeParam] = $data[$entityField];
                }
            }
            if (count($params) === count($entitySpec->showRouteParams)) {
                return sprintf('<a href="%s">%s</a>', $this->view->url($route, $params), $linkText);
            }
        }
        if (
            $entitySpec->showRouteKey
            && $entitySpec->showRouteKeyField
            && isset($data[$entitySpec->showRouteKeyField])
            && $this->isActionAllowed('show', $entityType, $data)
        ) {
            $routeKey = $entitySpec->showRouteKey;
            $id       = $data[$entitySpec->showRouteKeyField];
            return sprintf('<a href="%s">%s</a>', $this->view->url($route, [$routeKey => $id]), $linkText);
        }
        if (! empty($entitySpec->defaultRouteParams)) {
            $params = [];
            foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                assert(isset($data[$entityField]), "Unable to find entityField `$entityField` for `$entityType`");
                $params[$routeParam] = $data[$entityField];
            }
            if (count($params) === count($entitySpec->defaultRouteParams)) {
                return sprintf('<a href="%s">%s</a>', $this->view->url($route, $params), $linkText);
            }
        }
        return $linkText;
    }

    /**
     * @psalm-param 'show' $action
     */
    protected function isActionAllowed(string $action, string $entityType, array $object)
    {
        if (! $this->getRoutePermissionCheckingEnabled()) {
            return true;
        }
        if (! isset(Entity::IS_ACTION_ALLOWED_PERMISSION_PROPERTIES[$action])) {
            throw new InvalidArgumentException('Invalid action parameter');
        }
        $entitySpec = $this->entities[$entityType];

        /**
         * isAllowed plugin
         *
         * @var HelperInterface $isAllowedPlugin
         */
        $isAllowedPlugin = null;
        try {
            $isAllowedPlugin = $this->view->plugin('isAllowed');
        } catch (Exception $e) {
        }
        //if we don't have the isAllowed plugin, just allow
        if (! is_callable($isAllowedPlugin)) {
            return true;
        }

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

    /**
     * Get the routePermissionCheckingEnabled value
     *
     * @return bool
     */
    public function getRoutePermissionCheckingEnabled()
    {
        return $this->routePermissionCheckingEnabled;
    }

    /**
     * @param bool $routePermissionCheckingEnabled
     * @return self
     */
    public function setRoutePermissionCheckingEnabled($routePermissionCheckingEnabled)
    {
        $this->routePermissionCheckingEnabled = $routePermissionCheckingEnabled;
        return $this;
    }
}
