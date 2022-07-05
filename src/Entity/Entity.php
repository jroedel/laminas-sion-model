<?php

namespace SionModel\Entity;

use SionModel\Filter\MixedCase;

class Entity
{
    /**
     * Name of the entity. camelCase.
     * Example: 'person'
     * @var string $name
     */
    public string $name;
    /**
     * Name of the table
     * Example: 'sch_persons'
     * @var string $tableName
     */
    public string $tableName;
    /**
     * Name of the table column by which to update on
     * @var string $tableKey
     */
    public string $tableKey;
    /**
     * FQCN of SionControllers that handle this entity
     * @var array $sionControllers
     * @deprecated
     */
    public $sionControllers = [];
    /**
     * If a SionController needs more services than those provided they can specify these
     * in the 'controller_services' configuration, and they will be injected into this array.
     * @var array $controllerServices
     * @deprecated
     */
    public $controllerServices = [];
    /**
     * Name of identifier field
     * Example: 'personId'
     */
    public string $entityKeyField;
    /**
     * Service locator name of the class (that extends SionModel), which manages this entity
     */
    public string $sionModelClass;
    /**
     * Method names from which to get reference data upon updating an entity
     * It must be in the same SionModel class from which 'updateEntity' is called
     */
    public ?string $getObjectFunction = null;
    /**
     * Function name to get all objects. This function is called from getObjects.
     */
    public ?string $getObjectsFunction = null;
    /**
     * Name of a function that receives the raw database row in an array format
     * and returns an array of the processed data.
     * Is delegated from the processEntityRow function
     */
    public ?string $rowProcessorFunction = null;
    /**
     * A list of entity names this one depends on to complete its data.
     * This is used for cache management. For example, if entity A depends on entity B and a row of entity B
     * has been modified, the cached results of both B and A will be invalidated.
     * If the function returns null for a particular row, that row will not be included in the results
     * Value used in the queryObjects function
     * @var string[] $dependsOnEntities
     */
    public array $dependsOnEntities = [];
    /**
     * A registered alias of a view helper to render the entity
     */
    public ?string $formatViewHelper = null;
    /**
     * List of columns needed in order to insert a new entity
     * @var string[] $requiredColumnsForCreation
     */
    public array $requiredColumnsForCreation = [];
    /**
     * The name of the entity's field to use to display the name
     */
    public ?string $nameField = null;
    /**
     * Should the name be translated upon display?
     * @var bool $nameFieldIsTranslatable
     */
    public bool $nameFieldIsTranslatable = false;
    /**
     * Used in FormatEntity to display a flag before the name field.
     */
    public ?string $countryField = null;
    /**
     * List of fields that should be stored in the Changes table as a text column instead of varchar
     * @var string[] $textColumns
     */
    public array $textColumns = [];
    /**
     * A list of mappings from ORM field names to database column names
     * Example:
     * ['personId' => 'PersonId', 'email' => 'EmailAddress']
     * @var string[] $updateColumns
     */
    public array $updateColumns = [];
    /**
     * When a field by the name of the key is updated, the algorithm will search for a field by
     * the name of the $value.'UpdatedOn' and $value.'UpdatedBy', to update those as w3ell.
     * Example:
     * [ 'email1' => 'emails', 'email2' => 'emails]
     * @var string[] $manyToOneUpdateColumns
     */
    public array $manyToOneUpdateColumns = [];
    /**
     * If true, changes will be registered in the changes table.
     */
    public bool $reportChanges = false;
    /**
     * A route name where an index of the entities can be found.
     * The SionController will redirect here if an invalid entity was attempted to be edited.
     */
    public ?string $indexRoute = null;

    /**
     * A template stack address to render the SionController::indexAction
     */
    public ?string $indexTemplate = null;

    /**
     * The route parameter to pass the entity id, if a more specific one isn't specified
     * @deprecated
     */
    public ?string $defaultRouteKey = null;
    /**
     * An associative array mapping route parameters to properties of the entity.
     * @var string[] $defaultRouteParams
     */
    public array $defaultRouteParams = [];
    /**
     * Template stack address to render on the SionController::showAction
     */
    public ?string $showActionTemplate = null;
    /**
     * The route to show this entity
     * Example: 'persons/person'
     */
    public ?string $showRoute = null;
    /**
     * An associative array mapping route parameters to properties of the entity.
     * @var string[] $showRouteParams
     */
    public array $showRouteParams = [];
    /**
     * The route parameter to pass when generating the URL to the show route
     * Example: 'person_id'
     * @deprecated
     */
    public ?string $showRouteKey = null;
    /**
     * The entity field to pass as the show route parameter
     * Example: 'personId'
     * @deprecated
     */
    public ?string $showRouteKeyField = null;
    /**
     * String representing either a service, or a class name
     */
    public ?string $editActionForm = null;
    /**
     * Template stack address to render on the SionController::editAction
     */
    public ?string $editActionTemplate = null;
    /**
     * The route to edit this entity
     * Example: 'persons/person/edit'
     */
    public ?string $editRoute = null;
    /**
     * An associative array mapping route parameters to properties of the entity.
     * @var string[] $editRouteParams
     */
    public array $editRouteParams = [];
    /**
     * The route parameter to pass when generating the URL to the edit route
     * Example: 'person_id'
     * @deprecated
     */
    public ?string $editRouteKey = null;
    /**
     * The entity field to pass as the edit route parameter
     * Example: 'personId'
     * @deprecated
     */
    public ?string $editRouteKeyField = null;

    /**
     * String representing either a service, or a class name
     */
    public ?string $createActionForm = null;

    /**
     * A function within the SionController child class to handle functionality once
     * the entity has been validated. The function will receive one parameter, the
     * validated data.
     */
    public ?string $createActionValidDataHandler = null;

    /**
     * Route to which the user will be redirected upon a successful entity creation
     */
    public ?string $createActionRedirectRoute = null;
    /**
     * An associative array mapping route parameters to properties of the entity.
     * @var string[] $createActionRedirectRouteParams
     */
    public array $createActionRedirectRouteParams = [];

    /**
     * The route parameter to be paired with the $createActionRedirectRoute.
     * The value will be the new PRIMARYKEY value of the newly created entity.
     * If this value is omitted, the user will be redirected with no route parameter
     * @deprecated
     */
    public ?string $createActionRedirectRouteKey = null;

    /**
     * Entity field to use as route key value upon successfully creating an entity instance
     * @deprecated
     */
    public ?string $createActionRedirectRouteKeyField = null;

    /**
     * A view template in the template stack to render in the SionController->createAction.
     * If it is not specified, the default view template will be used.
     */
    public ?string $createActionTemplate = null;
    /**
     * The field of entity to touch if none is specified.
     * If not specified, the $entityKeyField will be touched
     */
    public ?string $touchDefaultField = null;
    /**
     * An associative array mapping route parameters to properties of the entity.
     * @var string[] $touchRouteParams
     */
    public array $touchRouteParams = [];
    /**
     * A route key that specifies the entity id to touch in the touchAction
     * @deprecated
     */
    public ?string $touchRouteKey = null;
    /**
     * A route key that specifies the entity field to touch for the touchAction
     * and touchJsonAction of SionController.
     * If not specified, the $touchDefaultField will be touched
     * @deprecated
     */
    public ?string $touchFieldRouteKey = null;
    /**
     * The route to edit this entity
     * Example: 'persons/person/touch'
     */
    public ?string $touchJsonRoute = null;
    /**
     * An associative array mapping route parameters to properties of the entity.
     */
    public ?array $touchJsonRouteParams = [];
    /**
     * The route parameter to pass when generating the URL to the edit route
     * Example: 'person_id'
     * @deprecated
     */
    public ?string $touchJsonRouteKey = null;

    /**
     * A function to be called upon $data before creating/updating an entity
     * Example:
     * 'sortNationalityArray'
     *
     * @see Entity
     * it must accept two parameters: $data, $entityData
     * @param $data array is the data to be updated
     * @param $entityData array the queried pre-action data, empty array on create
     * @param $entityAction string one of the SionTable::ENTITY_ACTION_ consts
     * @returns array updated data to be inserted/updated in the database
     *
     * @var string $databaseBoundDataPreprocessor
     */
    public ?string $databaseBoundDataPreprocessor = null;
    /**
     * Same as database bound data preprocessor, but will be run after editing the database
     * This can be used to do manipulation to other related entities in the database.
     * The function is passed:
     * @param $data array the data that was updated/inserted
     * @param $entityData array is the newly queried data of the entity
     * @param $entityAction string one of the SionTable::ENTITY_ACTION_ consts
     * @var string $databaseBoundDataPostprocessor
     */
    public ?string $databaseBoundDataPostprocessor = null;
    /**
     * The route to moderate a suggestion on this entity
     * Example: 'persons/person/moderate'
     */
    public ?string $moderateRoute = null;
    /**
     * The key used when generating the URL to the moderate route
     * Example: 'person_id'
     * @var ?string $moderateRouteEntityKey
     */
    public ?string $moderateRouteEntityKey = null;
    /**
     * String representing either a service, or a class name
     */
    public ?string $suggestForm = null;
    /**
     * Allow the entity to be deleted using the SionModel delete action
     * @var bool $enableDeleteAction
     */
    public bool $enableDeleteAction = false;
    /**
     * The route parameter key from which to get the entity's id in the deleteAction
     * @var ?string $deleteRouteKey
     */
    public ?string $deleteRouteKey = null;
    /**
     * Acl resource identifier to check for permissions to delete a concrete entity
     * @deprecated
     * @var ?string $deleteActionAclResource
     */
    public ?string $deleteActionAclResource = null;
    /**
     * A permission of the resource identifier to check for with isAllowed
     * @deprecated
     * @var ?string $deleteActionAclPermission
     */
    public ?string $deleteActionAclPermission = null;
    /**
     *
     * The route to which the user should be redirected after deleting an entity
     */
    public ?string $deleteActionRedirectRoute = null;

    /**
     * Entity field to find the resource identifier for permission checking
     */
    public ?string $aclResourceIdField = null;
    /**
     * Permission name to check if user is allowed to show the entity using the
     * BjyAuthorize isAllowed controller plugin
     */
    public ?string $aclShowPermission = null;
    /**
     * Permission name to check if user is allowed to edit the entity using the
     * BjyAuthorize isAllowed controller plugin
     */
    public ?string $aclEditPermission = null;
    /**
     * Permission name to check if user is allowed to suggest on the entity using the
     * BjyAuthorize isAllowed controller plugin
     */
    public ?string $aclSuggestPermission = null;
    /**
     * Permission name to check if user is allowed to moderate the entity using the
     * BjyAuthorize isAllowed controller plugin
     */
    public ?string $aclModeratePermission = null;
    /**
     * Permission name to check if user is allowed to delete the entity using the
     * BjyAuthorize isAllowed controller plugin
     */
    public ?string $aclDeletePermission = null;

    public const IS_ACTION_ALLOWED_PERMISSION_PROPERTIES = [
        'show' => 'aclShowPermission',
        'edit' => 'aclEditPermission',
        'suggest' => 'aclSuggestPermission',
        'moderate' => 'aclModeratePermission',
        'delete' => 'aclDeletePermission',
    ];

    public const ACTION_ROUTE_PROPERTIES = [
        'index' => 'indexRoute',
        'show' => 'showRoute',
        'create' => 'createRoute',
        'edit' => 'editRoute',
//         'suggest' => 'suggestRoute',
        'moderate' => 'moderateRoute',
        'touch' => 'touchRoute',
//         'delete' => 'aclDeletePermission',
    ];

    public function __construct(string $name, array $entitySpecification)
    {
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
     */
    public function isEnabledForEntityDelete(): bool
    {
        return $this->enableDeleteAction && isset($this->tableName) && isset($this->tableKey);
    }

    /**
     * Check that the entity has the necessary configuration to update/create using SionTable
     */
    public function isEnabledForUpdateAndCreate(): bool
    {
        return isset($this->tableName) && isset($this->tableKey)
            && isset($this->updateColumns) && ! empty($this->updateColumns);
    }
}
