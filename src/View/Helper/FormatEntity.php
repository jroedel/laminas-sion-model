<?php

namespace SionModel\View\Helper;

use InvalidArgumentException;
use Zend\View\Helper\AbstractHelper;
use SionModel\Service\EntitiesService;
use SionModel\Entity\Entity;

class FormatEntity extends AbstractHelper
{
    /**
     * @var Entity[] $entities
     */
    protected $entities = [];

    /**
     * @var bool $routePermissionCheckingEnabled
     */
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
     *
     * @param string $entityType
     * @param mixed[] $data
     * @param array $options
     * Available options: displayAsLink(bool), displayEditPencil(bool), displayFlag(bool)
     * @throws InvalidArgumentException
     */
    public function __invoke($entityType, $data, array $options = [])
    {
        if (! isset($entityType) || ! key_exists($entityType, $this->entities)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('Unknown entity type passed: ' . $entityType);
            }
        }
        $isDeleted = isset($data['isDeleted']) && $data['isDeleted'];
        $entitySpec = $this->entities[$entityType];

        //forward request to registered view helper if we have one
        if (isset($entitySpec->formatViewHelper) && ! $isDeleted) {
            $viewHelperName = $entitySpec->formatViewHelper;
            return $this->view->$viewHelperName($entityType, $data, $options);
        }

        //set default options
        $options = [
            'displayFlag' => isset($options['displayFlag']) ? (bool)$options['displayFlag'] : true,
            'displayAsLink' => isset($options['displayAsLink']) ? (bool)$options['displayAsLink'] : true,
            'displayEditPencil' => isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : true,
            'failSilently' => isset($options['failSilently']) ? (bool)$options['failSilently'] : true,
            'displayInactiveLabel' => isset($options['displayInactiveLabel']) ? (bool)$options['displayInactiveLabel'] : false,
        ];
        if ($isDeleted) {
            $options['displayAsLink'] = false;
            $options['displayEditPencil'] - false;
            $options['displayFlag'] = false;
        }

        if (! is_array($data)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new InvalidArgumentException('$data should be an array.');
            }
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
        if ($data[$entitySpec->nameField] instanceof \DateTime) {
            $name = $this->view->dateFormat(
                $data[$entitySpec->nameField],
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE
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
//                     throw new \Exception("Error while redirecting after a successful edit. Missing param `$entityField`");
                    } else {
                        $editParams[$routeParam] = $data[$entityField];
                    }
                }
                if (count($editParams) === count($entitySpec->editRouteParams)) {
                    $finalMarkup .= $this->view->editPencilNew($editRoute, $editParams);
                }
            } elseif ($entitySpec->editRouteKeyField &&
                isset($data[$entitySpec->editRouteKeyField])
            ) {
                $editId = $data[$entitySpec->editRouteKeyField];
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
        if ($options['displayInactiveLabel'] &&
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
        $route = $entitySpec->showRoute;
        if (! isset($route) || ! $this->isActionAllowed('show', $entityType, $data)) {
            return $linkText;
        }
        if (is_array($entitySpec->showRouteParams)) {
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
            $id = $data[$entitySpec->showRouteKeyField];
            return sprintf('<a href="%s">%s</a>', $this->view->url($route, [$routeKey => $id]), $linkText);
        }
        if (is_array($entitySpec->defaultRouteParams)) {
            $params = [];
            foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                if (! isset($data[$entityField])) {
                    //@todo log this
                    //                     throw new \Exception("Error while redirecting after a successful edit. Missing param `$entityField`");
                } else {
                    $params[$routeParam] = $data[$entityField];
                }
            }
            if (count($params) === count($entitySpec->defaultRouteParams)) {
                return sprintf('<a href="%s">%s</a>', $this->view->url($route, $params), $linkText);
            }
        }
        return $linkText;
    }

    protected function isActionAllowed($action, $entityType, $object)
    {
        if (! $this->getRoutePermissionCheckingEnabled()) {
            return true;
        }
        if (! isset(Entity::$isActionAllowedPermissionProperties[$action])) {
            throw new InvalidArgumentException('Invalid action parameter');
        }
        $entitySpec = $this->entities[$entityType];

        /**
         * isAllowed plugin
         * @var \Zend\View\Helper\HelperInterface $isAllowedPlugin
         */
        $isAllowedPlugin = null;
        try {
            $isAllowedPlugin = $this->view->plugin('isAllowed');
        } catch (\Exception $e) {
        }
        //if we don't have the isAllowed plugin, just allow
        if (! is_callable($isAllowedPlugin)) {
            return true;
        }

        //check the route permissions of BjyAuthorize
        $routeProperty = array_key_exists($action, Entity::$actionRouteProperties) ? Entity::$actionRouteProperties[$action] : null;
        if (
            isset($routeProperty) && isset($entitySpec->$routeProperty) &&
            ! $isAllowedPlugin->__invoke('route/' . $entitySpec->$routeProperty)
        ) {
            return false;
        }

        if (! isset($entitySpec->aclResourceIdField)) {
            return true;
        }

        $permissionProperty = Entity::$isActionAllowedPermissionProperties[$action];
        if (! isset($entitySpec->$permissionProperty)) {
            //we don't need the permission, just the resourceId
            return $isAllowedPlugin->__invoke($object[$entitySpec->aclResourceIdField]);
        }

        return $isAllowedPlugin->__invoke($object[$entitySpec->aclResourceIdField], $entitySpec->$permissionProperty);
    }

    /**
    * Get the routePermissionCheckingEnabled value
    * @return bool
    */
    public function getRoutePermissionCheckingEnabled()
    {
        return $this->routePermissionCheckingEnabled;
    }

    /**
    *
    * @param bool $routePermissionCheckingEnabled
    * @return self
    */
    public function setRoutePermissionCheckingEnabled($routePermissionCheckingEnabled)
    {
        $this->routePermissionCheckingEnabled = $routePermissionCheckingEnabled;
        return $this;
    }
}
