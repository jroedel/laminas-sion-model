<?php
namespace SionModel\Db\Model;

use Zend\Db\Adapter\Adapter;

use Zend\Db\Sql\Insert;
use Zend\Db\TableGateway\TableGateway;
use Zend\Filter\Boolean;
use Zend\Filter\FilterChain;
use Zend\Validator\EmailAddress;
use SionModel\Entity\Entity;
use Zend\Db\TableGateway\TableGatewayInterface;
use Zend\Uri\Http;
use Zend\Db\Adapter\AdapterInterface;
use SionModel\Problem\ProblemTable;
use Zend\Db\Sql\Where;
use JUser\Model\UserTable;
use Zend\Stdlib\StringUtils;
use SionModel\Problem\EntityProblem;
use Zend\Db\ResultSet\ResultSet;
use Zend\Cache\Storage\StorageInterface;
use Zend\Filter\StringToLower;
use Zend\Filter\PregReplace;
use Zend\Mvc\MvcEvent;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use SionModel\Db\GeoPoint;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\ResultSet\ResultSetInterface;

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
 * @todo Factor out 'changes_table_name' from __contruct method. Make it a required key for entities
 */
class SionTable
{
    const SUGGESTION_ERROR = 'Error';
    const SUGGESTION_INREVIEW = 'In review';
    const SUGGESTION_ACCEPTED = 'Accepted';
    const SUGGESTION_DENIED = 'Denied';

    //@TODO not used do something with this
    const MAILING_STATUS_QUEUED = 'queued';
    const MAILING_STATUS_ERROR = 'error';
    const MAILING_STATUS_ERROR_1 = 'error-1';
    const MAILING_STATUS_ERROR_2 = 'error-2';
    const MAILING_STATUS_ERROR_3 = 'error-3';
    const MAILING_STATUS_SENT = 'sent';

    const MAILING_STATUS_LABELS = [
        self::MAILING_STATUS_QUEUED => 'Queued',
        self::MAILING_STATUS_ERROR => 'Error',
        self::MAILING_STATUS_ERROR_1 => 'Error 1st attempt',
        self::MAILING_STATUS_ERROR_2 => 'Error 2nd attempt',
        self::MAILING_STATUS_ERROR_3 => 'Error 3rd attempt',
        self::MAILING_STATUS_SENT => 'Sent',
    ];

    /**
     * @var TableGateway $tableGateway
     */
    protected $tableGateway;

    /**
     *
     * @var Adapter $adapter
     */
    protected $adapter;

    /**
     * A cache of already created table gateways, keyed by the table name
     * @var TableGateway[] $tableGatewaysCache
     */
    protected $tableGatewaysCache = [];

    protected $select;

    protected $sql;

    /**
     * @var Entity[] $entitySpecifications
     */
    protected $entitySpecifications = [];

    /**
     * @var string $changesTableName
     */
    protected $changesTableName;

    /**
     * @var string $visitsTableName
     */
    protected $visitsTableName;

    /**
     * @var TableGatewayInterface $changesTableGateway
     */
    protected $changesTableGateway;

    /**
     * @var TableGatewayInterface $visitTableGateway
     */
    protected $visitsTableGateway;
    
    /**
     * @var Select[] $selectPrototypes
     */
    protected $selectPrototypes = [];
    /**
     * @var int $actingUserId
     */
    protected $actingUserId;

    /**
     * A prototype of an EntityProblem to clone
     * @var EntityProblem $entityProblemPrototype
     */
    protected $entityProblemPrototype;

    /**
     * @var UserTable $userTable
     */
    protected $userTable;

    /**
    * @var StorageInterface $cache
    */
    protected $persistentCache;

    /**
    * @var mixed[] $memoryCache
    */
    protected $memoryCache = [];

    /**
     * List of keys that should be persisted onFinish
     * @var array
     */
    protected $newPersistentCacheItems = [];

    /**
     * @var int $maxItemsToCache
     */
    protected $maxItemsToCache = 2;

    /**
     * For each cache key, the list of entities they depend on.
     * For example:
     * [
     *      'events' => ['event', 'dates',  'emails', 'persons'],
     *      'unlinked-events => ['event'],
     * ]
     * That is to say, each time an entity of that type is created or updated,
     * the cache will be invalidated.
     * @var array
     */
    protected $cacheDependencies = [];

    /**
     * Represents the action of updating an entity
     * @var string
     */
    const ENTITY_ACTION_UPDATE = 'entity-action-update';
    /**
     * Represents the action of creating an entity
     * @var string
     */
    const ENTITY_ACTION_CREATE = 'entity-action-create';
    /**
     * Represents the action of suggesting an edit to an entity
     * @var string
     */
    const ENTITY_ACTION_SUGGEST = 'entity-action-suggest';

    /**
     * 
     * @param AdapterInterface $dbAdapter
     * @param ServiceLocatorInterface $serviceLocator
     * @param int $actingUserId
     */
    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId)
    {
        /**
         * @var EntitiesService $entities
         */
        $entities = $serviceLocator->get('SionModel\Service\EntitiesService');

        $config = $serviceLocator->get('SionModel\Config');
        if ($serviceLocator->has('SionModel\PersistentCache')) {
            $this->persistentCache = $serviceLocator->get('SionModel\PersistentCache');
            $hasCacheDependencies = false;
            $cacheDependencies = $this->persistentCache->getItem($this->getSionTableIdentifier().'cachedependencies', $hasCacheDependencies);
            if ($hasCacheDependencies) {
                $this->cacheDependencies = $cacheDependencies;
            }

            //setup listener for onFinish, so move objects to persistent storage
            $em = $serviceLocator->get('Application')->getEventManager();
            $em->attach(MvcEvent::EVENT_FINISH, [$this, 'onFinish'], -1);
        }

        $this->tableGateway     = new TableGateway('', $dbAdapter);
        $this->adapter          = $dbAdapter;
        $this->entitySpecifications = $entities->getEntities();
        $this->actingUserId     = $actingUserId;
        $this->changeTableName  = isset($config['changes_table']) ? $config['changes_table'] : null;
        $this->visitsTableName  = isset($config['visits_table']) ? $config['visits_table'] : null;
        if (isset($config['max_items_to_cache'])) {
            $this->maxItemsToCache = $config['max_items_to_cache'];
        }

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
     * Get all records from the mailings table
     * @return mixed[][]
     */
    public function getMailings()
    {
        $sql = "SELECT `MailingId`, `ToAddresses`, `MailingOn`, `MailingBy`, `Subject`,
`Body`, `Sender`, `MailingText`, `MailingTags`, `TrackingToken`, `OpenedFromIpAddress`,
`OpenedFromHeaders`, `OpenedOn`, `EmailTemplate`, `EmailLocale`, `Status`, `QueueUntil`,
`Attempt`, `MaxAttempts`, `ErrorMessage`, `StackTrace` FROM `a_data_mailing` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['MailingId']);
            $subject = $this->filterDbString($row['Subject']);
            $email = $this->filterDbString($row['ToAddresses']);
            $entities[$id] = [
                'mailingId'             => $id,
                'mailingName'           => 'Mail '.$id.': '.$subject,
                'emailAddress'          => $this->getEmailAddress($email),
                'mailingOn'             => $this->filterDbDate($row['MailingOn']),
                'mailingBy'             => $this->filterDbId($row['MailingBy']),
                'subject'               => $subject,
                'body'                  => $this->filterDbString($row['Body']),
                'sender'                => $this->filterDbString($row['Sender']),
                'text'                  => $this->filterDbString($row['MailingText']),
                'tags'                  => $this->filterDbArray($row['MailingTags']),
                'trackingToken'         => $this->filterDbString($row['TrackingToken']),
                'openedFromIpAddress'   => $this->filterDbString($row['OpenedFromIpAddress']),
                'openedFromHeaders'     => $row['OpenedFromHeaders'], //@todo process as JSON
                'openedOn'              => $this->filterDbDate($row['OpenedOn']),
                'emailTemplate'         => $this->filterDbString($row['EmailTemplate']),
                'emailLocale'           => $this->filterDbString($row['EmailLocale']),
                'status'                => $this->filterDbString($row['Status']),
                'attempt'               => $this->filterDbInt($row['Attempt']),
                'maxAttempts'           => $this->filterDbInt($row['MaxAttempts']),
                'queueUntil'            => $this->filterDbDate($row['QueueUntil']),
                'errorMessage'          => $this->filterDbString($row['ErrorMessage']),
                'stackTrace'            => $this->filterDbString($row['StackTrace']),
            ];
        }
        return $entities;
    }

    public function getMailing($id)
    {
        $mailings = $this->getMailings();

        if (!isset($mailings[$id]) || !($mailing = $mailings[$id])) {
            return null;
        }
        return $mailing;
    }

    /**
     * Get a string to identify this SionTable amongst others. Based on a transformed class name.
     * @return string
     */
    public function getSionTableIdentifier()
    {
        static $filter;
        if (!is_object($filter)) {
            $filter = new FilterChain();
            $filter->attach(new StringToLower())
            ->attach(new PregReplace(['pattern' => '/\\\\/', 'replacement' => '']));
        }
        return $filter->filter(get_class($this));
    }

    /**
     * At the end of the page load, cache any uncached items up to max_number_of_items_to_cache.
     * This is because serializing big objects can be very memory expensive.
     */
    public function onFinish()
    {
        $maxObjects = $this->getMaxItemsToCache();
        $count = 0;
        if (is_object($this->persistentCache)) {
            $this->persistentCache->setItem($this->getSionTableIdentifier().'cachedependencies', $this->cacheDependencies);
            foreach ($this->newPersistentCacheItems as $key) {
                if (key_exists($key, $this->memoryCache)) {
                    $this->persistentCache->setItem($key, $this->memoryCache[$key]);
                    $count++;
                }
                if ($count >= $maxObjects) {
                    break;
                }
            }
        }
    }

    /**
     * Get entity data for the specified entity and id
     * @param string $entity
     * @param number|string $id
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @return mixed[]
     */
    public function getObject($entity, $id, $failSilently = false)
    {
        $entitySpec = $this->getEntitySpecification($entity);
        $objectFunction = $entitySpec->getObjectFunction;
        if (!isset($objectFunction)
            || !method_exists($this, $objectFunction)
            || method_exists('SionTable', $objectFunction)
        ) {
            if ($failSilently) {
                try {
                    $object = $this->tryGettingObject($entity, $id);
                } catch (\Exception $e) {
                    $object = null;
                }
            } else {
                $object = $this->tryGettingObject($entity, $id);
            }
            return $object;
        }
        //@todo clarify which exceptions are thrown and when. Why the NOT operator?
        $entityData = $this->$objectFunction($id);
        if (!$entityData && !$failSilently) {
            throw new \InvalidArgumentException('No entity provided.');
        }
        return $entityData;
    }
    
    /**
     * Try making an SQL query to get an object for the given entityType and identifier
     * @param string $entity
     * @param number|string $entityId
     * @return mixed[]
     */
    protected function tryGettingObject($entity, $entityId)
    {
        if (!isset($entityId)) {
            throw new \Exception('Invalid entity id requested.');
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (!isset($entitySpec->tableName) || !isset($entitySpec->tableKey)) {
            throw new \Exception("Invalid entity configuration for `$entity`.");
        }
        $gateway = $this->getTableGateway($entitySpec->tableName);
        $select = $this->getSelectPrototype($entity);
        $predicate = new Operator($entitySpec->tableKey, Operator::OPERATOR_EQUAL_TO, $entityId);
        $select->where($predicate);
        $result = $gateway->selectWith($select);
        if (!$result instanceof ResultSetInterface) {
            throw new \Exception("Unexpected query result for entity `$entity`");
        }
        $results = $result->toArray();
        if (!isset($results[0])) {
            return null;
        }
        
        $object = $this->processEntityRow($entity, $results[0]);
        return $object;
    }

    public function getObjects($entity, $query = [], $options = [])
    {
        $entitySpec = $this->getEntitySpecification($entity);
        $objectsFunction = $entitySpec->getObjectsFunction;
        if (!isset($objectsFunction)
            || !method_exists($this, $objectsFunction) 
            || method_exists('SionTable', $objectsFunction)
        ) {
            $objects = $this->queryObjects($entity, $query, $options);
            if (!isset($objects)) {
                throw new \Exception('Invalid get_objects_function set for entity \''.$entity.'\'');
            }
            return $objects;
        }
        $objects = $this->$objectsFunction($query, $options);
        $shouldFailSilently = isset($options['failSilently']) ? (bool)$options['failSilently'] : false;
        if (!$objects && !$shouldFailSilently) {
            throw new \InvalidArgumentException('No entity provided.');
        }
        return $objects;
    }
    
    /**
     * Try a query given certain parameters and options
     * @param string $entity
     * @param array|PredicateInterface|PredicateInterface[] $query
     * @param array $options 'failSilently'(bool), 'orCombination'(bool), 'limit'(int), 'offset'(int), 'order'
     * @throws \Exception
     * @return mixed[][]
     */
    public function queryObjects($entity, $query = [], $options = [])
    {
        $cacheKey = null;
        if (is_array($query) && empty($query) && empty($options)) {
            $cacheKey = 'query-objects-'.$entity;
            if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
                return $cache;
            }
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (!isset($entitySpec->tableName) || !isset($entitySpec->tableKey)) {
            return null; //the calling function should throw an exception
        }
        $gateway = $this->getTableGateway($entitySpec->tableName);
        $select = $this->getSelectPrototype($entity);
        $fieldMap = $entitySpec->updateColumns;
        
        $shouldFailSilently = isset($options['failSilently']) ? (bool)$options['failSilently'] : false;
        $combination = (isset($options['orCombination']) && $options['orCombination']) ? PredicateSet::OP_OR : PredicateSet::OP_AND;
        
        if ($query instanceof PredicateInterface) {
            $where = $query;
        } else {
            $where = new Where();
            foreach ($query as $key => $value) {
                if ($value instanceof PredicateInterface) {
                    $where->addPredicate($value, $combination);
                }
                if (isset($fieldMap[$key])) {
                    continue;
                }
                if (is_array($value)) {
                    $clause = new In($fieldMap[$key], $value);
                } else {
                    $clause = new Operator($fieldMap[$key], Operator::OPERATOR_EQUAL_TO, $value);
                }
                $where->addPredicate($clause, $combination);
            }
        }
        if (isset($options['limit'])) {
            $select->limit($options['limit']);
        }
        if (isset($options['offset'])) {
            $select->offset($options['offset']);
        }
        if (isset($options['order'])) {
            $select->order($options['order']);
        }
        
        $result = $gateway->selectWith($select);
        if (!$result instanceof ResultSetInterface) {
            if ($shouldFailSilently) {
                return null;
            } else {
                $type = gettype($result);
                if ('object' === $type) {
                    $type = get_class($result);
                }
                throw new \Exception("Unexpected query result of type `$type`");
            }
        }
        $results = $result->toArray();
        $objects = [];
        foreach ($results as $row) {
            $data = $this->processEntityRow($entity, $row);
            $id = isset($data[$entitySpec->entityKeyField]) 
                ? $data[$entitySpec->entityKeyField]
                : null;
            //make sure we don't overwrite other entries
            if (isset($id) && !isset($objects[$id])) {
                $objects[$id] = $data;
            } else {
                $objects[] = $data;
            }
        }
        
        if (isset($cacheKey)) {
            //@todo we could create a 'dependsOnEntities' key in the entity specs to add it to this array
            $this->cacheEntityObjects($cacheKey, $objects, [$entitySpec->name]); 
        }
        return $objects;
    }
    
    /**
     * Process an SQL-returned row, mapping column names to our field names
     * @param string $entity
     * @param array $row
     * @return mixed[]
     */
    protected function processEntityRow($entity, array $row)
    {
        $entitySpec = $this->getEntitySpecification($entity);
        //@todo check if the entity has registered a row processor
        $columnsMap = $entitySpec->updateColumns;
        $data = [];
        foreach ($row as $column => $value) {
            if (isset($columnsMap[$column])) {
                //@todo add some generic filter here to convert datetimes
                $data[$columnsMap[$column]] = $value;
            }
        }
        return $data;
    }

    /**
     * Get a standardized select object to retrieve records from the database
     * @return Select
     */
    protected function getSelectPrototype($entity)
    {
        if (isset($this->selectPrototypes[$entity])) {
            return clone $this->selectPrototypes[$entity];
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (!isset($entitySpec->tableName) || empty($entitySpec->updateColumns)) {
            throw new \Exception("Cannot construct select prototype for `$entity`");
        }
        $select = new Select($entitySpec->tableName);
        $select->columns(array_values($entitySpec->updateColumns));
        //@todo maybe add an order config to entity specs
        $this->selectPrototypes[$entity] = $select;
        return clone $select;
    }
    
    /**
     * Sort an entity object
     * @param mixed[] $array
     * @param array $fieldsAndSortParameter ex. ['name' => SORT_ASC, 'country' = SORT_DESC]
     */
    public static function sortEntityObject(&$array, $fieldsAndSortParameter)
    {
        foreach ($fieldsAndSortParameter as $field => $sortParameter) {
            if (!is_string($field) || ($sortParameter !== SORT_ASC &&
                $sortParameter !== SORT_DESC)
            ) {
                throw new \InvalidArgumentException('The $fieldsAndSortParamer should be in format [\'name\' => SORT_ASC, \'country\' = SORT_DESC]');
            }
        }

        $sort = [];
        foreach ($array as $k => $v) {
            foreach ($fieldsAndSortParameter as $field => $sortParameter) {
                $sort[$field][$k] = $v[$field];
            }
        }
        # sort by event_type desc and then title asc
        $params = [];
        foreach ($fieldsAndSortParameter as $field => $sortParameter) {
            $params[] = $sort[$field];
            $params[] = $sortParameter;
        }
        $params[] = &$array;
        call_user_func_array('array_multisort', $params);
    }

    /**
     * @param Where|\Closure|string|array $where
     * @param null|string
     * @param null|array
     * @return array
     */
    public function fetchSome($where, $sql = null, $sqlArgs = null)
    {
        if (null === $where && null === $sql) {
            throw new \InvalidArgumentException('No query requested.');
        }
        if (null !== $sql) {
            if (null === $sqlArgs) {
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
        if (null === $unprocessedUrls) {
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
            if (null !== $urlRow['url']) {
                $url = new Http($urlRow['url']);
                if ($url->isValid()) {
                    if (null === $urlRow['label'] || 0 === strlen($urlRow['label'])) {
                        $urlRow['label'] = $url->getHost();
                    }
                    $urlRow['url'] = $url->toString();
                    $urls[] = $urlRow;
                }
            }
        }
        return $urls;
    }

    /**
     * Validate urls and configure their labels
     * @param string[] $unprocessedUrls Should be a 2-dimensional array, each element containing a 'url' and 'label' key
     * @throws \InvalidArgumentException
     * @return string[]
     *
     * @todo check URL against Google Safe Browsing
     */
    public static function processJsonUrls($unprocessedUrls, $unacceptedLabels = [])
    {
        if (null === $unprocessedUrls) {
            return null;
        }
        if (!is_array($unprocessedUrls)) {
            throw new \InvalidArgumentException('unprocessedUrls must be a 2-dimensional array, each element containing keys \'url\' and \'label\'');
        }
        $lowerUnacceptedLabels = [];
        foreach ($unacceptedLabels as $value) {
            if (is_string($value)) {
                $lowerUnacceptedLabels[] = strtolower($value);
            }
        }

        $urls = [];
        foreach ($unprocessedUrls as $urlRow) {
            if (!key_exists('url', $urlRow)) {
                throw new \InvalidArgumentException('Each element of unprocessedUrls must contain key \'url\'');
            }
            if (isset($urlRow['label'])
                && in_array(strtolower($urlRow['label']), $lowerUnacceptedLabels)
            ) {
                continue;
            }
            if (null !== $urlRow['url']) {
                $url = new Http($urlRow['url']);
                if ($url->isValid()) {
                    $urls[] = $url->toString();
                }
            }
        }
        if (0 === count($urls)) {
            $urls = null;
        } elseif (1 === count($urls)) {
            $urls = $urls[0];
        }
        return $urls;
    }

    /**
     * Return the EntitySpec for a given $entity string identifier
     * @param string $entity
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @return \SionModel\Entity\Entity
     */
    protected function getEntitySpecification($entity)
    {
        if (empty($this->entitySpecifications)) {
            throw new \Exception('No entity specifications are loaded. Please see sionmodel.global.php.dist');
        }
        if (!key_exists($entity, $this->entitySpecifications)) {
            throw new \InvalidArgumentException('The request entity is unspecified. \''.$entity.'\'');
        }
        return $this->entitySpecifications[$entity];
    }

    /**
     * Make sure entity type is prepared for update/create
     * @param string $entity
     * @throws \Exception
     * @return boolean
     */
    public function isReadyToUpdateAndCreate($entity)
    {
        $entitySpec = $this->getEntitySpecification($entity);
        if (!$entitySpec->isEnabledForUpdateAndCreate()) {
            throw new \Exception('The following config keys are required to update entity \''.
                '\': table_name, table_key, update_columns');
        }
        if (isset($this->entitySpecifications[$entity]->getObjectFunction)
            && (!method_exists($this, $this->entitySpecifications[$entity]->getObjectFunction)
                || method_exists('SionTable', $this->entitySpecifications[$entity]->getObjectFunction))
        ) {
            throw new \Exception('\'get_object_function\' configuration for entity \''.$entity.'\' refers to a function that doesn\'t exist');
        }
        if (isset($this->entitySpecifications[$entity]->databaseBoundDataPreprocessor) &&
            null !== $this->entitySpecifications[$entity]->databaseBoundDataPreprocessor &&
            !method_exists($this, $this->entitySpecifications[$entity]->databaseBoundDataPreprocessor)
        ) {
            throw new \Exception('\'databaseBoundDataPreprocessor\' configuration for entity \''.$entity.'\' refers to a function that doesn\'t exist');
        }
        return true;
    }

    /**
     * Simply incur an update of the 'updatedOn' field of an entity.
     * If a  field is indicated, it may incur a field-specific updatedOn update.
     * @param string $entity
     * @param number $id
     * @param array|string $field
     * @throws \InvalidArgumentException
     * @return boolean
     */
    public function touchEntity($entity, $id, $field = null, $refreshCache = true)
    {
        if (!$this->existsEntity($entity, $id)) {
            throw new \InvalidArgumentException('Entity doesn\'t exist.');
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (null === $field && null !== $entitySpec->entityKeyField) {
            throw new \InvalidArgumentException("Please specify the entity_key_field configuration for entity '$entity' to use the touchEntity function.");
        }
        if (null !== $field && !is_array($field)) {
            $field = [$field];
        }
        $fields = null !== $field ? $field : [$entitySpec->entityKeyField];
//         var_dump($fields);
        return $this->updateEntity($entity, $id, [], $fields, $refreshCache);
    }

    /**
     *
     * @param string $entity
     * @param number $id
     * @param array $data
     * @param array $fieldsToTouch
     * @throws \InvalidArgumentException
     * @return boolean
     */
    public function updateEntity($entity, $id, $data, array $fieldsToTouch = [], $refreshCache = true)
    {
        if (!$this->isReadyToUpdateAndCreate($entity)) {
            throw new \InvalidArgumentException('Improper configuration for entity \''.$entity.'\'');
        }
        $entitySpec     = $this->getEntitySpecification($entity);
        $tableName      = $entitySpec->tableName;
        $tableKey       = $entitySpec->tableKey;
        $tableGateway   = new TableGateway($tableName, $this->adapter);

        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid id provided.');
        }
        $entityData = $this->getObject($entity, $id);
        $updateCols = $entitySpec->updateColumns;
        $manyToOneUpdateColumns = isset($entitySpec->manyToOneUpdateColumns) ?
           $entitySpec->manyToOneUpdateColumns : null;
        $reportChanges = isset($entitySpec->reportChanges) ?
           $entitySpec->reportChanges : false;

        /*
         * Run the new/old data throught the preprocessor function if it exists
         */
        if (isset($entitySpec->databaseBoundDataPreprocessor) &&
            null !== $entitySpec->databaseBoundDataPreprocessor &&
            method_exists($this, $entitySpec->databaseBoundDataPreprocessor) &&
            !method_exists('SionTable', $entitySpec->databaseBoundDataPreprocessor) //make sure noone's being sneaky)
        ) {
            $preprocessor = $entitySpec->databaseBoundDataPreprocessor;
            $data = $this->$preprocessor($data, $entityData, self::ENTITY_ACTION_UPDATE);
        }
        $return = $this->updateHelper($id, $data, $entity, $tableKey, $tableGateway, $updateCols, $entityData, $manyToOneUpdateColumns, $reportChanges, $fieldsToTouch);

        if ($refreshCache) {
            $this->removeDependentCacheItems($entity);
        }

        //if the id is being changed, update it
        $keyField = $entitySpec->entityKeyField;
        if (isset($data[$keyField]) && null !== $data[$keyField] &&
            isset($entityData[$keyField]) && null !== $entityData[$keyField] &&
            $data[$keyField] !== $entityData[$keyField]
        ) {
            $id = $data[$keyField];
        }

        /*
         * Run the changed/new data through the preprocessor function if it exists
         */
        if (isset($entitySpec->databaseBoundDataPostprocessor) &&
            null !== $entitySpec->databaseBoundDataPostprocessor &&
            method_exists($this, $entitySpec->databaseBoundDataPostprocessor) &&
            !method_exists('SionTable', $entitySpec->databaseBoundDataPostprocessor) //make sure noone's being sneaky
        ) {
            $newEntityData = $this->getObject($entity, $id);
            $postprocessor = $entitySpec->databaseBoundDataPostprocessor;
            $this->$postprocessor($data, $newEntityData, self::ENTITY_ACTION_UPDATE);
        }
        return $return;
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
    protected function updateHelper($id, $data, $entityType, $tableKey, TableGatewayInterface $tableGateway, $updateCols, $referenceEntity, $manyToOneUpdateColumns = null, $reportChanges = false, array $fieldsToTouch = [])
    {
        if (null === $entityType || $entityType === '') {
            throw new \Exception('No entity provided.');
        }
        if (null === $tableKey || $tableKey === '') {
            throw new \Exception('No table key provided');
        }
        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $updateVals = [];
        $changes = [];
        foreach ($referenceEntity as $field => $value) {
            $tempNewValue = null;
            if (!in_array($field, $fieldsToTouch) &&
                (!key_exists($field, $updateCols) || !key_exists($field, $data) || $value == $data[$field])
            ) {
                continue;
            } elseif (in_array($field, $fieldsToTouch) && !key_exists($field, $data)) {
                $data[$field] = $value;
            }
            if ($data[$field] instanceof \DateTime) { //convert Date objects to strings
                $data[$field] = $data[$field]->format('Y-m-d H:i:s');
            }
            if (is_array($data[$field])) { //convert arrays to strings
                if (empty($data[$field])) {
                    $data[$field] = null;
                } else {
                    $data[$field] = implode('|', $data[$field]); //pipe (|) is a good unused character for separating
                }
            }
            if ($data[$field] instanceof GeoPoint) {
                $tempNewValue = $data[$field]; //insert the string value, not the expression into the changes table
                $data[$field] = new Expression($data[$field]->getDatabaseInsertString());
            }
            $updateVals[$updateCols[$field]] = $data[$field];
            if (key_exists($field.'UpdatedOn', $updateCols) && !key_exists($field.'UpdatedOn', $data) //check if this column has updatedOn column
            ) {
                $updateVals[$updateCols[$field.'UpdatedOn']] = $now;
            }
            if (key_exists($field.'UpdatedBy', $updateCols) && !key_exists($field.'UpdatedBy', $data) &&
                null !== $this->actingUserId
            ) { //check if this column has updatedBy column
                $updateVals[$updateCols[$field.'UpdatedBy']] = $this->actingUserId;
            }
            if (is_array($manyToOneUpdateColumns) && isset($manyToOneUpdateColumns[$field])) {
                if (key_exists($manyToOneUpdateColumns[$field].'UpdatedOn', $updateCols) &&
                    !key_exists($manyToOneUpdateColumns[$field].'UpdatedOn', $data)) { //check if this column maps to some other updatedOn column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$field].'UpdatedOn']] = $now;
                }
                if (key_exists($manyToOneUpdateColumns[$field].'UpdatedBy', $updateCols) &&
                    !key_exists($manyToOneUpdateColumns[$field].'UpdatedBy', $data) &&
                    null !== $this->actingUserId) { //check if this column  maps to some other updatedBy column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$field].'UpdatedBy']] = $this->actingUserId;
                }
            }

            $changes[] = [
                'entity'   => $entityType,
                'field'    => $field,
                'id'       => $id,
                'oldValue' => $value,
                'newValue' => isset($tempNewValue) ? $tempNewValue : $data[$field],
            ];
        }
        if (count($updateVals) > 0) {
            if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
                $updateVals[$updateCols['updatedOn']] = $now;
            }
            if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
                null !== $this->actingUserId) {
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

    public function createEntity($entity, $data, $refreshCache = true)
    {
        if (!$this->isReadyToUpdateAndCreate($entity)) {
            throw new \InvalidArgumentException('Improper configuration for entity \''.$entity.'\'');
        }
        $entitySpec                = $this->getEntitySpecification($entity);
        $tableName                 = $entitySpec->tableName;
        $tableGateway              = $this->getTableGateway($tableName);
        $requiredCols              = $entitySpec->requiredColumnsForCreation;
        $updateCols                = $entitySpec->updateColumns;
        $manyToOneUpdateColumns    = $entitySpec->manyToOneUpdateColumns;
        $reportChanges             = $entitySpec->reportChanges;

        /**
         * Run the changed/new data through the preprocessor function if it exists
         * @see Entity
         */
        if (null !== $preprocessor = $entitySpec->databaseBoundDataPreprocessor) {
            $data = $this->$preprocessor($data, [], self::ENTITY_ACTION_CREATE);
        }

        $return = $this->createHelper($data, $requiredCols, $updateCols, $entity, $tableGateway, $manyToOneUpdateColumns, $reportChanges);

        if ($refreshCache) {
            $this->removeDependentCacheItems($entity);
        }

        /**
         * Run the changed/new data through the postprocessor function if it exists
         * @see Entity
         * @todo Code for the case of no AUTO_INCREMENT (look for the $tableKey set in the $data)
         */
        if ($return && //$return is should be the AUTO_INCREMENT value of the inserted row
            isset($entitySpec->databaseBoundDataPostprocessor) &&
            null !== $entitySpec->databaseBoundDataPostprocessor
        ) {
            $newEntityData = $this->getObject($entity, $return);
            $postprocessor = $entitySpec->databaseBoundDataPostprocessor;
            $this->$postprocessor($data, $newEntityData, self::ENTITY_ACTION_CREATE);
        }

        return $return;
    }

    /**
     * @param mixed[] $data
     * @param string[] $requiredCols
     * @param string[] $updateCols
     * @param string $entityType
     * @param TableGatewayInterface $tableGateway
     * @param string|null $scope
     * @param string[]|null $manyToOneUpdateColumns
     */
    protected function createHelper(
        $data,
        $requiredCols,
        $updateCols,
        $entityType,
        $tableGateway,
        $manyToOneUpdateColumns = null,
        $reportChanges = false
    ) {
        //make sure required cols are being passed
        foreach ($requiredCols as $colName) {
            if (!isset($data[$colName])) {
                throw new \InvalidArgumentException("Not all required fields for the creation of an entity were provided. Missing `$colName`");
            }
        }

        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $updateVals = [];
        foreach ($data as $col => $value) {
            if (!isset($updateCols[$col])) {
                continue;
            }
            if ($data[$col] instanceof \DateTime) {
                $data[$col] = $data[$col]->format('Y-m-d H:i:s');
            }
            if (is_array($data[$col])) {
                $data[$col] = $this->formatDbArray($data[$col]);
            }
            if ($data[$col] instanceof GeoPoint) {
                $data[$col] = new Expression($data[$col]->getDatabaseInsertString());
            }
            $updateVals[$updateCols[$col]] = $data[$col];
            if (null !== $value && isset($updateCols[$col.'UpdatedOn']) && !isset($data[$col.'UpdatedOn'])) { //check if this column has updatedOn column
                $updateVals[$updateCols[$col.'UpdatedOn']] = $now;
            }
            if (null !== $value && isset($updateCols[$col.'UpdatedBy']) && !isset($data[$col.'UpdatedBy']) &&
                null !== $this->actingUserId
            ) { //check if this column has updatedOn column
                $updateVals[$updateCols[$col.'UpdatedBy']] = $this->actingUserId;
            }
            if (null !== $value && is_array($manyToOneUpdateColumns) && isset($manyToOneUpdateColumns[$col])) {
                if (isset($manyToOneUpdateColumns[$col]) &&
                    isset($updateCols[$manyToOneUpdateColumns[$col].'UpdatedOn']) &&
                    !isset($data[$manyToOneUpdateColumns[$col].'UpdatedOn'])) { //check if this column maps to some other updatedOn column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$col].'UpdatedOn']] = $now;
                }
                if (isset($manyToOneUpdateColumns[$col]) &&
                    isset($updateCols[$manyToOneUpdateColumns[$col].'UpdatedBy']) &&
                    !isset($data[$manyToOneUpdateColumns[$col].'UpdatedBy']) &&
                    null !== $this->actingUserId) { //check if this column  maps to some other updatedBy column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$col].'UpdatedBy']] = $this->actingUserId;
                }
            }
        }
        if (isset($updateCols['updatedOn']) && !isset($updateVals[$updateCols['updatedOn']])) {
            $updateVals[$updateCols['updatedOn']] = $now;
        }
        if (isset($updateCols['updatedBy']) && !isset($updateVals[$updateCols['updatedBy']]) &&
            null !== $this->actingUserId
        ) {
            $updateVals[$updateCols['updatedBy']] = $this->actingUserId;
        }
        if (isset($updateCols['createdOn']) && !isset($data['createdOn'])) { //check if this column has updatedOn column
            $updateVals[$updateCols['createdOn']] = $now;
        }
        if (isset($updateCols['createdBy']) && !isset($data['createdBy']) &&
            null !== $this->actingUserId
        ) { //check if this column has updatedOn column
            $updateVals[$updateCols['createdBy']] = $this->actingUserId;
        }
        if (count($updateVals) > 0) {
            $tableGateway->insert($updateVals);
            $newId = $tableGateway->getLastInsertValue();
            $changeVals = [[
                'entity'   => $entityType,
                'field'    => 'newEntry',
                'id'       => $newId
            ]];
            if ($reportChanges) {
                $this->reportChange($changeVals);
            }
            return $newId;
        }
        return false;
    }

    /**
     * Get an instance of a TableGateway for a particular table name
     * @param string $tableName
     * @return TableGateway
     */
    protected function getTableGateway($tableName)
    {
        if (isset($this->tableGatewaysCache[$tableName])) {
            return $this->tableGatewaysCache[$tableName];
        }
        $gateway = new TableGateway($tableName, $this->adapter);
        //@todo is there a way to make sure the table exists?
        return $this->tableGatewaysCache[$tableName] = $gateway;
    }

    /**
     * Check if an entity exists
     * @param string $entity
     * @param number|string $id
     * @throws \Exception
     * @return boolean
     */
    public function existsEntity($entity, $id)
    {
        $entitySpec = $this->getEntitySpecification($entity);
        $tableName  = $entitySpec->tableName;
        $tableKey   = $entitySpec->tableKey;
        $gateway    = $this->getTableGateway($tableName);
        $result     = $gateway->select([$tableKey => $id]);
        if (!$result instanceof ResultSet || 0 === $result->count()) {
            return false;
        }
        if ($result->count() > 1) {
            throw new \Exception('Improper primary key configuration of entity \''.$entity.'\'. Multiple records returned.');
        }
        return true;
    }
    
    /**
     * Similar to the existsEntity function, this one checks for the existence of
     * several objects, by id and returns an associative array mapping $id => bool
     * @param string $entity
     * @param number[]|string[] $ids
     * @return bool[]
     */
    public function existsEntities($entity, array $ids)
    {
        $entitySpec = $this->getEntitySpecification($entity);
        $tableName  = $entitySpec->tableName;
        $tableKey   = $entitySpec->tableKey;
        
        $return = [];
        foreach ($ids as $value) {
            $return[$value] = false;
        }
        
        $select = new Select($tableName);
        $select->columns([$tableKey]);
        
        /** @var TableGateway $gateway */
        $gateway    = $this->getTableGateway($tableName);
        $result     = $gateway->select([$tableKey => $ids]);
        if (!$result instanceof ResultSet) {
            return $return;
        }
        $results = $result->toArray();
        foreach ($results as $row) {
            if (isset($return[$row[$tableKey]])) {
                $return[$row[$tableKey]] = true;
            }
        }
        return $return;
    }

    /**
     * Delete an entity. Protection against deleting other records provided by existsEntity check
     * @param string $entity
     * @param number|string $id
     * @throws \Exception
     * @return number
     */
    public function deleteEntity($entity, $id, $refreshCache = true)
    {
        $entitySpec = $this->getEntitySpecification($entity);
        //make sure we have enough information to delete
        if (!$entitySpec->isEnabledForEntityDelete()) {
            throw new \Exception('Entity \''.$entity.'\' is not configured for deleting.');
        }
        //make sure entity exists before attempting to delete
        if (!$this->existsEntity($entity, $id)) {
            throw new \Exception('The requested entity for deletion '.$entity.$id.' does not exist.');
        }
        $tableName = $entitySpec->tableName;
        $tableKey = $entitySpec->tableKey;
        $gateway = $this->getTableGateway($tableName);
        $return = $gateway->delete([$tableKey => $id]);

        if ($return !== 1) {
            throw new \Exception('Delete action expected a return code of \'1\', received \''.$return.'\'');
        }

        if ($entitySpec->reportChanges) {
            $changeVals = [[
                'entity'    => $entity,
                'field'   => 'entryDeleted',
                'id'       => $id
            ]];
            $this->reportChange($changeVals);
        }

        if ($refreshCache) {
            $this->removeDependentCacheItems($entity);
        }

        return $return;
    }

    /**
     * Takes an array of associative arrays containing reports of changed columns.
     * Keys are table(req), field(req),  id(req), newValue, oldValue.
     * @todo include the UserAgent
     * @param string[][] $data
     */
    public function reportChange($data)
    {
        $changesTableGateway = $this->getChangesTableGateway();
        if (!$changesTableGateway instanceof TableGatewayInterface) {
            return -1;
        }
        $i = 0;
        $date = new \DateTime(null, new \DateTimeZone('utc'));
        foreach ($data as $row) {
            if (isset($row['entity']) && isset($row['field']) && isset($row['id'])
            ) {
                if (isset($row['oldValue']) && $row['oldValue'] instanceof \DateTime) {
                    $row['oldValue'] = $this->formatDbDate($row['oldValue']);
                }
                if (isset($row['newValue']) && $row['newValue'] instanceof \DateTime) {
                    $row['newValue'] = $this->formatDbDate($row['newValue']);
                }
                if (isset($row['oldValue']) && is_array($row['oldValue'])) {
                    $row['oldValue'] = $this->formatDbArray($row['oldValue']);
                }
                if (isset($row['newValue']) && is_array($row['newValue'])) {
                    $row['newValue'] = $this->formatDbArray($row['newValue']);
                }
                $params = [
                    'ChangedEntity'    => $row['entity'],
                    'ChangedField'     => $row['field'],
                    'ChangedIDValue'   => $row['id'],
                    'NewValue'         => isset($row['newValue']) ? $row['newValue'] : null,
                    'OldValue'         => isset($row['oldValue']) ? $row['oldValue'] : null,
                    'UpdatedOn'        => $date->format('Y-m-d H:i:s'),
                    'UpdatedBy'        => $this->actingUserId,
                    'IpAddress'        => $_SERVER['REMOTE_ADDR'], //@todo there should be a better way to do this
                ];
                $changesTableGateway->insert($params);
                $i++;
            }
        }

        return $i;
    }

    public function getChangesCountPerMonth()
    {
        $tableEntities = $this->getTableEntities();
        $predicate = new Where();
        $gateway = $this->getChangesTableGateway();
        $select = new Select($this->changeTableName);
        $select->columns(['TheMonth' => new Expression('MONTH(`UpdatedOn`)'), 'TheYear' => new Expression('YEAR(`UpdatedOn`)'), 'Count' => new Expression('Count(*)')]);
        $select->group(['TheMonth', 'TheYear']);
        $select->where($predicate->in('ChangedEntity', $tableEntities));
        $select->order('TheYear, TheMonth');
        $resultsChanges = $gateway->selectWith($select);
        $months = [];
        foreach ($resultsChanges as $row) {
            if (is_numeric($row['TheMonth']) && $row['TheMonth'] > 0  && $row['TheMonth'] <= 12 &&
                is_numeric($row['TheYear']) && $row['TheYear'] >= 2015 && $row['TheYear'] <= 2050 &&
                is_numeric($row['Count'])
            ) {
                $key = (string)($row['TheYear'] * 100 + $row['TheMonth']);
                $months[$key] = $this->filterDbInt($row['Count']);
            }
        }
        return $months;
    }

    /**
     * Get the list of entities registered to this particular SionTable.
     * The entity spec must specify the 'sion_model_class' option.
     * @return string[]
     */
    public function getTableEntities()
    {
        $tableEntities = [];
        foreach ($this->entitySpecifications as $key => $entitySpec) {
            if ($entitySpec->sionModelClass == get_class($this)) {
                $entityName = (null !== $entitySpec->name) ? $entitySpec->name : $key;
                $tableEntities[] = $entityName;
            }
        }
        return $tableEntities;
    }

    /**
     * Get changes for a particular entity
     * @param string $entity
     * @param int|string $entityId
     * @return mixed[]
     */
    public function getEntityChanges($entity, $entityId)
    {
        $gateway = $this->getChangesTableGateway();
        $select = new Select($this->changeTableName);
        $select->where([
            'ChangedEntity' => $entity,
            'ChangedIDValue' => $entityId,
        ]);
        $select->order(['UpdatedOn' => 'DESC']);
        $resultsChanges = $gateway->selectWith($select);
        $objects = [
            $entity => [
                $entityId => $this->getObject($entity, $entityId),
            ],
        ];

        $changes = [];
        foreach ($resultsChanges as $row) {
            $this->processChangeRow($row, $objects, $changes);
        }
        krsort($changes);

        return $changes;
    }
    /**
     * Get list of changes from database
     * @return mixed[]
     */
    public function getChanges($maxRows = 250)
    {
        if ($cache = $this->fetchCachedEntityObjects('changes')) {
            return $cache;
        }
        $entityTypes = $this->getTableEntities();
        $gateway = $this->getChangesTableGateway();
        $select = new Select($this->changeTableName);
        $select->where(['ChangedEntity' => $entityTypes]);
        $select->order(['UpdatedOn'=>'DESC']);
        $select->limit($maxRows);
        $resultsChanges = $gateway->selectWith($select);
        $results = $resultsChanges->toArray();

        //collect list of objects to query
        $objectKeyList = [];
        foreach ($results as $key => $row) {
            $entity = $row['ChangedEntity'];
            $entityId = $row['ChangedIDValue'];
            //only bring in recognized entities from this class
            if (!isset($this->entitySpecifications[$entity]) ||
                $this->entitySpecifications[$entity]->sionModelClass !== get_class($this)
            ) {
                unset($results[$key]);
                continue;
            }
            if (!isset($objectKeyList[$entity])) {
                $objectKeyList[$entity] = [];
            }
            if (!in_array($entityId, $objectKeyList[$entity])) {
                $objectKeyList[$entity][] = $entityId;
            }
        }

        $objects = [];
        foreach ($objectKeyList as $entity => $entityIds) {
            $objects[$entity] = $this->getObjects(
                $entity, 
                [$this->entitySpecifications[$entity]->tableKey => $entityIds], 
                ['failSilently' => true]
                );
        }

        $changes = [];
        foreach ($results as $row) {
            $this->processChangeRow($row, $objects, $changes);
        }
        krsort($changes);

        $this->cacheEntityObjects('changes', $changes);
        return $changes;
    }

    /**
     * Process a row from the changes table and add it to the referenced array
     * This allows DRYs up code for processing various subsets of changes
     * @param array $row
     * @param array $changes
     */
    protected function processChangeRow($row, array &$objects, array &$changes)
    {
        static $userTable;
        $user = null;
        if ($row['UpdatedBy'] && is_numeric($row['UpdatedBy'])) {
            if (!is_object($userTable)) {
                $userTable = $this->getUserTable();
            }
            if (is_object($userTable)) {
                $user = $userTable->getUser($row['UpdatedBy']);
            } else {
                $user = [
                    'userId' => $this->filterDbId($row['UpdatedBy']),
                ];
            }
        }
        $entity = $this->filterDbString($row['ChangedEntity']);
        $entityId = $this->filterDbString($row['ChangedIDValue']);
        $updatedOn = $this->filterDbDate($row['UpdatedOn']);
        //only bring in recognized entities from this class
        if (!isset($this->entitySpecifications[$entity]) ||
            $this->entitySpecifications[$entity]->sionModelClass !== get_class($this) ||
            null === $updatedOn
        ) {
            return false;
        }
        $change = [
            'changeId'              => $this->filterDbId($row['ChangeID']),
            'entityType'            => $entity,
            'entitySpecification'   => $this->entitySpecifications[$entity],
            'entityId'              => $entityId,
            'object'                => null,
            'field'                 => $this->filterDbString($row['ChangedField']),
            'newValue'              => $row['NewValue'],
            'oldValue'              => $row['OldValue'],
            'ipAddress'             => $this->filterDbString($row['IpAddress']),
            'updatedOn'             => $updatedOn,
            'updatedBy'             => $user,

            'object'                => null,
        ];
        if (isset($objects[$entity]) && isset($objects[$entity][$entityId])) {
            $change['object'] = $objects[$entity][$entityId];
        }
        if (null === $change['object']) { //it looks like the object has been deleted, let's fill in some data
            $change['object'] = [
                'isDeleted' => true,
            ];
            if ($this->entitySpecifications[$entity]->entityKeyField) {
                $change['object'][$this->entitySpecifications[$entity]->entityKeyField] = $entityId;
            }
            if ($this->entitySpecifications[$entity]->nameField) {
                $change['object'][$this->entitySpecifications[$entity]->nameField] = ucfirst($entity).' Id: '.$entityId;
            }
        }
        //we'll sort by the key afterwards
        $key = (int)date_format($updatedOn, 'U');
        while (isset($changes[$key])) {
            ++$key;
        }
        $changes[$key] = $change;
        return true;
    }

    /**
     * Register a visit in the visits table as defined by the config
     * @param string $entity
     * @param int $entityId
     * @throws \InvalidArgumentException
     */
    public function registerVisit($entity, $entityId)
    {
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
     * Cache some entities. A simple proxy of the cache's setItem method with dependency support.
     *
     * @param string $cacheKey
     * @param mixed[] $objects
     * @param array $entityDependencies
     * @return boolean
     */
    public function cacheEntityObjects($cacheKey, &$objects, array $cacheDependencies = [])
    {
        if (!isset($this->persistentCache)) {
            throw new \Exception('The cache must be configured to cache entites.');
        }
        $cacheKey = $this->getSionTableIdentifier().'-'.$cacheKey;
        $this->memoryCache[$cacheKey] = $objects;
        $this->newPersistentCacheItems[] = $cacheKey;
        $this->cacheDependencies[$cacheKey] = $cacheDependencies;
        return true;
    }

    /**
     * Retrieve a cache item. A simple proxy of the cache's getItem method.
     * First we check the memoryCache, if it's not there, we look in the
     * persistent cache. If it's in the persistent cache, we set it in the
     * memory cache and return the objects. If we don't find the key we
     * return null.
     * @param string $key
     * @param bool $success
     * @param mixed $casToken
     * @throws \Exception
     * @return mixed|null
     */
    public function &fetchCachedEntityObjects($key, &$success = null, $casToken = null)
    {
        if (null === $this->persistentCache) {
            throw new \Exception('Please set a cache before fetching cached entities.');
        }
        $key = $this->getSionTableIdentifier().'-'.$key;
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }
        $objects = $this->persistentCache->getItem($key, $success, $casToken);
        if ($success) {
            $this->memoryCache[$key]=$objects;
            return $this->memoryCache[$key];
        }
        $null = null;
        return $null;
    }

    /**
     * Examine the $this->cacheDependencies array to see if any depends on the entity passed.
     * @param string $entity
     * @return bool
     */
    public function removeDependentCacheItems($entity)
    {
        $cache = $this->getPersistentCache();
        foreach ($this->cacheDependencies as $key => $dependentEntities) {
            if (in_array($entity, $dependentEntities) || $key == 'changes' || $key == 'problems') {
                if (is_object($cache)) {
                    $cache->removeItem($key);
                }
                if (isset($this->memoryCache[$key])) {
                    unset($this->memoryCache[$key]);
                }
            }
        }

        return true;
    }

    /**
    * Get the maxItemsToCache value
    * @return int
    */
    public function getMaxItemsToCache()
    {
        return $this->maxItemsToCache;
    }

    /**
    *
    * @param int $maxItemsToCache
    * @return self
    */
    public function setMaxItemsToCache($maxItemsToCache)
    {
        $this->maxItemsToCache = $maxItemsToCache;
        return $this;
    }

    /**
     * Get the cache value
     * @return StorageInterface
     */
    public function getPersistentCache()
    {
        return $this->persistentCache;
    }

    /**
     *
     * @param StorageInterface $cache
     * @return self
     */
    public function setPersistentCache($cache)
    {
        $this->persistentCache = $cache;
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
        if ((null !== $startDate && !$startDate instanceof \DateTime) ||
            (null !== $endDate && !$endDate instanceof \DateTime)) {
            throw new \InvalidArgumentException('Date parameters must be either DateTime instances or null.');
        }

        $text = '';
        if ((null !== $startDate && $startDate instanceof \DateTime) ||
            (null !== $endDate && $startDate instanceof \DateTime)) {
            if (null !== $startDate xor null !== $endDate) { //only one is set
                if (null !== $startDate) {
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
        if ((null !== $startDate && !$startDate instanceof \DateTime) ||
            (null !== $endDate && !$endDate instanceof \DateTime)
        ) {
            throw new \InvalidArgumentException('Invalid value passed to `areWeWithinDateRange`');
        }
        static $now;
        if (!isset($now)) {
            $timeZone = new \DateTimeZone('UTC');
            $now = new \DateTime(null, $timeZone);
        }
        return ($startDate <= $now && (null === $endDate || $endDate > $now)) ||
            (null === $startDate && ((null === $endDate || $endDate > $now)));
    }

    /**
     * Filter a database int
     * @param string $str
     * @return NULL|number
     */
    protected function filterDbId($str)
    {
        if (null === $str || $str === '' || $str == '0') {
            return null;
        }
        return (int) $str;
    }

    /**
     * Null database string
     * @param string $str
     * @return string
     */
    protected function filterDbString($str)
    {
        if ($str === '') {
            return null;
        }
        return $str;
    }

    /**
     * Filter a database int
     * @param string $str
     * @return NULL|number
     */
    protected function filterDbInt($str)
    {
        if (null === $str || $str === '') {
            return null;
        }
        return (int) $str;
    }

    /**
     * Filter a database boolean
     * @param string $str
     * @return boolean
     */
    protected function filterDbBool($str)
    {
        if (null === $str || $str === '' || $str == '0') {
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
        if (!isset($tz)) {
            $tz = new \DateTimeZone('UTC');
        }
        if (null === $str || $str === '' || $str == '0000-00-00' || $str == '0000-00-00 00:00:00') {
            return null;
        }
        try {
            $return = new \DateTime($str, $tz);
        } catch (\Exception $e) {
            $return = null;
        }
        return $return;
    }

    /**
     *
     * @param string $str
     * @return GeoPoint
     */
    protected function filterDbGeoPoint($str)
    {
        if (!isset($str)) {
            return null;
        }
        $re = '/[0-9\.-]+/u';
//         $str = 'POINT(76.2144 10.5276)';

        $matches = null;
        preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);

        // Print the entire match result
        if (!isset($matches)) {
            return null;
        }

        $longitude = isset($matches[0]) ? $matches[0][0] : 0;
        $latitude = isset($matches[1]) ? $matches[1][0] : 0;
        $obj = new GeoPoint($longitude, $latitude);
        return $obj;
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
        if (null === $str) {
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
        if (!isset($str) || $str == '') {
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

    /**
     * Check a URL and, if it is valid, return an array of format:
     * [ 'url' => 'https://...', 'label' => 'google.com']
     * @param string $str
     * @return NULL|string[]
     */
    public static function filterUrl($str, $label = null)
    {
        if (null === $str || '' === $str) {
            return null;
        }
        if ('' === $label) {
            $label = null;
        }

        $url = new Http($str);
        if ($url->isValid()) {
            if (null === $label) {
                $label = $url->getHost();
            }
            $result = ['url' => $url->toString(), 'label' => $label];
        } else {
            $result = null;
        }

        return $result;
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
    public static function strPad($input, $padLength, $padString = ' ', $padType = STR_PAD_RIGHT)
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
     * @return self
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
        if (null === $this->changesTableGateway) {
            if (null === $this->changeTableName) {
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
        if (null === $this->visitsTableGateway) {
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
