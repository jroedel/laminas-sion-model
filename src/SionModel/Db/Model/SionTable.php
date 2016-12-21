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
 * @todo make a EntitySpecification class that will parse and validate the options.
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
     * @todo Refactor to always use the Entity class
     * @var mixed[] $entities
     */
    protected $entities;

    /**
     *
     * @var string $changesTable
     */
    protected $changesTableName;

    /**
     *
     * @var TableGateway $changesTableGateway
     */
    protected $changesTableGateway;

    /**
     *
     * @var int
     */
    protected $actingUserId;

    protected $entityProblemPrototype;

    /**
     * If $changesTableName is left null, no changes will be made.
     * @param TableGatewayInterface $tableGateway
     * @param Entity[] $entities
     * @param int $actingUserId
     * @param null|string $changesTableName
     */
    public function __construct(TableGatewayInterface $tableGateway, $entities, $actingUserId, $changesTableName = null, $entityProblemPrototype = null)
    {
        $this->tableGateway = $tableGateway;
        $this->adapter      = $tableGateway->getAdapter();
        $this->entities		= $entities;
        $this->actingUserId = $actingUserId;
        $this->changeTableName  = $changesTableName;
        $this->entityProblemPrototype = $entityProblemPrototype;
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

        $return = array();
        foreach ($result as $row) {
            $return[] = $row;
        }
        return $return;
    }

    protected function updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $referenceEntity, $manyToOneUpdateColumns = null)
    {
    	if (is_null($tableName) || $tableName == '') {
    		throw new \Exception('No table name provided.');
    	}
    	if (is_null($tableKey) || $tableKey == '') {
    		throw new \Exception('No table key provided');
    	}
    	$now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    	$updateVals = array();
    	$changes = array();
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
    		$changes[] = array(
    				'table'    => $tableName,
    				'column'   => $col,
    				'id'       => $id,
    				'oldValue' => $value,
    				'newValue' => $data[$col],
    		);
    	}
    	if (count($updateVals) > 0) {
    		if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
    			$updateVals[$updateCols['updatedOn']] = $now;
    		}
    		if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
    				!is_null($this->actingUserId)) {
    					$updateVals[$updateCols['updatedBy']] = $this->actingUserId;
    				}
    				$result = $tableGateway->update($updateVals, array($tableKey => $id));
    				$this->reportChange($changes);
    				return $result;
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
    	$tableName     = $this->entities[$entity]['table_name'];
    	$tableKey      = $this->entities[$entity]['table_key'];
    	$tableGateway  = new TableGateway($tableName, $this->adapter);

    	if (!is_numeric($id)) {
    		throw new \InvalidArgumentException('Invalid id provided.');
    	}
    	$entityFunction = $this->entities[$entity]['update_reference_data_function'];
    	//@todo Check to make sure function exists
    	$entityData = $this->$entityFunction($id);
    	if (!$entityData) {
    		throw new \InvalidArgumentException('No entity provided.');
    	}
    	$updateCols = $this->entities[$entity]['update_columns'];
    	$manyToOneUpdateColumns = isset($this->entities[$entity]['many_to_one_update_columns']) ?
    	$this->entities[$entity]['many_to_one_update_columns'] : null;
    	if (isset($this->entities[$entity]['database_bound_data_preprocessor']) &&
    			method_exists($this, $preprocessor = $this->entities[$entity]['database_bound_data_preprocessor'])
    			) {
    				$data = $this->$preprocessor($data);
    			}
    			return $this->updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $entityData, $manyToOneUpdateColumns);
    }

    public function createEntity($entity, $data)
    {
    	$tableName                 = $this->entities[$entity]['table_name'];
    	$tableGateway              = new TableGateway($tableName, $this->adapter);
    	$scope                     = $this->entities[$entity]['scope'];
    	$requiredCols              = $this->entities[$entity]['required_columns_for_creation'];
    	$updateCols                = $this->entities[$entity]['update_columns'];
    	$manyToOneUpdateColumns    = isset($this->entities[$entity]['many_to_one_update_columns']) ? $this->entities[$entity]['many_to_one_update_columns'] : null;
    	if (isset($this->entities[$entity]['database_bound_data_preprocessor']) &&
    			method_exists($this, $preprocessor = $this->entities[$entity]['database_bound_data_preprocessor'])
    			) {
    				$data = $this->$preprocessor($data);
    			}
    			return $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope, $manyToOneUpdateColumns);
    }

    protected function createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope = null, $manyToOneUpdateColumns = null)
    {
    	//make sure required cols are being passed
    	foreach ($requiredCols as $colName) {
    		if (!isset($data[$colName])) {
    			return false;
    		}
    	}

    	$now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    	$updateVals = array();
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
    		if (key_exists($col.'UpdatedOn', $updateCols) && !key_exists($col.'UpdatedOn', $data)) { //check if this column has updatedOn column
    			$updateVals[$updateCols[$col.'UpdatedOn']] = $now;
    		}
    		if (key_exists($col.'UpdatedBy', $updateCols) && !key_exists($col.'UpdatedBy', $data) &&
    				!is_null($this->actingUserId)) { //check if this column has updatedOn column
    					$updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUserId;
    		}
    		if (is_array($manyToOneUpdateColumns) && isset($manyToOneUpdateColumns[$col])) {
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
    				$changeVals = array(array(
    						'table'    => $tableName,
    						'column'   => 'newEntry',
    						'id'       => $newId
    				));
    				$this->reportChange($changeVals);
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
    		if (isset($values['table']) && isset($values['column']) && isset($values['id'])) {
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
    			$params = array(
    					'IpAddress'        => $_SERVER['REMOTE_ADDR'],
    					'user_id'          => $this->actingUserId,
    					'ChangedTable'     => $values['table'],
    					'ChangedColumn'    => $values['column'],
    					'ChangedIDValue'   => $values['id'],
    					'NewValue'         => isset($values['newValue']) ? $values['newValue'] : null,
    					'OldValue'         => isset($values['oldValue']) ? $values['oldValue'] : null,
    					'UpdateDateTime'   => $date->format('Y-m-d H:i:s'),
    			);
    			$changesTableGateway->insert($params);
    			$i++;
    		}
    	}

    	return $i;
    }

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
            return array();
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
        $return = array();
        foreach ($a as $item) {
            if (!$unique) {
                if (isset($return[$item[$key]])) {
                    $return[$item[$key]][] = $item;
                } else {
                    $return[$item[$key]] = array($item);
                }
            } else {
                $return[$item[$key]] = $item;
            }
        }
        return $return;
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
     * @return TableGateway
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
     * @param TableGateway $gateway
     * @return self
     */
    public function setChangesTableGateway($gateway)
    {
        $this->changesTableGateway = $gateway;
        return $this;
    }
}
