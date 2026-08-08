<?php

namespace SionModel\Service;

class ChangesCollector
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Every registered table's newest changes, merged newest-first.
     *
     * $maxRows is applied **per table**, not to the merged result, and that is the
     * correct place for it: the caller wants the newest N overall, and which table those
     * come from is not known until they are merged. Asking each table for fewer than N
     * could therefore drop a change that belongs in the answer.
     *
     * It is a real bound rather than decoration. Each change row may pull in the entity
     * row it describes, so an unbounded fetch is an unbounded page — /sm/view-changes
     * exhausted a 512 MB limit before this parameter existed. See
     * SionTable::entityFieldForTableKey() for the other half of that failure.
     *
     * @param int $maxRows per table
     * @return mixed[]
     */
    public function getAllChanges($maxRows = 250)
    {
        $container = $this->container;
        /** @var EntitiesService $entitiesService */
        $entitiesService = $container->get(EntitiesService::class);
        $entiesSpecs = $entitiesService->getEntities();

        /** @var \SionModel\Db\Model\SionTable[] $sionModelsToQuery */
        $sionModelsToQuery = [];
        foreach ($entiesSpecs as $entitySpec) {
            if (
                isset($entitySpec->sionModelClass) &&
                ! isset($sionModelsToQuery[$entitySpec->sionModelClass]) &&
                $container->has($entitySpec->sionModelClass)
            ) {
                $sionModelsToQuery[$entitySpec->sionModelClass] =
                $container->get($entitySpec->sionModelClass);
            }
        }
        $changes = [];
        foreach ($sionModelsToQuery as $table) {
            $changes[] = $table->getChanges($maxRows);
        }
        //@todo fix bug where duplicate array keys between different changes arrays provoke unexpected results
        if (! empty($changes)) {
            //array_replace is like array_merge, but preserves keys
            $allChanges = call_user_func_array('array_replace', $changes);
            krsort($allChanges);
        } else {
            $allChanges = [];
        }
        return $allChanges;
    }
}
