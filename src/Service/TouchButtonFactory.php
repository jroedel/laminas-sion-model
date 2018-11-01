<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\View\Helper\TouchButton;

class TouchButtonFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        /**
         * @var EntitiesService $entities
         */
        $entities = $parentLocator->get('SionModel\Service\EntitiesService');
        $viewHelper = new TouchButton($entities->getEntities());
        return $viewHelper;
    }
}
