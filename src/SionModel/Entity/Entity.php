<?php
namespace SionModel\Entity;

use SionModel\Filter\MixedCase;
use SionModel\Db\Model\SionTable;

class Entity
{
    /**
     * Name of the entity. camelCase.
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
     * Service locator name of the class (that extends SionModel), which manages this entity
     * @var string $sionModelClass
     */
    public $sionModelClass;
    /**
     * Method name from which to get reference data upon updating an entity
     * It must be in the same SionModel class from which 'updateEntity' is called
     * @var string $getObjectFunction
     */
    public $getObjectFunction;
    /**
     * Function name to get all objects. This function is called from getObjects.
     * @var string getObjectsFunction
     */
    public $getObjectsFunction;
    /**
     * List of columns needed in order to insert a new entity
     * @var string[] $requiredColumnsForCreation
     */
    public $requiredColumnsForCreation = [];
    /**
     * The name of the entity's field to use to display the name
     * @var string $nameColumn
     */
    public $nameField;
    /**
     * Should the name be translated upon display?
     * @var bool $nameFieldIsTranslateable
     */
    public $nameFieldIsTranslateable = false;
    /**
     * Used in FormatEntity to display a flag before the name field.
     * @var string $countryField
     */
    public $countryField;
    /**
     * List of fields that should be stored in the Changes table as a text column instead of varchar
     * @var string[]
     */
    public $textColumns = [];
    /**
     * List of fields that should be converted from DateTime objects before insert
     * @var string[]
     */
    public $dateColumns = [];
    /**
     * A list of mappings from ORM field names to database column names
     * Example:
     * ['personId' => 'PersonId', 'email' => 'EmailAddress']
     * @var string[] $updateColumns
     */
    public $updateColumns = [];
    /**
     * When a field by the name of the key is updated, the algorithm will search for a field by
     * the name of the $value.'UpdatedOn' and $value.'UpdatedBy', to update those as w3ell.
     * Example:
     * [ 'email1' => 'emails', 'email2' => 'emails]
     * @var string[]
     */
    public $manyToOneUpdateColumns = [];
    /**
     * If true, changes will be registered in the changes table.
     * @var bool
     */
    public $reportChanges = false;
    /**
     * A route name where an index of the entities can be found.
     * The SionController will redirect here if an invalid entity was attempted to be edited.
     * @var string $indexRoute
     */
    public $indexRoute;

    /**
     * A template stack address to render the SionController::indexAction
     * @var string $indexTemplate
     */
    public $indexTemplate;
    /**
     * Template stack address to render on the SionController::showAction
     * @var string $showActionTemplate
     */
    public $showActionTemplate;
    /**
     * The route to show this entity
     * Example: 'persons/person'
     * @var string $showRoute
     */
    public $showRoute;
    /**
     * The route parameter to pass when generating the URL to the show route
     * Example: 'person_id'
     * @var string $showRouteKey
     */
    public $showRouteKey;
    /**
     * The entity field to pass as the show route parameter
     * Example: 'personId'
     * @var string $showRouteKeyField
     */
    public $showRouteKeyField;
    /**
     * String representing either a service, or a class name
     * @var string $editActionForm
     */
    public $editActionForm;
    /**
     * Template stack address to render on the SionController::editAction
     * @var string $editActionTemplate
     */
    public $editActionTemplate;
    /**
     * The route to edit this entity
     * Example: 'persons/person/edit'
     * @var string $editRoute
     */
    public $editRoute;
    /**
     * The route parameter to pass when generating the URL to the edit route
     * Example: 'person_id'
     * @var string $editRouteKey
     */
    public $editRouteKey;
    /**
     * The entity field to pass as the edit route parameter
     * Example: 'personId'
     * @var string $editRouteKeyField
     */
    public $editRouteKeyField;

    /**
     * String representing either a service, or a class name
     * @var string $createActionForm
     */
    public $createActionForm;

    /**
     * Route to which the user will be redirected upon a successful entity creation
     * @var string $createActionRedirectRoute
     */
    public $createActionRedirectRoute;

    /**
     * The route parameter to be paired with the $createActionRedirectRoute.
     * The value will be the new PRIMARYKEY value of the newly created entity.
     * If this value is omitted, the user will be redirected with no route parameter
     * @var string $createActionRedirectRouteKey
     */
    public $createActionRedirectRouteKey;

    /**
     * Entity field to use as route key value upon successfully creating an entity instance
     * @var string $createActionRedirectRouteKeyField
     */
    public $createActionRedirectRouteKeyField;

    /**
     * A view template in the template stack to render in the SionController->createAction.
     * If it is not specified, the default view template will be used.
     * @var unknown
     */
    public $createActionTemplate;

    /**
     * A function to be called upon $data before creating/updating an entity
     * Example:
     * 'sortNationalityArray'
     *
     * @see Entity
     * it must accepts two parameters: $data, $entityData
     * @param $data mixed[] is the data to be updated
     * @param $entityData mixed[] the queried pre-action data, empty array on create
     * @param $entityAction one of the SionTable::ENTITY_ACTION_ consts
     * @returns mixed[] updated data to be inserted/updated in the database
     *
     * @var string $databaseBoundDataPreprocessor
     */
    public $databaseBoundDataPreprocessor;
    /**
     * Same as database bound data preprocessor, but will be run after editing the database
     * This can be used to do manipulation to other related entities in the database.
     * The function is passed:
     * @param $data the data that was updated/inserted
     * @param $entityData is the newly queried data of the entity
     * @param $entityAction one of the SionTable::ENTITY_ACTION_ consts
     * @var string $databaseBoundDataPostprocessor
     */
    public $databaseBoundDataPostprocessor;
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
     * @deprecated will be replaced by suggestForm
     */
    public $hasDedicatedSuggestForm = false;
    /**
     * String representing either a service, or a class name
     * @var string $suggestForm
     */
    public $suggestForm;
    /**
     * Allow the entity to be deleted using the SionModel delete action
     * @var bool
     */
    public $enableDeleteAction = false;
    /**
     * Acl resource identifier to check for permissions to delete a concrete entity
     * @var string
     */
    public $deleteActionAclResource;
    /**
     * A permission of the resource identifier to check for with isAllowed
     * @var string
     */
    public $deleteActionAclPermission;
    /**
     *
     * The route to which the user should be redirected after deleting an entity
     * @var string
     */
    public $deleteActionRedirectRoute;

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

    /**
     * Check that entity has specified all required information for deleting
     * an instance using the SionController::deleteEntityAction
     * @return boolean
     */
    public function isEnabledForEntityDelete()
    {
        return (bool)$this->enableDeleteAction &&
            $this->tableName && 0 !== strlen($this->tableName) &&
            $this->tableKey && 0 !== strlen($this->tableKey);
    }

    /**
     * Check that the entity has the necessary configuration to update/create using SionTable
     * @return boolean
     */
    public function isEnabledForUpdateAndCreate()
    {
        return !is_null($this->tableName) && !is_null($this->tableKey) &&
            !is_null($this->getObjectFunction) &&
            !is_null($this->updateColumns) && !empty($this->updateColumns);
    }
}
