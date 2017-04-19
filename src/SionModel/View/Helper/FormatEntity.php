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
        if (!isset($entityType) || !key_exists($entityType, $this->entities)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Unknown entity type passed: '.$entityType);
            }
        }
        $entitySpecification = $this->entities[$entityType];
        //forward request to registered view helper if we have one
        if (!is_null($entitySpecification->formatViewHelper)) {
            $viewHelperName = $entitySpecification->formatViewHelper;
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

        if (!is_array($data)) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('$data should be an array.');
            }
        }
        if (!$entitySpecification->entityKeyField ||
            !isset($data[$entitySpecification->entityKeyField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Id field not set for entity '.$entityType);
            }
        }
        if (!$entitySpecification->nameField ||
            !isset($data[$entitySpecification->nameField])
        ) {
            if ($options['failSilently']) {
                return '';
            } else {
                throw new \InvalidArgumentException('Name field not set for entity '.$entityType);
            }
        }

    	$finalMarkup = '';
    	if ($options['displayFlag'] &&
    	    $entitySpecification->countryField &&
    	    isset($data[$entitySpecification->countryField]) &&
    	    2 === strlen($data[$entitySpecification->countryField])
    	) {
    		$finalMarkup .= $this->view->flag($data[$entitySpecification->countryField])."&nbsp;";
    	}

    	//if our name field is a date, format it as a medium date
    	if ($data[$entitySpecification->nameField] instanceof \DateTime) {
    	    $this->view->dateFormat($data[$entitySpecification->nameField],
	            IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
    	} else {
        	$name = $data[$entitySpecification->nameField];
    	    if ($entitySpecification->nameFieldIsTranslateable) {
    	        $name = $this->view->translate($name);
    	    }
    	}

    	if ($options['displayAsLink'] &&
    	    $entitySpecification->showRoute &&
    	    $entitySpecification->showRouteKey &&
    	    $entitySpecification->showRouteKeyField &&
    	    isset($data[$entitySpecification->showRouteKeyField])
    	) {
    	    $route = $entitySpecification->showRoute;
    	    $routeKey = $entitySpecification->showRouteKey;
    	    $id = $data[$entitySpecification->showRouteKeyField];
    	    $finalMarkup .= '<a href="'.$this->view->url($route, [$routeKey => $id]).'">'.
    	        $this->view->escapeHtml($name).'</a>';
    	} else {
    	    $finalMarkup .= $this->view->escapeHtml($name);
    	}

    	if ($options['displayEditPencil'] &&
    	    $entitySpecification->editRouteKeyField &&
            isset($data[$entitySpecification->editRouteKeyField])
	    ) {
    	    $editId = $data[$entitySpecification->editRouteKeyField];
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
