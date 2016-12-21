<?php
namespace SionModel\Entity;

use Zend\Filter\Word\UnderscoreToCamelCase;
use SionModel\Filter\MixedCase;
class Entity
{
    /**
     * Name of the entity. Camelcase.
     * @var string
     */
    public $name;
    public $tableName;
    public $tableKey;
    public $entityKeyField;
    public $scope;
    public $sionModelClass;
    public $updateReferenceDataFunction;
    public $requiredColumnsForCreation;
    public $nameColumn;
    public $textColumns;
    public $dateColumns;
    public $updateColumns;
    public $showRoute;
    public $showRouteKey;
    public $showRouteKeyField;
    public $moderateRoute;
    public $moderateRouteEntityKey;
    public $hasDedicatedSuggestForm;

    public function __construct($name, $entitySpecification)
    {
        if (is_null($name)) {
            throw new \InvalidArgumentException('Name is a required parameter.');
        }
        if (!is_array($entitySpecification)) {
            throw new \InvalidArgumentException('Entity specification must be an array.');
        }
        $this->name = $name;
        $camelCaseFilter = new MixedCase('_');
        foreach ($entitySpecification as $key => $value) {
            $key = $camelCaseFilter->filter($key);
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}