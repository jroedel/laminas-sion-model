<?php
namespace SionModel\Db\Model;

use Zend\Db\Adapter\Adapter;

use BjyAuthorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use Zend\Db\Sql\Insert;
use Zend\Db\TableGateway\TableGateway;
use Zend\Filter\Boolean;
use Zend\Filter\StringTrim;
use Zend\Filter\FilterChain;
use Zend\Filter\ToNull;
use Zend\Validator\EmailAddress;
use SionModel\Entity\Entity;
use Zend\Db\TableGateway\TableGatewayInterface;
use Zend\Uri\Http;
use Zend\Db\Adapter\AdapterInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\ProblemService;
use SionModel\Problem\ProblemTable;
use Zend\Db\Sql\Where;
use JUser\Model\UserTable;
use Zend\Stdlib\StringUtils;

/*
 * I have an interesting idea of being able to specify in a configuration file
 * or maybe even directly in the Entity class the specifications for each SionTable
 * including column access information (for example the "father's name" column
 * wouldn't be accessable to everyone, just superiors or something like that.
 *
 * This is a huge topic: how do I manage resource based permissions? The specifications
 * should probably be stored in a table, but that means either using GUIDs in all the
 * tables or else having to specify the resource type with a string in the ACL table.
 * How might this access permission table look like? maybe...
 * resourceType, resourceId, fields, role, readAccess(Allow, Deny, Neutral),
 * writeAccess(Allow, Deny, Neutral)
 *
 * Something like that could work. I have to check out how that would work. Then I could
 * combine all that code along with the table specs in a SionDB Module, maybe even
 * including the User login stuff there. That would have to have its own bitbucket.
 *
 * Ok, I just read the intro to Zend\Permissions\Acl.  I think the solution is pretty
 * simple.
 * Table: role, resource, permission, allow/deny
 * ex: "user_77", "event_98", "read,update,delete", "allow"
 * ex: "group_editors", "events", "read,update,delete", "allow"
 * ex: "group_guests", "events", "read,update,delete", "deny"
 * Table: role, parent   This table let's us add parent roles to other roles
 * ex: "user_6", "group_kentenich_reader"
 * ex: "administrator", "moderator"
 * ex: "user_8", "administrator"
 */
/**
 *
 * @author jeffr
 * @todo Finish refactoring of reportChange
 * @todo Integrate data problem management
 * @todo Factor out 'changes_table_name' from __contruct method. Make it a required key for entities
 */
class SionTable // implements ResourceProviderInterface
{
    const SUGGESTION_ERROR = 'Error';
    const SUGGESTION_INREVIEW = 'In review';
    const SUGGESTION_ACCEPTED = 'Accepted';
    const SUGGESTION_DENIED = 'Denied';

    protected $tableGateway;

    /**
     *
     * @var Adapter
     */
    protected $adapter;

    protected $select;

    protected $sql;

    /**
     * @var Entity[] $entities
     */
    protected $entities;

    /**
     *
     * @var string $changesTableName
     */
    protected $changesTableName;

    /**
     *
     * @var string $visitsTableName
     */
    protected $visitsTableName;

    /**
     *
     * @var TableGatewayInterface $changesTableGateway
     */
    protected $changesTableGateway;

    /**
     *
     * @var TableGatewayInterface $visitTableGateway
     */
    protected $visitsTableGateway;
    /**
     *
     * @var int
     */
    protected $actingUserId;

    protected $entityProblemPrototype;

    /**
     *
     * @var UserTable $userTable
     */
    protected $userTable;

    /**
     * If $changesTableName is left null, no changes will be made.
     * @param TableGatewayInterface $tableGateway
     * @param Entity[] $entities
     * @param int $actingUserId
     * @param null|string $changesTableName
     */
    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId)
    {
        /**
         * @var EntitiesService $entities
         */
        $entities = $serviceLocator->get('SionModel\Service\EntitiesService');

        $config = $serviceLocator->get('SionModel\Config');
        $this->tableGateway = new TableGateway('', $dbAdapter);
        $this->adapter      = $dbAdapter;
        $this->entities		= $entities->getEntities();
        $this->actingUserId = $actingUserId;
        $this->changeTableName = isset($config['changes_table']) ? $config['changes_table'] : null;
        $this->visitsTableName = isset($config['visits_table']) ? $config['visits_table'] : null;

        //if we have it, use it
        if ($serviceLocator->has('JUser\Model\UserTable')) {
            $userTable = $serviceLocator->get('JUser\Model\UserTable');
            $this->setUserTable($userTable);
        }

        if (!$this instanceof ProblemTable && // prevent circular dependency
            isset($config['problem_specifications']) &&
            !empty($config['problem_specifications'])
        ) {
            /**
             * @var ProblemService $problemService
             */
            $problemService = $serviceLocator->get('SionModel\Service\ProblemService');
            $this->entityProblemPrototype = $problemService->getEntityProblemPrototype();
        }
    }

    /**
     * @param Where|\Closure|string|array $where
     * @param null|string
     * @param null|array
     * @return array
     */
    public function fetchSome($where, $sql = null, $sqlArgs = null)
    {
        if (is_null($where) && is_null($sql)) {
            throw new \InvalidArgumentException('No query requested.');
        }
        if (!is_null($sql))
        {
            if (is_null($sqlArgs)) {
                $sqlArgs = Adapter::QUERY_MODE_EXECUTE; //make sure query executes
            }
            $result = $this->tableGateway->getAdapter()->query($sql, $sqlArgs);
        } else {
            $result = $this->tableGateway->select($where);
        }

        $return = [];
        foreach ($result as $row) {
            $return[] = $row;
        }
        return $return;
    }

    /**
     * Validate urls and configure their labels
     * @param string[] $unprocessedUrls Should be a 2-dimensional array, each element containing a 'url' and 'label' key
     * @throws \InvalidArgumentException
     * @return string[]
     *
     * @todo check URL against Google Safe Browsing
     */
    public static function processUrls($unprocessedUrls)
    {
        if (is_null($unprocessedUrls)) {
            return null;
        }
        if (!is_array($unprocessedUrls)) {
            throw new \InvalidArgumentException('unprocessedUrls must be a 2-dimensional array, each element containing keys \'url\' and \'label\'');
        }
        $urls = [];
        foreach ($unprocessedUrls as $urlRow) {
            if (!key_exists('url', $urlRow) || !key_exists('label', $urlRow)) {
                throw new \InvalidArgumentException('Each element of unprocessedUrls must contain keys \'url\' and \'label\'');
            }
            if (!is_null($urlRow['url'])) {
                $url = new Http($urlRow['url']);
                if ($url->isValid()) {
                    if (is_null($urlRow['label']) || 0 === strlen($urlRow['label'])) {
                        $urlRow['label'] = $url->getHost();
                    }
                    $urlRow['url'] = $url->toString();
                    $urls[] = $urlRow;
                }
            }
        }
        return $urls;
    }

    public function isReadyToUpdateAndCreate($entity)
    {
        if (!isset($this->entities[$entity]->tableName) ||
            is_null($this->entities[$entity]->tableName)
        ) {
            throw new \Exception('Table name configuration is required for entity \''.$entity.'\'');
        }
        if (!isset($this->entities[$entity]->tableKey) ||
            is_null($this->entities[$entity]->tableKey)
        ) {
            throw new \Exception('Table key configuration is required for entity \''.$entity.'\'');
        }
        if (!isset($this->entities[$entity]->updateReferenceDataFunction) ||
            is_null($this->entities[$entity]->updateReferenceDataFunction)
        ) {
            throw new \Exception('\'updateReferenceDataFunction\' configuration is required for entity \''.$entity.'\'');
        }
        if (!method_exists($this, $this->entities[$entity]->updateReferenceDataFunction)
        ) {
            throw new \Exception('\'updateReferenceDataFunction\' configuration for entity \''.$entity.'\' refers to a function that doesn\'t exist');
        }
        if (!isset($this->entities[$entity]->updateColumns) ||
            is_null($this->entities[$entity]->updateColumns)
        ) {
            throw new \Exception('\'updateColumns\' configuration is required for entity \''.$entity.'\'');
        }
    	if (isset($this->entities[$entity]->databaseBoundDataPreprocessor) &&
    	    !is_null($this->entities[$entity]->databaseBoundDataPreprocessor) &&
    	    !method_exists($this, $this->entities[$entity]->databaseBoundDataPreprocessor)
		) {
            throw new \Exception('\'databaseBoundDataPreprocessor\' configuration for entity \''.$entity.'\' refers to a function that doesn\'t exist');
		}
        return true;
    }

    /**
     *
     * @param string $entity
     * @param int $id
     * @param array $data
     * @throws \InvalidArgumentException
     * @return boolean|unknown
     */
    public function updateEntity($entity, $id, $data)
    {
        if (!$this->isReadyToUpdateAndCreate($entity)) {
            throw new \InvalidArgumentException('Improper configuration for entity \''.$entity.'\'');
        }

    	$tableName     = $this->entities[$entity]->tableName;
    	$tableKey      = $this->entities[$entity]->tableKey;
    	$tableGateway  = new TableGateway($tableName, $this->adapter);

    	if (!is_numeric($id)) {
    		throw new \InvalidArgumentException('Invalid id provided.');
    	}
    	$entityFunction = $this->entities[$entity]->updateReferenceDataFunction;
    	$entityData = $this->$entityFunction($id);
    	if (!$entityData) {
    		throw new \InvalidArgumentException('No entity provided.');
    	}
    	$updateCols = $this->entities[$entity]->updateColumns;
    	$manyToOneUpdateColumns = isset($this->entities[$entity]->manyToOneUpdateColumns) ?
    	   $this->entities[$entity]->manyToOneUpdateColumns : null;
    	$reportChanges = isset($this->entities[$entity]->reportChanges) ?
    	   $this->entities[$entity]->reportChanges : false;
    	
    	/*
    	 * Run the new/old data throught the preprocessor function if it exists
    	 */
    	if (isset($this->entities[$entity]->databaseBoundDataPreprocessor) &&
    	    !is_null($this->entities[$entity]->databaseBoundDataPreprocessor)
		) {
		    $preprocessor = $this->entities[$entity]->databaseBoundDataPreprocessor;
			$data = $this->$preprocessor($data, $entityData);
		}
		return $this->updateHelper($id, $data, $entity, $tableKey, $tableGateway, $updateCols, $entityData, $manyToOneUpdateColumns, $reportChanges);
    }

    /**
     *
     * @param int $id
     * @param mixed[] $data
     * @param string $entityType
     * @param string $tableKey
     * @param TableGatewayInterface $tableGateway
     * @param string[] $updateCols
     * @param mixed[] $referenceEntity
     * @param string[] $manyToOneUpdateColumns
     * @param bool $reportChanges
     * @throws \Exception
     */
    protected function updateHelper($id, $data, $entityType, $tableKey, TableGatewayInterface $tableGateway, $updateCols, $referenceEntity, $manyToOneUpdateColumns = null, $reportChanges = false)
    {
        if (is_null($entityType) || $entityType == '') {
            throw new \Exception('No entity provided.');
        }
        if (is_null($tableKey) || $tableKey == '') {
            throw new \Exception('No table key provided');
        }
        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $updateVals = [];
        $changes = [];
        foreach ($referenceEntity as $col => $value) {
            if (!key_exists($col, $updateCols) || !key_exists($col, $data) || $value == $data[$col]) {
                continue;
            }
            if ($data[$col] instanceof \DateTime) { //convert Date objects to strings
                $data[$col] = $data[$col]->format('Y-m-d H:i:s');
            }
            if (is_array($data[$col])) { //convert arrays to strings
                if (empty($data[$col])) {
                    $data[$col] = null;
                } else {
                    $data[$col] = implode('|', $data[$col]); //pipe (|) is a good unused character for separating
                }
            }
            $updateVals[$updateCols[$col]] = $data[$col];
            if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) { //check if this column has updatedOn column
                $updateVals[$updateCols[$col.'UpdatedOn']] = $now;
            }
            if (key_exists($col.'UpdatedBy', $updateCols) && !key_exists($col.'UpdatedBy', $data) &&
                !is_null($this->actingUserId))
            { //check if this column has updatedBy column
                $updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUserId;
            }
            if (is_array($manyToOneUpdateColumns) && isset($manyToOneUpdateColumns[$col])) {
                if ( key_exists($manyToOneUpdateColumns[$col].'UpdatedOn', $updateCols) &&
                    !key_exists($manyToOneUpdateColumns[$col].'UpdatedOn', $data))
                { //check if this column maps to some other updatedOn column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$col].'UpdatedOn']] = $now;
                }
                if ( key_exists($manyToOneUpdateColumns[$col].'UpdatedBy', $updateCols) &&
                    !key_exists($manyToOneUpdateColumns[$col].'UpdatedBy', $data) &&
                    !is_null($this->actingUserId))
                { //check if this column  maps to some other updatedBy column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$col].'UpdatedBy']] = $this->actingUserId;
                }
            }
            $changes[] = [
                'entity'   => $entityType,
                'field'    => $col,
                'id'       => $id,
                'oldValue' => $value,
                'newValue' => $data[$col],
            ];
        }
        if (count($updateVals) > 0) {
            if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
                $updateVals[$updateCols['updatedOn']] = $now;
            }
            if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
                !is_null($this->actingUserId)) {
                    $updateVals[$updateCols['updatedBy']] = $this->actingUserId;
                }
                $result = $tableGateway->update($updateVals, [$tableKey => $id]);
                if ($reportChanges) {
                    $this->reportChange($changes);
                }
                return $result;
        }
        return true;
    }

    public function createEntity($entity, $data)
    {
        if (!$this->isReadyToUpdateAndCreate($entity)) {
            throw new \InvalidArgumentException('Improper configuration for entity \''.$entity.'\'');
        }

    	$tableName                 = $this->entities[$entity]->tableName;
    	$tableGateway              = new TableGateway($tableName, $this->adapter);
    	$scope                     = $this->entities[$entity]->scope;
    	$requiredCols              = $this->entities[$entity]->requiredColumnsForCreation;
    	$updateCols                = $this->entities[$entity]->updateColumns;
    	$manyToOneUpdateColumns    = $this->entities[$entity]->manyToOneUpdateColumns;
    	$reportChanges             = $this->entities[$entity]->reportChanges;
    	//preprocess the data
    	if (!is_null($preprocessor = $this->entities[$entity]->databaseBoundDataPreprocessor)) {
			$data = $this->$preprocessor($data, []);
		}
		return $this->createHelper($data, $requiredCols, $updateCols, $entity, $tableGateway, $scope, $manyToOneUpdateColumns, $reportChanges);
    }

    /**
     * @todo Factor out scope
     * @param mixed[] $data
     * @param string[] $requiredCols
     * @param string[] $updateCols
     * @param string $entityType
     * @param TableGatewayInterface $tableGateway
     * @param string|null $scope
     * @param string[]|null $manyToOneUpdateColumns
     */
    protected function createHelper($data, $requiredCols, $updateCols, $entityType, $tableGateway, $scope = null, $manyToOneUpdateColumns = null, $reportChanges = false)
    {
    	//make sure required cols are being passed
    	foreach ($requiredCols as $colName) {
    		if (!isset($data[$colName])) {
    			return false;
    		}
    	}

    	$now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    	$updateVals = [];
    	foreach ($data as $col => $value) {
    		if (!key_exists($col, $updateCols)) {
    			continue;
    		}
    		if ($data[$col] instanceof \DateTime) {
    			$data[$col] = $data[$col]->format('Y-m-d H:i:s');
    		}
    		if (is_array($data[$col])) {
    			$data[$col] = $this->formatDbArray($data[$col]);
    		}
    		$updateVals[$updateCols[$col]] = $data[$col];
    		if (!is_null($value) && key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) { //check if this column has updatedOn column
    			$updateVals[$updateCols[$col.'UpdatedOn']] = $now;
    		}
    		if (!is_null($value) && key_exists($col.'UpdatedBy', $updateCols) && !key_exists($col.'UpdatedBy', $data) &&
    				!is_null($this->actingUserId)) { //check if this column has updatedOn column
    					$updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUserId;
    		}
    		if (!is_null($value) && is_array($manyToOneUpdateColumns) && isset($manyToOneUpdateColumns[$col])) {
    			if (key_exists($col, $manyToOneUpdateColumns) &&
    					key_exists($manyToOneUpdateColumns[$col].'UpdatedOn', $updateCols) &&
    					!key_exists($manyToOneUpdateColumns[$col].'UpdatedOn', $data))
    			{ //check if this column maps to some other updatedOn column
    				$updateVals[$updateCols[$manyToOneUpdateColumns[$col].'UpdatedOn']] = $now;
    			}
    			if (key_exists($col, $manyToOneUpdateColumns) &&
    					key_exists($manyToOneUpdateColumns[$col].'UpdatedBy', $updateCols) &&
    					!key_exists($manyToOneUpdateColumns[$col].'UpdatedBy', $data) &&
    					!is_null($this->actingUserId))
    			{ //check if this column  maps to some other updatedBy column
    				$updateVals[$updateCols[$manyToOneUpdateColumns[$col].'UpdatedBy']] = $this->actingUserId;
    			}
    		}
    	}
    	if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
    		$updateVals[$updateCols['updatedOn']] = $now;
    	}
    	if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
    			!is_null($this->actingUserId)) {
    				$updateVals[$updateCols['updatedBy']] = $this->actingUserId;
    			}
    			if (key_exists('createdOn', $updateCols) && !key_exists('createdOn', $data)) { //check if this column has updatedOn column
    				$updateVals[$updateCols['createdOn']] = $now;
    			}
    			if (key_exists('createdBy', $updateCols) && !key_exists('createdBy', $data) &&
    					!is_null($this->actingUserId)) { //check if this column has updatedOn column
    						$updateVals[$updateCols['createdBy']] = $this->actingUserId;
    			}
    			if (count($updateVals) > 0) {
    				$resultsInsert = $tableGateway->insert($updateVals);
    				$newId = $tableGateway->getLastInsertValue();
    				$changeVals = [[
						'entity'   => $entityType,
						'field'    => 'newEntry',
						'id'       => $newId
    				]];
    				if ($reportChanges) {
    				    $this->reportChange($changeVals);
    				}
    				//@todo FACTOR THIS OUT!, belongs only in Patres
    				//create the roles associated with the newly created entity
    				if ($scope) {
    					$this->createAssociatedRoles($scope, $newId);
    				}
    				return $newId;
    			}
    			return false;
    }

    /**
     * Takes an array of associative arrays containing reports of changed columns.
     * Keys are table, column,  id, newValue, oldValue
     * table, column and id are required.
     * @param string[][] $data
     */
    public function reportChange($data)
    {
        $changesTableGateway = $this->getChangesTableGateway();
        if (is_null($changesTableGateway)) {
            return -1;
        }
    	$i = 0;
    	$date = new \DateTime(null, new \DateTimeZone('utc'));
    	foreach ($data as $values) {
    		if (isset($values['entity']) && isset($values['field']) && isset($values['id'])) {
    			if (isset($values['oldValue']) && $values['oldValue'] instanceof \DateTime) {
    				$values['oldValue'] = $this->formatDbDate($values['oldValue']);
    			}
    			if (isset($values['newValue']) && $values['newValue'] instanceof \DateTime) {
    				$values['newValue'] = $this->formatDbDate($values['newValue']);
    			}
    			if (isset($values['oldValue']) && is_array($values['oldValue'])) {
    				$values['oldValue'] = $this->formatDbArray($values['oldValue']);
    			}
    			if (isset($values['newValue']) && is_array($values['newValue'])) {
    				$values['newValue'] = $this->formatDbArray($values['newValue']);
    			}
    			$params = [
					'ChangedEntity'    => $values['entity'],
					'ChangedField'     => $values['field'],
					'ChangedIDValue'   => $values['id'],
					'NewValue'         => isset($values['newValue']) ? $values['newValue'] : null,
					'OldValue'         => isset($values['oldValue']) ? $values['oldValue'] : null,
					'UpdatedOn'        => $date->format('Y-m-d H:i:s'),
					'UpdatedBy'        => $this->actingUserId,
					'IpAddress'        => $_SERVER['REMOTE_ADDR'],
    			];
    			$changesTableGateway->insert($params);
    			$i++;
    		}
    	}

    	return $i;
    }

    /**
     * Get list of changes from database
     * @return \DateTime[][]|string[][]|unknown[][]|string[][][]|boolean[][][]|unknown[][][]|NULL[][]
     */
    public function getChanges()
    {
        $gateway = $this->getChangesTableGateway();
        $resultsChanges = $gateway->select();

        $tz = new \DateTimeZone('UTC');
        $changes = [];
        foreach ($resultsChanges as $row) {
            $user = null;
            if ($row['UpdatedBy']) {
                if ($this->getUserTable()) {
                    $user = $this->getUserTable()->getUser($row['UpdatedBy']);
                } else {
                    $user = [
                        'userId' => $this->filterDbId($row['UpdatedBy']),
                    ];
                }
            }
            $entity = $this->filterDbString($row['ChangedEntity']);
            $entityId = $this->filterDbString($row['ChangedIDValue']);
            //only bring in recognized entities
            if (!key_exists($entity, $this->entities)) {
                continue;
            }
            $change = [
                'changeId'  => $this->filterDbId($row['ChangeID']),
                'entity'    => $entity,
                'entitySpecification' => $this->entities[$entity],
                'entityId'  => $entityId,
                'object'    => null,
                'field'     => $this->filterDbString($row['ChangedField']),
                'newValue'  => $row['NewValue'],
                'oldValue'  => $row['OldValue'],
                'ipAddress' => $this->filterDbString($row['IpAddress']),
                'updatedOn' => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy' => $user,
            ];

            if (!is_null($this->entities[$entity]->updateReferenceDataFunction) &&
                method_exists($this, $this->entities[$entity]->updateReferenceDataFunction) &&
                !method_exists('SionTable', $this->entities[$entity]->updateReferenceDataFunction) //make sure noone's being sneaky
            ) {
                $objectFunction = $this->entities[$entity]->updateReferenceDataFunction;
                $change['object'] = $this->$objectFunction($entityId);
                if (is_null($change['object'])) { //it looks like the object has been deleted, let's fill in some data
                    $change['object'] = [
                        'isDeleted' => true,
                    ];
                    if ($this->entities[$entity]->entityKeyField) {
                        $change['object'][$this->entities[$entity]->entityKeyField] = $entityId;
                    }
                    if ($this->entities[$entity]->nameField) {
                        $change['object'][$this->entities[$entity]->nameField] = ucfirst($entity).' Id: '.$entityId;
                    }
                }
            }

            $changes[] = $change;
        }

        $sort = [];
        foreach($changes as $k=>$v) {
            $sort['updatedOn'][$k] = $v['updatedOn'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['updatedOn'], SORT_DESC, $changes);

        return $changes;
    }

    /**
     * Register a visit in the visits table as defined by the config
     * @param string $entity
     * @param int $entityId
     * @throws \InvalidArgumentException
     */
    public function registerVisit($entity, $entityId)
    {
        if (!isset($this->entities[$entity]))
        {
            throw new \InvalidArgumentException('Invalid entity submitted for visit registration');
        }

        if (!is_numeric($entityId)) {
            throw new \InvalidArgumentException('Invalid entity id submitted for visit registration');
        }

        $date = new \DateTime(null, new \DateTimeZone('UTC'));
        $params = [
            'Entity' => $entity,
            'EntityId' => $entityId,
            'UserId' => $this->actingUserId,
            'IpAddress' => $_SERVER['REMOTE_ADDR'],
            'VisitedAt' => $date->format('Y-m-d H:i:s'),
        ];
        $this->getVisitTableGateway()->insert($params);
    }

    /**
     * @return UserTable
     */
    public function getUserTable()
    {
        return $this->userTable;
    }

    /**
     *
     * @param UserTable $userTable
     * @return self
     */
    public function setUserTable($userTable)
    {
        $this->userTable = $userTable;
        return $this;
    }

    /**
     * Takes two DateTime objects and returns a string of the range of years the dates involve.
     * If one date is null, just the one year is returned. If both dates are null, null is returned.
     * @param \DateTime|null $startDate
     * @param \DateTime|null $endDate
     * @throws \InvalidArgumentException
     * @return string|null
     */
    public static function getYearRange($startDate, $endDate)
    {
        if ((!is_null($startDate) && !$startDate instanceof \DateTime) ||
            (!is_null($endDate) && !$endDate instanceof \DateTime))
        {
            throw new \InvalidArgumentException('Date parameters must be either DateTime instances or null.');
        }

        $text = '';
        if ((!is_null($startDate) && $startDate instanceof \DateTime) ||
            (!is_null($endDate) && $startDate instanceof \DateTime))
        {
            if (!is_null($startDate) xor !is_null($endDate)) { //only one is set
                if (!is_null($startDate)) {
                    $text .= $startDate->format('Y');
                } else {
                    $text .= $endDate->format('Y');
                }
            } else {
                $startYear = (int)$startDate->format('Y');
                $endYear = (int)$endDate->format('Y');
                if ($startYear == $endYear) {
                    $text .=' '. $startYear;
                } else {
                    $text .=' '. $startYear.'-'.$endYear;
                }
            }
            return $text;
        } else {
            return null;
        }
    }

    /**
     * Check if an assignment should be considered active based on the start/end date
     * @param null|\DateTime $startDate
     * @param null|\DateTime $endDate
     * @throws \InvalidArgumentException
     * @return boolean
     */
    public static function areWeWithinDateRange($startDate, $endDate)
    {
        if ((!is_null($startDate) && !$startDate instanceof \DateTime) ||
            (!is_null($endDate) && !$endDate instanceof \DateTime)
        ) {
            throw new \InvalidArgumentException('Invalid value passed to `isAssignmentActive`');
        }
        static $now;
        if (!isset($now)) {
            $timeZone = new \DateTimeZone('UTC');
            $now = new \DateTime(null, $timeZone);
        }
        return ($startDate < $now && (is_null($endDate) || $endDate > $now)) || (is_null($startDate) && (is_null($endDate) || $endDate > $now));
    }

    protected function filterDbId($str)
    {
        if (is_null($str) || $str === '' || $str == '0') {
            return null;
        }
        return (int) $str;
    }

    protected function filterDbString($str)
    {
        if (is_null($str) || $str === '') {
            return null;
        }
        static $filter;
        if (!is_object($filter)) {
            $filter = new FilterChain();
            $filter->attach(new StringTrim(), 2000);
            $filter->attach(new ToNull(), 1000);
            //@todo here I think I could strip tags; careful with Markdown fields
        }
        return $filter->filter($str);
    }

    protected function filterDbInt($str)
    {
        if (is_null($str) || $str === '') {
            return null;
        }
        return (int) $str;
    }

    protected function filterDbBool($str)
    {
        if (is_null($str) || $str === '' || $str == '0') {
            return false;
        }
        static $filter;
        if (!is_object($filter)) {
            $filter = new Boolean();
        }
        return $filter->filter($str);
    }

    /**
     *
     * @param string $str
     * @return \DateTime
     */
    protected function filterDbDate($str)
    {
        static $tz;
        $tz = new \DateTimeZone('UTC');
        if (is_null($str) || $str === '' || $str == '0000-00-00' || $str == '0000-00-00 00:00:00') {
            return null;
        }
        try {
            $return = new \DateTime($str, $tz);
        } catch(\Exception $e) {
            $return = null;
        }
        return $return;
    }

    /**
     *
     * @param string $str
     * @return \DateTime
     */
    protected function filterEmailString($str)
    {
        static $validator;
        $str = $this->filterDbString($str);
        if (is_null($str)) {
            return null;
        }
        if (!is_object($validator)) {
            $validator = new EmailAddress();
        }
        if ($validator->isValid($str)) {
            return $str;
        }
        return null;
    }

    /**
     *
     * @param \DateTime $object
     * @return string
     */
    protected function formatDbDate($object)
    {
        if (!$object instanceof \DateTime) {
            return $object;
        }
        return $object->format('Y-m-d H:i:s');
    }

    protected function formatDbArray($arr, $delimiter = '|', $trim = true)
    {
        if (!is_array($arr)) {
            return $arr;
        }
        if (empty($arr)) {
            return null;
        }
        if ($trim) {
            foreach ($arr as $value) {
                $value = trim($value);
            }
        }
        $return = implode($delimiter, $arr);
        return $return;
    }

    protected function filterDbArray($str, $delimiter = '|', $trim = true)
    {
        if ($str == '') {
            return [];
        }
        $return = explode($delimiter, $str);
        if ($trim) {
            foreach ($return as $value) {
                $value = trim($value);
            }
        }
        return $return;
    }

    public static function keyArray(array $a, $key, $unique = true)
    {
        $return = [];
        foreach ($a as $item) {
            if (!$unique) {
                if (isset($return[$item[$key]])) {
                    $return[$item[$key]][] = $item;
                } else {
                    $return[$item[$key]] = [$item];
                }
            } else {
                $return[$item[$key]] = $item;
            }
        }
        return $return;
    }

    /**
     * Pad a string to a certain length with another string
     *
     * @param  string  $input
     * @param  int $padLength
     * @param  string  $padString
     * @param  int $padType
     * @return string
     */
    public function strPad($input, $padLength, $padString = ' ', $padType = STR_PAD_RIGHT)
    {
        if (StringUtils::isSingleByteEncoding('UTF8')) {
            return str_pad($input, $padLength, $padString, $padType);
        }

        $lengthOfPadding = $padLength - strlen($input);
        if ($lengthOfPadding <= 0) {
            return $input;
        }

        $padStringLength = strlen($padString);
        if ($padStringLength === 0) {
            return $input;
        }

        $repeatCount = floor($lengthOfPadding / $padStringLength);

        if ($padType === STR_PAD_BOTH) {
            $repeatCountLeft = $repeatCountRight = ($repeatCount - $repeatCount % 2) / 2;

            $lastStringLength       = $lengthOfPadding - 2 * $repeatCountLeft * $padStringLength;
            $lastStringLeftLength   = $lastStringRightLength = floor($lastStringLength / 2);
            $lastStringRightLength += $lastStringLength % 2;

            $lastStringLeft  = substr($padString, 0, $lastStringLeftLength);
            $lastStringRight = substr($padString, 0, $lastStringRightLength);

            return str_repeat($padString, $repeatCountLeft) . $lastStringLeft
            . $input
            . str_repeat($padString, $repeatCountRight) . $lastStringRight;
        }

        $lastString = substr($padString, 0, $lengthOfPadding % $padStringLength);

        if ($padType === STR_PAD_LEFT) {
            return str_repeat($padString, $repeatCount) . $lastString . $input;
        }

        return $input . str_repeat($padString, $repeatCount) . $lastString;
    }

    /**
     * Pure and simple update call of the tableGateway
     * @param array $data
     * @param array $key
     * @return bool
     */
    public function update(array $data, array $key)
    {
        return $this->tableGateway->update($data, $key);
    }

    /**
     * Pure and simple insert call of the tableGateway
     * @param array|Insert $data
     * @return bool
     */
    public function insert($insert)
    {
        return $this->tableGateway->insert($insert);
    }

    public function getWhere()
    {
        return $this->where;
    }

    /**
     * @param Where|\Closure|string|array $where
     * @return \SionModel\Model\SionTable
     */
    public function setWhere($where)
    {
        $this->where = $where;
        return $this;
    }

    /**
     *
     * @return \Zend\Db\Adapter\Adapter
     */
    public function getAdapter()
    {
    	return $this->adapter;
    }

    /**
     *
     * @param \Zend\Db\Adapter\Adapter $adapter
     */
    public function setAdapter($adapter)
    {
    	$this->adapter = $adapter;
    }

    /**
     *
     * @return int
     */
    public function getActingUserId()
    {
        return $this->actingUserId;
    }

    /**
     *
     * @param int $actingUserId
     * @return self
     */
    public function setActingUserId($actingUserId)
    {
        $this->actingUserId = $actingUserId;
        return $this;
    }

    /**
     * @return TableGatewayInterface
     */
    public function getChangesTableGateway()
    {
        if (null == $this->changesTableGateway) {
            if (is_null($this->changeTableName)) {
                return null;
            }
            $this->changesTableGateway = new TableGateway($this->changeTableName, $this->adapter);
        }
        return $this->changesTableGateway;
    }

    /**
     *
     * @param TableGatewayInterface $gateway
     * @return self
     */
    public function setChangesTableGateway(TableGatewayInterface $gateway)
    {
        $this->changesTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGatewayInterface
     */
    public function getVisitTableGateway()
    {
        if (null == $this->visitsTableGateway) {
            $this->visitsTableGateway = new TableGateway($this->visitsTableName, $this->adapter);
        }
        return $this->visitsTableGateway;
    }

    /**
     *
     * @param TableGatewayInterface $gateway
     * @return self
     */
    public function setVisitTableGateway(TableGatewayInterface $gateway)
    {
        $this->visitsTableGateway = $gateway;
        return $this;
    }
}
