<?php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;
use SionModel\Entity\Entity;
use SionModel\Form\TouchForm;

class TouchButton extends AbstractHelper
{
    /**
     * @var Entity[] $entities
     */
    public $entities;

    /**
     * @var bool $firstRun
     */
    public $firstRun = true;

    public function __construct($entities)
    {
        $this->entities = $entities;
    }

    public function __invoke($entity, $id, $text = 'Confirm')
    {
        //validate input
        if (! isset($entity) || ! isset($id) || ! (is_string($id) || is_numeric($id))) {
            throw new \InvalidArgumentException("Invalid parameters passed to touchButton");
        }
        if (! key_exists($entity, $this->entities)) {
            throw new \InvalidArgumentException("No configuration found for entity $entity");
        }
        $entitySpec = $this->entities[$entity];
        if (
            ! isset($entitySpec->touchJsonRoute) || ! isset($entitySpec->touchJsonRouteKey)
        ) {
            throw new \InvalidArgumentException("Please set touch_json_route and touch_json_route_key to use the touchButton view helper for '$entity'");
        }

        $form = new TouchForm();

        $url = $this->view->url($entitySpec->touchJsonRoute, [
            $entitySpec->touchJsonRouteKey => $id]);

        $finalMarkup = $this->view->partial('sion-model/sion-model/touch-button', [
            'form'          => $form,
            'url'           => $url,
            'buttonText'    => $text,
            'outputModal'   => $this->firstRun
        ]);
        $this->firstRun = false;

        return $finalMarkup;
    }
}
