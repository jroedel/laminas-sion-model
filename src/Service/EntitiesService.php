<?php

declare(strict_types=1);

namespace SionModel\Service;

use SionModel\Entity\Entity;
use Webmozart\Assert\Assert;

class EntitiesService
{
    /**
     * Keyed array of Entity's
     *
     * @var Entity[] $entities
     */
    protected array $entities = [];

    public function __construct(array $entitySpecifications)
    {
        foreach ($entitySpecifications as $entity => $spec) {
            $this->entities[$entity] = new Entity($entity, $spec);
        }
    }

    /**
     * @return Entity[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getEntity(string $entity): Entity
    {
        Assert::stringNotEmpty($entity);
        Assert::keyExists($this->entities, $entity);
        return $this->entities[$entity];
    }
}
