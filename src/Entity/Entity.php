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
     * @var string $getObjectsFunction
     */
    public $getObjectsFunction;
    /**
     * A registered alias of a view helper to render the entity
     * @var string $formatViewHelper
     */
    public $formatViewHelper;
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
     * @var string[] $textColumns
     */
    public $textColumns = [];
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
     * @var string[] $manyToOneUpdateColumns
     */
    public $manyToOneUpdateColumns = [];
    /**
     * If true, changes will be registered in the changes table.
     * @var bool $reportChanges
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
     * The route parameter to pass the entity id, if a more specific one isn't specified
     * @var string $defaultRouteKey
     */
    public $defaultRouteKey;
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
     * A function within the SionController child class to handle functionality once
     * the entity has been validated. The function will receive one parameter, the
     * validated data.
     * @var string $createActionCreateHandler
     */
    public $createActionValidDataHandler;

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
     * @var string $createActionTemplate
     */
    public $createActionTemplate;
    /**
     * The field of entity to touch if none is specified.
     * If not specified, the $entityKeyField will be touched
     * @var string $touchDefaultField
     */
    public $touchDefaultField;
    /**
     * A route key that specifies the entity id to touch in the touchAction
     * @var string $touchRouteKey
     */
    public $touchRouteKey;
    /**
     * A route key that specifies the entity field to touch for the touchAction
     * and touchJsonAction of SionController.
     * If not specified, the $touchDefaultField will be touched
     * @var string $touchFieldRouteKey
     */
    public $touchFieldRouteKey;
    /**
     * The route to edit this entity
     * Example: 'persons/person/touch'
     * @var string $editRoute
     */
    public $touchJsonRoute;
    /**
     * The route parameter to pass when generating the URL to the edit route
     * Example: 'person_id'
     * @var string $editRouteKey
     */
    public $touchJsonRouteKey;

    /**
     * A function to be called upon $data before creating/updating an entity
     * Example:
     * 'sortNationalityArray'
     *
     * @see Entity
     * it must accepts two parameters: $data, $entityData
     * @param $data mixed[] is the data to be updated
     * @param $entityData mixed[] the queried pre-action data, empty array on create
     * @param $entityAction string one of the SionTable::ENTITY_ACTION_ consts
     * @returns mixed[] updated data to be inserted/updated in the database
     *
     * @var string $databaseBoundDataPreprocessor
     */
    public $databaseBoundDataPreprocessor;
    /**
     * Same as database bound data preprocessor, but will be run after editing the database
     * This can be used to do manipulation to other related entities in the database.
     * The function is passed:
     * @param $data array the data that was updated/inserted
     * @param $entityData array is the newly queried data of the entity
     * @param $entityAction string one of the SionTable::ENTITY_ACTION_ consts
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
     * String representing either a service, or a class name
     * @var string $suggestForm
     */
    public $suggestForm;
    /**
     * Allow the entity to be deleted using the SionModel delete action
     * @var bool $enableDeleteAction
     */
    public $enableDeleteAction = false;
    /**
     * The route parameter key from which to get the entity's id in the deleteAction
     * @var string $deleteRouteKey
     */
    public $deleteRouteKey;
    /**
     * Acl resource identifier to check for permissions to delete a concrete entity
     * @deprecated
     * @var string $deleteActionAclResource
     */
    public $deleteActionAclResource;
    /**
     * A permission of the resource identifier to check for with isAllowed
     * @deprecated
     * @var string $deleteActionAclPermission
     */
    public $deleteActionAclPermission;
    /**
     *
     * The route to which the user should be redirected after deleting an entity
     * @var string $deleteActionRedirectRoute
     */
    public $deleteActionRedirectRoute;

    /**
     * Entity field to find the resource identifier for permission checking
     * @var string $resourceIdField
     */
    public $aclResourceIdField;
    /**
     * Permission name to check if user is allowed to show the entity using the
     * BjyAuthorize isAllowed controller plugin
     * @var string $aclShowPermission
     */
    public $aclShowPermission;
    /**
     * Permission name to check if user is allowed to edit the entity using the
     * BjyAuthorize isAllowed controller plugin
     * @var string $aclEditPermission
     */
    public $aclEditPermission;
    /**
     * Permission name to check if user is allowed to suggest on the entity using the
     * BjyAuthorize isAllowed controller plugin
     * @var string $aclSuggestPermission
     */
    public $aclSuggestPermission;
    /**
     * Permission name to check if user is allowed to moderate the entity using the
     * BjyAuthorize isAllowed controller plugin
     * @var string $aclModeratePermission
     */
    public $aclModeratePermission;
    /**
     * Permission name to check if user is allowed to delete the entity using the
     * BjyAuthorize isAllowed controller plugin
     * @var string $aclDeletePermission
     */
    public $aclDeletePermission;

    public static $isActionAllowedPermissionProperties = [
        'show' => 'aclShowPermission',
        'edit' => 'aclEditPermission',
        'suggest' => 'aclSuggestPermission',
        'moderate' => 'aclModeratePermission',
        'delete' => 'aclDeletePermission',
    ];

    public static $actionRouteProperties = [
        'index' => 'indexRoute',
        'show' => 'showRoute',
        'create' => 'createRoute',
        'edit' => 'editRoute',
//         'suggest' => 'suggestRoute',
        'moderate' => 'moderateRoute',
        'touch' => 'touchRoute',
//         'delete' => 'aclDeletePermission',
    ];

    public function __construct($name, $entitySpecification)
    {
        if (!isset($name)) {
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
