<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\View\Helper\FormatEntity;

/**
 * Factory responsible of constructing the FormatEntity view helper
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class FormatEntityFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return FormatEntity
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $entityService = $serviceLocator->get ( 'SionModel\Service\EntitiesService' );

        $viewHelper = new FormatEntity($entityService);
		return $viewHelper;
    }
}
