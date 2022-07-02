<?php

declare(strict_types=1);

namespace SionModel\Service;

use Psr\Container\ContainerInterface;
use SionModel\Db\Model\SionTable;

use function call_user_func_array;
use function krsort;

class ChangesCollector
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function getAllChanges()
    {
        $container = $this->container;
        /** @var EntitiesService $entitiesService */
        $entitiesService = $container->get(EntitiesService::class);
        $entiesSpecs     = $entitiesService->getEntities();

        /** @var SionTable[] $sionModelsToQuery */
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
            $changes[] = $table->getChanges();
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
