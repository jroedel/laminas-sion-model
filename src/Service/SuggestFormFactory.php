<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Form\SuggestForm;

/**
 * Factory responsible of priming the SuggestForm
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class SuggestFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /**
         * @var EntitiesService $entitiesService
         */
        $entitiesService = $container->get('SionModel\Service\EntitiesService');
        $entities = $entitiesService->getEntities();
        $entityHaystack = [];
        foreach ($entities as $entity) {
            $entityHaystack[] = $entity->name;
        }
        
        $form = new SuggestForm($entityHaystack);
        $form->prepareForSuggestion($container);

        return $form;
    }
}
