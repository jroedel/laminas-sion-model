<?php
namespace SionModel\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class AllChangesServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var EntitiesService $entitiesService */
        $entitiesService = $serviceLocator->get('SionModel\Service\EntitiesService');
        $entiesSpecs = $entitiesService->getEntities();

        $sionModelsToQuery = [];
        foreach ($entiesSpecs as $entity => $entitySpec) {
            if (!is_null($entitySpec->sionModelClass) &&
                !key_exists($entitySpec->sionModelClass, $sionModelsToQuery) &&
                $serviceLocator->has($entitySpec->sionModelClass)
            ) {
                $sionModelsToQuery[$entitySpec->sionModelClass] =
                    $serviceLocator->get($entitySpec->sionModelClass);
            }
        }

        $changes = [];
        foreach ($sionModelsToQuery as $sionModelKey => $table) {
            $changes[$sionModelKey] = $table->getChanges();
        }
        $allChanges = call_user_func_array('array_merge', $changes);
        krsort($allChanges);
        return $allChanges;
    }
}
