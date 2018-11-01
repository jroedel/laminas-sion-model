<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\View\Helper\EditPencil;

/**
 * Factory responsible of constructing the FormatEntity view helper
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class EditPencilFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        $entityService = $parentLocator->get('SionModel\Service\EntitiesService');

        $viewHelper = new EditPencil($entityService);
        return $viewHelper;
    }
}
