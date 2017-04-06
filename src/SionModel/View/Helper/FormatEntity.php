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
     *
     * @param EntitiesService $entityService
     */
    public function __construct($entityService)
    {
        $this->entities = $entityService->getEntities();
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
        //set default options
        $options = [
            'displayFlag' => isset($options['displayFlag']) ? (bool)$options['displayFlag'] : true,
            'displayAsLink' => isset($options['displayAsLink']) ? (bool)$options['displayAsLink'] : true,
            'displayEditPencil' => isset($options['displayEditPencil']) ? (bool)$options['displayEditPencil'] : true,
            'failSilently' => isset($options['failSilently']) ? (bool)$options['failSilently'] : true,
            'displayInactiveLabel' => isset($options['displayInactiveLabel']) ? (bool)$options['displayInactiveLabel'] : false,
        ];

        if (!isset($entityType) || !isset($this->entities[$entityType])) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Unknown entity type passed: '.$entityType);
            }
        }
        if (!is_array($data)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('$data should be an array.');
            }
        }
        if (!$this->entities[$entityType]->entityKeyField ||
            !isset($data[$this->entities[$entityType]->entityKeyField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Id field not set for entity '.$entityType);
            }
        }
        if (!$this->entities[$entityType]->nameField ||
            !isset($data[$this->entities[$entityType]->nameField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Name field not set for entity '.$entityType);
            }
        }

    	$finalMarkup = '';
    	if ($options['displayFlag'] &&
    	    $this->entities[$entityType]->countryField &&
    	    isset($data[$this->entities[$entityType]->countryField]) &&
    	    2 === strlen($data[$this->entities[$entityType]->countryField])
    	) {
    		$finalMarkup .= $this->view->flag($data[$this->entities[$entityType]->countryField])."&nbsp;";
    	}

    	//if our name field is a date, format it as a medium date
    	if ($data[$this->entities[$entityType]->nameField] instanceof \DateTime) {
    	    $this->view->dateFormat($data[$this->entities[$entityType]->nameField],
	            IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
    	} else {
        	$name = $data[$this->entities[$entityType]->nameField];
    	    if ($this->entities[$entityType]->nameFieldIsTranslateable) {
    	        $name = $this->view->translate($name);
    	    }
    	}

    	if ($options['displayAsLink'] &&
    	    $this->entities[$entityType]->showRoute &&
    	    $this->entities[$entityType]->showRouteKey &&
    	    $this->entities[$entityType]->showRouteKeyField &&
    	    isset($data[$this->entities[$entityType]->showRouteKeyField])
    	) {
    	    $route = $this->entities[$entityType]->showRoute;
    	    $routeKey = $this->entities[$entityType]->showRouteKey;
    	    $id = $data[$this->entities[$entityType]->showRouteKeyField];
    	    $finalMarkup .= '<a href="'.$this->view->url($route, [$routeKey => $id]).'">'.
    	        $this->view->escapeHtml($name).'</a>';
    	} else {
    	    $finalMarkup .= $this->view->escapeHtml($name);
    	}

    	if ($options['displayEditPencil'] &&
    	    $this->entities[$entityType]->editRouteKeyField &&
            isset($data[$this->entities[$entityType]->editRouteKeyField])
	    ) {
    	    $editId = $data[$this->entities[$entityType]->editRouteKeyField];
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
}
