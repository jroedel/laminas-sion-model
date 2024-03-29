<?php

declare(strict_types=1);

namespace SionModel\Entity;

use SionModel\Filter\MixedCase;
use Webmozart\Assert\Assert;

use function property_exists;

class Entity
{
    /**
     * Name of the entity. camelCase.
     * Example: 'person'
     */
    public string $name;
    /**
     * Name of the table
     * Example: 'sch_persons'
     */
    public string $tableName;
    /**
     * Name of the table column by which to update on
     */
    public string $tableKey;
    /**
     * FQCN of SionControllers that handle this entity
     *
     * @deprecated
     *
     * @var array $sionControllers
     */
    public array $sionControllers = [];
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
     *
     * @var string[] $dependsOnEntities
     */
    public array $dependsOnEntities = [];
    /**
     * A registered alias of a view helper to render the entity
     */
    public ?string $formatViewHelper = null;
    /**
     * List of columns needed in order to insert a new entity
     *
     * @var string[] $requiredColumnsForCreation
     */
    public array $requiredColumnsForCreation = [];
    /**
     * The name of the entity's field to use to display the name
     */
    public ?string $nameField = null;
    /**
     * Should the name be translated upon display?
     */
    public bool $nameFieldIsTranslatable = false;
    /**
     * Used in FormatEntity to display a flag before the name field.
     */
    public ?string $countryField = null;
    /**
     * List of fields that should be stored in the Changes table as a text column instead of varchar
     *
     * @var string[] $textColumns
     */
    public array $textColumns = [];
    /**
     * A list of mappings from ORM field names to database column names
     * Example:
     * ['personId' => 'PersonId', 'email' => 'EmailAddress']
     *
     * @var string[] $updateColumns
     */
    public array $updateColumns = [];
    /**
     * When a field by the name of the key is updated, the algorithm will search for a field by
     * the name of the $value.'UpdatedOn' and $value.'UpdatedBy', to update those as w3ell.
     * Example:
     * [ 'email1' => 'emails', 'email2' => 'emails]
     *
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
     * An associative array mapping route parameters to properties of the entity.
     *
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
     *
     * @var string[] $showRouteParams
     */
    public array $showRouteParams = [];
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
     *
     * @var string[] $editRouteParams
     */
    public array $editRouteParams = [];

    /**
     * String representing either a service, or a class name
     */
    public ?string $createActionForm = null;

    /**
     * Route to which the user will be redirected upon a successful entity creation
     */
    public ?string $createActionRedirectRoute = null;
    /**
     * An associative array mapping route parameters to properties of the entity.
     *
     * @var string[] $createActionRedirectRouteParams
     */
    public array $createActionRedirectRouteParams = [];

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
     *
     * @var string[] $touchRouteParams
     */
    public array $touchRouteParams = [];
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
     * A function to be called upon $data before creating/updating an entity
     * Example:
     * 'sortNationalityArray'
     *
     * @see Entity
     * it must accept two parameters: $data, $entityData
     *
     * @param $data array is the data to be updated
     * @param $entityData array the queried pre-action data, empty array on create
     * @param $entityAction string one of the SionTable::ENTITY_ACTION_ consts
     * @returns array updated data to be inserted/updated in the database
     */
    public ?string $databaseBoundDataPreprocessor = null;
    /**
     * Same as database bound data preprocessor, but will be run after editing the database
     * This can be used to do manipulation to other related entities in the database.
     * The function is passed:
     *
     * @param $data array the data that was updated/inserted
     * @param $entityData array is the newly queried data of the entity
     * @param $entityAction string one of the SionTable::ENTITY_ACTION_ consts
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
     */
    public ?string $moderateRouteEntityKey = null;
    /**
     * String representing either a service, or a class name
     */
    public ?string $suggestForm = null;
    /**
     * Allow the entity to be deleted using the SionModel delete action
     */
    public bool $enableDeleteAction = false;
    /**
     * The route parameter key from which to get the entity's id in the deleteAction
     */
    public ?string $deleteRouteKey = null;
    /**
     * Acl resource identifier to check for permissions to delete a concrete entity
     *
     * @deprecated
     */
    public ?string $deleteActionAclResource = null;
    /**
     * A permission of the resource identifier to check for with isAllowed
     *
     * @deprecated
     */
    public ?string $deleteActionAclPermission = null;
    /**
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
        'show'     => 'aclShowPermission',
        'edit'     => 'aclEditPermission',
        'suggest'  => 'aclSuggestPermission',
        'moderate' => 'aclModeratePermission',
        'delete'   => 'aclDeletePermission',
    ];

    public const ACTION_ROUTE_PROPERTIES = [
        'index'  => 'indexRoute',
        'show'   => 'showRoute',
        'create' => 'createRoute',
        'edit'   => 'editRoute',
//         'suggest' => 'suggestRoute',
        'moderate' => 'moderateRoute',
        'touch'    => 'touchRoute',
//         'delete' => 'aclDeletePermission',
    ];

    public function __construct(string $name, array $entitySpecification)
    {
        $this->name      = $name;
        $camelCaseFilter = new MixedCase('_');

        $propertiesSet = [];
        foreach ($entitySpecification as $key => $value) {
            $key = $camelCaseFilter->filter($key);
            if (property_exists($this, $key)) {
                $propertiesSet[] = $value;
                $this->$key      = $value;
            }
        }

        //make sure we've set all required properties
        Assert::true(isset($this->sionModelClass), "Expected sionModelClass to be set for Entity `$name`, unset.");
        Assert::true(
            isset($this->tableName) && ! empty($this->tableName),
            "Expected tableName to be set for Entity `$name`, unset."
        );
        Assert::true(
            isset($this->tableKey) && ! empty($this->tableKey),
            "Expected tableKey to be set for Entity `$name`, unset."
        );
        Assert::true(
            isset($this->entityKeyField) && ! empty($this->entityKeyField),
            "Expected entityKeyField to be set for Entity `$name`, unset."
        );
        Assert::notEmpty($this->updateColumns);
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
