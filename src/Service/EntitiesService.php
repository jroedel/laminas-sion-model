<?php

namespace SionModel\Service;

use SionModel\Entity\Entity;

class EntitiesService
{
    /**
     * Keyed array of Entity's
     * @var Entity[] $entities
     */
    protected array $entities = [];

    protected array $entityControllers = [];

    public function __construct(array $entitySpecifications)
    {
        foreach ($entitySpecifications as $entity => $spec) {
            $this->entities[$entity] = new Entity($entity, $spec);
            if (! empty($this->entities[$entity]->sionControllers)) {
                foreach ($this->entities[$entity]->sionControllers as $sionController) {
                    if (isset($this->entityControllers[$entity])) {
                        throw new \Exception('Duplication assignment of SionController \'' . $sionController . '\' under the \'' . $entity . '\' entity.');
                    }
                    $this->entityControllers[$sionController] = $entity;
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getEntityControllers(): array
    {
        return $this->entityControllers;
    }

    /**
     * @return Entity[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
