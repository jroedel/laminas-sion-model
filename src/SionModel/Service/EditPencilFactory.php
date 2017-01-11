<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\View\Helper\EditPencil;

/**
 * Factory responsible of constructing the FormatEntity view helper
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class EditPencilFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return FormatEntity
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $parentLocator = $serviceLocator->getServiceLocator();
        $entityService = $parentLocator->get ( 'SionModel\Service\EntitiesService' );

        $viewHelper = new EditPencil($entityService);
		return $viewHelper;
    }
}
