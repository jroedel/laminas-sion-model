<?php
namespace SionModel\Service;

class ChangesCollector
{
    protected $container;
    
    public function __construct($container)
    {
        $this->container = $container;
    }
    
    public function getAllChanges()
    {
        $container = $this->container;
        /** @var EntitiesService $entitiesService */
        $entitiesService = $container->get(EntitiesService::class);
        $entiesSpecs = $entitiesService->getEntities();
        
        /** @var \SionModel\Db\Model\SionTable[] $sionModelsToQuery */
        $sionModelsToQuery = [];
        foreach ($entiesSpecs as $entity => $entitySpec) {
            if (isset($entitySpec->sionModelClass) &&
                !isset($sionModelsToQuery[$entitySpec->sionModelClass]) &&
                $container->has($entitySpec->sionModelClass)
            ) {
                $sionModelsToQuery[$entitySpec->sionModelClass] =
                $container->get($entitySpec->sionModelClass);
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