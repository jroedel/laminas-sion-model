<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\View\Helper\TouchButton;

class TouchButtonFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $parentLocator = $serviceLocator->getServiceLocator();
        /**
         * @var EntitiesService $entities
         */
        $entities = $parentLocator->get ( 'SionModel\Service\EntitiesService' );
        $viewHelper = new TouchButton($entities->getEntities());
        return $viewHelper;
    }
}
