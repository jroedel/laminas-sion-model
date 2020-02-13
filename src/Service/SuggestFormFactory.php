<?php
namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Form\SuggestForm;

/**
 * Factory responsible of priming the SuggestForm
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
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
        $entitiesService = $container->get(EntitiesService::class);
        $entities = $entitiesService->getEntities();
        $entityHaystack = [];
        foreach ($entities as $entity) {
            $entityHaystack[] = $entity->name;
        }
        
        $form = new SuggestForm($entityHaystack);
        //@todo both of these must be configurable!!! FIXME
        $auth = $container->get('zfcuser_auth_service');
//         $personProvider = $container->get(PatresTable::class);
        $form->prepareForSuggestion($auth,$personProvider);

        return $form;
    }
}
