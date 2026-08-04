<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\View\Helper\TouchButton;

class TouchButtonFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /**
         * @var EntitiesService $entities
         */
        $entities = $container->get('SionModel\Service\EntitiesService');
        $viewHelper = new TouchButton($entities->getEntities());
        return $viewHelper;
    }
}
