<?php
namespace SionModel\Entity;

use SionModel\Filter\MixedCase;

class Entity
{
    /**
     * Name of the entity. Camelcase.
     * Example: 'person'
     * @var string $name
     */
    public $name;
    /**
     * Name of the table
     * Example: 'sch_persons'
     * @var string $tableName
     */
    public $tableName;
    /**
     * Name of the table column by which to update on
     * @var string $tableKey
     */
    public $tableKey;
    /**
     * Name of identifier field
     * Example: 'personId'
     * @var string $entityKeyField
     */
    public $entityKeyField;
    /**
     * @deprecated Used for creating associated roles for the Patres database
     * @var string $scope
     */
    public $scope;
    /**
     * Service locator name of the class (that extends SionModel), which manages this entity
     * @var string $sionModelClass
     */
    public $sionModelClass;
    /**
     * Method name from which to get reference data upon updating an entity
     * It must be in the same SionModel class from which 'updateEntity' is called
     * @var string $updateReferenceDataFunction
     */
    public $updateReferenceDataFunction;
    /**
     * List of columns needed in order to insert a new entity
     * @var string[] $requiredColumnsForCreation
     */
    public $requiredColumnsForCreation;
    /**
     * A function to be called upon $data before creating/updating an entity
     * Example:
     * 'sortNationalityArray'
     * @var string $databaseBoundDataPreprocessor
     */
    public $databaseBoundDataPreprocessor;
    /**
     * The name of the entity's field to use to display the name
     * @var string $nameColumn
     */
    public $nameField;
    /**
     * List of fields that should be stored in the Changes table as a text column instead of varchar
     * @var string[]
     */
    public $textColumns;
    /**
     * List of fields that should be converted from DateTime objects before insert
     * @var string[]
     */
    public $dateColumns;
    /**
     * A list of mappings from ORM field names to database column names
     * Example:
     * ['personId' => 'PersonId', 'email' => 'EmailAddress']
     * @var string[] $updateColumns
     */
    public $updateColumns;
    /**
     * When a field by the name of the key is updated, the algorithm will search for a field by
     * the name of the $value.'UpdatedOn' and $value.'UpdatedBy', to update those as well.
     * Example:
     * [ 'email1' => 'emails', 'email2' => 'emails]
     * @var string[]
     */
    public $manyToOneUpdateColumns;
    /**
     * The route to show this entity
     * Example: 'persons/person'
     * @var string $showRoute
     */
    public $showRoute;
    /**
     * The route parameter to pass when generating the URL to the show route
     * Example: 'person_id'
     * @var string
     */
    public $showRouteKey;
    /**
     * The entity field to pass as the route parameter
     * @var unknown
     */
    public $showRouteKeyField;
    /**
     * The route to moderate a suggestion on this entity
     * Example: 'persons/person/moderate'
     * @var string $moderateRoute
     */
    public $moderateRoute;
    /**
     * The key used when generating the URL to the moderate route
     * Example: 'person_id'
     * @var string $moderateRouteEntityKey
     */
    public $moderateRouteEntityKey;
    /**
     * True if there is a form dedicated to suggestions for this entity
     * @var bool $hasDedicatedSuggestForm
     */
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