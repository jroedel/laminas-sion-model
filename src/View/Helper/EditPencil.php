<?php
namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;
use SionModel\Entity\Entity;

class EditPencil extends AbstractHelper
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
     * @param int $id
     */
    public function __invoke($entityType, $id, $openInNewTab = false)
    {
    	//if there's not enough info we won't do anything
    	if (!$id || $id == '' || !isset($this->entities[$entityType]) ||
    	    !$this->entities[$entityType]->editRoute ||
    	    !$this->entities[$entityType]->editRouteKey
        ) {
    		return '';
    	}

    	$isAllowed = true; //if there is an exception, we'll assume there's no route permissions configured
    	try {
    	    $isAllowed = $this->view->isAllowed('route/'.$this->entities[$entityType]->editRoute);
    	} catch (\Exception $e) {}
    	if (!$isAllowed) {
    	    return '';
    	}
    	$otherAttributes = $openInNewTab ? 'target="_blank"' : '';
		$pattern = ' <a href="%s" %s><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
		$finalMarkup = sprintf($pattern,
		    $this->view->url($this->entities[$entityType]->editRoute,
	        [$this->entities[$entityType]->editRouteKey => $id]),
	        $otherAttributes);
    	return $finalMarkup;
    }
}
