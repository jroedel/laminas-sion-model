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

        /** @var \SionModel\Db\Model\SionTable[] $sionModelsToQuery */
        $sionModelsToQuery = [];
        foreach ($entiesSpecs as $entity => $entitySpec) {
            if (isset($entitySpec->sionModelClass) &&
                !isset($sionModelsToQuery[$entitySpec->sionModelClass]) &&
                $serviceLocator->has($entitySpec->sionModelClass)
            ) {
                $sionModelsToQuery[$entitySpec->sionModelClass] =
                    $serviceLocator->get($entitySpec->sionModelClass);
            }
        }
        $changes = [];
        foreach ($sionModelsToQuery as $sionModelKey => $table) {
            $changes[] = $table->getChanges();
        }
        //@todo fix bug where duplicate array keys between different changes arrays provoke unexpected results
        if (!empty($changes)) {
            //array_replace is like array_merge, but preserves keys
            $allChanges = call_user_func_array('array_replace', $changes);
            krsort($allChanges);
        } else {
            $allChanges = [];
        }
        return $allChanges;
    }
}
