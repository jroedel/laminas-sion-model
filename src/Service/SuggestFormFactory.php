<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Service\User;
use Psr\Container\ContainerInterface;
use SionModel\Form\SuggestForm;

class SuggestFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /**
         * @var EntitiesService $entitiesService
         */
        $entitiesService = $container->get(EntitiesService::class);
        $entities        = $entitiesService->getEntities();
        $entityHaystack  = [];
        foreach ($entities as $entity) {
            $entityHaystack[] = $entity->name;
        }

        $config = $container->get('Config');
        if (! isset($config['sion_model'])) {
            throw new Exception('Unable to fetch `sion_model` configuration key');
        }

        $form = new SuggestForm($entityHaystack);

        /** @var  User $userService **/
        $userService = $container->get('lmcuser_user_service');
        $user        = $userService->getAuthService()->getIdentity();

        if (isset($config['sion_model']['multi_person_user_person_provider'])) {
            $personProvider = $container->get($config['sion_model']['multi_person_user_person_provider']);
        } else {
            $personProvider = null;
        }
        $form->prepareForSuggestion($user, $personProvider);

        return $form;
    }
}
