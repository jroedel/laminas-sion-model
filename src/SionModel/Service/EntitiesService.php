<?php
namespace SionModel\Service;

use SionModel\Entity\Entity;

class EntitiesService
{
    /**
     * Keyed array of Entity's
     * @var Entity[] $entities
     */
    protected $entities = [];

    public function __construct(array $entitySpecifications)
    {
        foreach ($entitySpecifications as $entity => $spec) {
            $this->entities[$entity] = new Entity($entity, $spec);
        }
    }

    public function getEntities()
    {
        //for debugging
        return ['person' => new Entity('person', [])];
        return $this->entities;
    }
}