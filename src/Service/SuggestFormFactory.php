<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
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

        $config = $container->get('Config');
        if (! isset($config['sion_model'])) {
            throw new \Exception('Unable to fetch `sion_model` configuration key');
        }

        $form = new SuggestForm($entityHaystack);

        if (! isset($config['sion_model']['default_authentication_service'])) {
            throw new \Exception('No default authentication service found');
        }
        $auth = $container->get($config['sion_model']['default_authentication_service']);

        if (isset($config['sion_model']['multi_person_user_person_provider'])) {
            $personProvider = $container->get($config['sion_model']['multi_person_user_person_provider']);
        } else {
            $personProvider = null;
        }
        $form->prepareForSuggestion($auth, $personProvider);

        return $form;
    }
}
