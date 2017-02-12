<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\Form\SuggestForm;

/**
 * Factory responsible of priming the SuggestForm
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class SuggestFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return CreateTimelineEventForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /**
         * @var EntitiesService $entitiesService
         */
        $entitiesService = $serviceLocator->get ( 'SionModel\Service\EntitiesService' );
        $entities = $entitiesService->getEntities();
        $entityHaystack = [];
        foreach ($entities as $entity) {
            $entityHaystack[] = $entity->name;
        }
        
		$form = new SuggestForm($entityHaystack);
        $form->prepareForSuggestion($serviceLocator);

		return $form;
    }
}
