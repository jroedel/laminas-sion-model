<?php
namespace SionModel\View\Helper;

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
     *
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
     * @throws \InvalidArgumentException
     */
    public function __invoke($entityType, $data, array $options = [])
    {
        if (!isset($entityType) || !key_exists($entityType, $this->entities)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Unknown entity type passed: '.$entityType);
            }
        }
        $isDeleted = key_exists('isDeleted', $data) && $data['isDeleted'];
        $entitySpec = $this->entities[$entityType];

        //forward request to registered view helper if we have one
        if (!is_null($entitySpec->formatViewHelper) && !$isDeleted) {
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

        if (!is_array($data)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('$data should be an array.');
            }
        }
        if (!$entitySpec->entityKeyField ||
            !isset($data[$entitySpec->entityKeyField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Id field not set for entity '.$entityType);
            }
        }
        if (!$entitySpec->nameField ||
            !isset($data[$entitySpec->nameField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Name field not set for entity '.$entityType);
            }
        }

    	$finalMarkup = '';
    	if ($options['displayFlag'] &&
    	    $entitySpec->countryField &&
    	    isset($data[$entitySpec->countryField]) &&
    	    2 === strlen($data[$entitySpec->countryField])
    	) {
    		$finalMarkup .= $this->view->flag($data[$entitySpec->countryField])."&nbsp;";
    	}

    	//if our name field is a date, format it as a medium date
    	if ($data[$entitySpec->nameField] instanceof \DateTime) {
    	    $name = $this->view->dateFormat($data[$entitySpec->nameField],
	            \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
    	} else {
        	$name = $data[$entitySpec->nameField];
    	    if ($entitySpec->nameFieldIsTranslateable) {
    	        $name = $this->view->translate($name);
    	    }
    	}

    	if ($options['displayAsLink']) {
    	    $finalMarkup .= $this->wrapAsLink($entityType, $data, $this->view->escapeHtml($name));
    	} else {
    	    $finalMarkup .= $this->view->escapeHtml($name);
    	}

    	if ($options['displayEditPencil'] &&
    	    $entitySpec->editRouteKeyField &&
            isset($data[$entitySpec->editRouteKeyField])
	    ) {
    	    $editId = $data[$entitySpec->editRouteKeyField];
    		$finalMarkup .= $this->view->editPencil($entityType, $editId);
    	}
    	if ($options['displayInactiveLabel'] &&
    	    (isset($data['isActive']) && is_bool($active = $data['isActive']) ||
	        isset($data['active']) && is_bool($active = $data['active']))
        ) {
            if (!$active) {
                $finalMarkup .= ' <span class="label label-warning">'.$this->view->translate('Inactive').'</span>';
            }
    	}
    	return $finalMarkup;
    }

    /**
     * @todo remove dependency on isAllowed view helper, make optional
     * @param string $entityType
     * @param array $data
     * @param string $linkText
     * @return string
     */
    protected function wrapAsLink($entityType, $data, $linkText)
    {
        $entitySpec = $this->entities[$entityType];
        if ($entitySpec->showRoute &&
            $entitySpec->showRouteKey &&
            $entitySpec->showRouteKeyField &&
            isset($data[$entitySpec->showRouteKeyField]) &&
            $this->isActionAllowed('show', $entityType, $data)
        ) {
            $route = $entitySpec->showRoute;
            $routeKey = $entitySpec->showRouteKey;
            $id = $data[$entitySpec->showRouteKeyField];
            return sprintf('<a href="%s">%s</a>',$this->view->url($route, [$routeKey => $id]), $linkText);
        }
        return $linkText;
    }

    protected function isActionAllowed($action, $entityType, $object)
    {
        if (!$this->getRoutePermissionCheckingEnabled()) {
            return true;
        }
        if (!array_key_exists($action, Entity::$isActionAllowedPermissionProperties)) {
            throw new \InvalidArgumentException('Invalid action parameter');
        }
        $entitySpec = $this->entities[$entityType];

        /**
         * isAllowed plugin
         * @var \Zend\View\Helper\HelperInterface $isAllowedPlugin
         */
        $isAllowedPlugin = null;
        try {
            $isAllowedPlugin = $this->view->plugin('isAllowed');
        } catch (Exception $e) {
        }
        //if we don't have the isAllowed plugin, just allow
        if (!is_callable($isAllowedPlugin)) {
            return true;
        }

        //check the route permissions of BjyAuthorize
        $routeProperty = array_key_exists($action, Entity::$actionRouteProperties) ? Entity::$actionRouteProperties[$action] : null;
        if (!is_null($routeProperty) && !is_null($entitySpec->$routeProperty) &&
            !$isAllowedPlugin->__invoke('route/'.$entitySpec->$routeProperty)
        ) {
            return false;
        }

        if (is_null($entitySpec->aclResourceIdField)) {
            return true;
        }

        $permissionProperty = Entity::$isActionAllowedPermissionProperties[$action];
        if (is_null($entitySpec->$permissionProperty)) {
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
