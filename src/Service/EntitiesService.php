<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use SionModel\Entity\Entity;

class EntitiesService
{
    /**
     * Keyed array of Entity's
     *
     * @var Entity[] $entities
     */
    protected array $entities = [];

    protected array $entityControllers = [];

    public function __construct(array $entitySpecifications)
    {
        foreach ($entitySpecifications as $entity => $spec) {
            $this->entities[$entity] = new Entity($entity, $spec);
        }
    }

    public function getEntityControllers(): array
    {
        return $this->entityControllers;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }
}
