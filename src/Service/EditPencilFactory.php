<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\View\Helper\EditPencil;

/**
 * Factory responsible of constructing the FormatEntity view helper
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class EditPencilFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $entityService = $container->get('SionModel\Service\EntitiesService');

        $viewHelper = new EditPencil($entityService);
        return $viewHelper;
    }
}
