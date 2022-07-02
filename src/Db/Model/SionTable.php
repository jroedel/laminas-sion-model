<?php

declare(strict_types=1);

namespace SionModel\Db\Model;

use Closure;
use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use JUser\Model\UserTable;
use Laminas\Crypt\Hash;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\In;
use Laminas\Db\Sql\Predicate\IsNull;
use Laminas\Db\Sql\Predicate\Operator;
use Laminas\Db\Sql\Predicate\PredicateInterface;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\Filter\Boolean;
use Laminas\Log\LoggerAwareTrait;
use Laminas\Stdlib\StringUtils;
use Laminas\Uri\Http;
use Laminas\Validator\EmailAddress;
use Matriphe\ISO639\ISO639;
use Psr\Container\ContainerInterface;
use SionModel\Db\GeoPoint;
use SionModel\Entity\Entity;
use SionModel\I18n\LanguageSupport;
use SionModel\Problem\EntityProblem;
use SionModel\Problem\ProblemTable;
use SionModel\Service\EntitiesService;
use SionModel\Service\ProblemService;

use function array_key_exists;
use function array_values;
use function call_user_func_array;
use function count;
use function date_format;
use function explode;
use function floor;
use function gettype;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function krsort;
use function method_exists;
use function preg_match_all;
use function str_pad;
use function str_repeat;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function ucfirst;

use const PREG_SET_ORDER;
use const SORT_ASC;
use const SORT_DESC;
use const STR_PAD_BOTH;
use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

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
 * Ok, I just read the intro to Laminas\Permissions\Acl.  I think the solution is pretty
 * simple.
 * Table: role, resource, permission, allow/deny
 * ex: "user_77", "event_98", "read,update,delete", "allow"
 * ex: "group_editors", "events", "read,update,delete", "allow"
 * ex: "group_guests", "events", "read,update,delete", "deny"
 * Table: role, parent   This table lets us add parent roles to other roles
 * ex: "user_6", "group_kentenich_reader"
 * ex: "administrator", "moderator"
 * ex: "user_8", "administrator"
 */
/**
 * @todo Factor out 'changes_table_name' from __contruct method. Make it a required key for entities
 */
class SionTable
{
    use LoggerAwareTrait;
    use SionCacheTrait;

    public const SUGGESTION_ERROR    = 'Error';
    public const SUGGESTION_INREVIEW = 'In review';
    public const SUGGESTION_ACCEPTED = 'Accepted';
    public const SUGGESTION_DENIED   = 'Denied';

    /**
     * A cache of already created table gateways, keyed by the table name
     *
     * @var TableGateway[] $tableGatewaysCache
     */
    protected array $tableGatewaysCache = [];

    /** @var Entity[] $entitySpecifications */
    protected array $entitySpecifications = [];

    protected ?string $visitsTableName;

    protected ?TableGatewayInterface $changesTableGateway;

    protected ?TableGatewayInterface $visitsTableGateway;

    /** @var Select[] $selectPrototypes */
    protected array $selectPrototypes = [];

    /**
     * A prototype of an EntityProblem to clone
     */
    protected ?EntityProblem $entityProblemPrototype;

    protected ?UserTable $userTable;

    protected ?string $changeTableName;

    /**
     * Class to get language information
     *
     * @deprecated
     */
    protected ISO639 $iso639;

    /**
     * Class for multilingual language name support
     */
    protected LanguageSupport $languageSupport;

    /**
     * An associative array mapping 2-digit iso-639 codes to the english name of a language
     *
     * @var string[] $languageNames
     */
    protected array $languageNames;

    /**
     * An associative array mapping 2-digit iso-639 codes to the native name of a language
     *
     * @var string[] $nativeLanguageNames
     */
    protected array $nativeLanguageNames;

    /**
     * Default algorithm for hashing sensitive data
     */
    protected ?string $privacyHashAlgorithm = 'sha256';

    /**
     * Default random salt for hashing sensitive data
     */
    protected string $privacyHashSalt = '78z^PjApc';

    protected int $maxChangeTableValueStringLength = 1000;

    protected string $maxChangeTableValueStringLengthReplacementText = '<content truncated>';
    /**
     * Represents the action of updating an entity
     *
     * @var string
     */
    public const ENTITY_ACTION_UPDATE = 'entity-action-update';
    /**
     * Represents the action of creating an entity
     *
     * @var string
     */
    public const ENTITY_ACTION_CREATE = 'entity-action-create';
    /**
     * Represents the action of suggesting an edit to an entity
     *
     * @var string
     */
    public const ENTITY_ACTION_SUGGEST = 'entity-action-suggest';

    public function __construct(
        protected AdapterInterface $adapter,
        ContainerInterface $container,
        protected ?int $actingUserId
    ) {
        /**
         * @var EntitiesService $entities
         */
        $entities = $container->get(EntitiesService::class);

        $config = $container->get('SionModel\Config');

        //setup cache
        if ($container->has('SionModel\PersistentCache')) {
            $this->setPersistentCache($container->get('SionModel\PersistentCache'));

            //setup listener for onFinish, so move objects to persistent storage
            $em = $container->get('Application')->getEventManager();
            $this->wireOnFinishTrigger($em);
            if (isset($config['max_items_to_cache'])) {
                $this->setMaxItemsToCache($config['max_items_to_cache']);
            }
        }

        $this->entitySpecifications = $entities->getEntities();
        $this->changeTableName      = $config['changes_table'] ?? null;
        $this->visitsTableName      = $config['visits_table'] ?? null;

        if (isset($config['privacy_hash_algorithm']) && Hash::isSupported($config['privacy_hash_algorithm'])) {
            $this->privacyHashAlgorithm = $config['privacy_hash_algorithm'];
        } elseif (
            array_key_exists('privacy_hash_algorithm', $config)
            && null === $config['privacy_hash_algorithm']
        ) {
            $this->privacyHashAlgorithm = null;
        }

        if (isset($config['privacy_hash_salt'])) {
            $this->privacyHashSalt = $config['privacy_hash_salt'];
        }

        if (
            isset($config['max_change_table_value_string_length'])
            && is_int($config['max_change_table_value_string_length'])
        ) {
            $this->maxChangeTableValueStringLength = $config['max_change_table_value_string_length'];
        }
        //can be either string or null
        if (
            array_key_exists('max_change_table_value_string_length_replacement_text', $config)
            && (is_string($config['max_change_table_value_string_length_replacement_text'])
                || ! isset($config['max_change_table_value_string_length_replacement_text']))
        ) {
            $this->maxChangeTableValueStringLengthReplacementText =
                $config['max_change_table_value_string_length_replacement_text'];
        }

        if ($container->has('SionModel\Logger')) {
            $logger = $container->get('SionModel\Logger');
            $this->setLogger($logger);
        }

        //if we have it, use it; careful because UserTable is itself a SionTable
        if (UserTable::class !== static::class && $container->has(UserTable::class)) {
            $userTable = $container->get(UserTable::class);
            $this->setUserTable($userTable);
        }

        if (
            ! $this instanceof ProblemTable && // prevent circular dependency
            isset($config['problem_specifications']) &&
            ! empty($config['problem_specifications'])
        ) {
            /**
             * @var ProblemService $problemService
             */
            $problemService               = $container->get(ProblemService::class);
            $this->entityProblemPrototype = $problemService->getEntityProblemPrototype();
        }
    }

    /**
     * Get all records from the mailings table
     *
     * @deprecated
     *
     * @psalm-return array<int, array{
     * mailingId: null|int,
     * mailingName: string,
     * emailAddress: mixed,
     * mailingOn: DateTime,
     * mailingBy: null|int,
     * subject: null|string,
     * body: null|string,
     * sender: null|string,
     * text: null|string,
     * tags: array,
     * trackingToken: null|string,
     * openedFromIpAddress: null|string,
     * openedFromHeaders: mixed,
     * openedOn: DateTime,
     * emailTemplate: null|string,
     * emailLocale: null|string,
     * status: null|string,
     * attempt: int|null,
     * maxAttempts: int|null,
     * queueUntil: DateTime,
     * errorMessage: null|string,
     * stackTrace: null|string
     * }>
     */
    public function getMailings(): array
    {
        $sql = "SELECT `MailingId`, `ToAddresses`, `MailingOn`, `MailingBy`, `Subject`,
`Body`, `Sender`, `MailingText`, `MailingTags`, `TrackingToken`, `OpenedFromIpAddress`,
`OpenedFromHeaders`, `OpenedOn`, `EmailTemplate`, `EmailLocale`, `Status`, `QueueUntil`,
`Attempt`, `MaxAttempts`, `ErrorMessage`, `StackTrace` FROM `a_data_mailing` WHERE 1";

        $results  = $this->fetchSome(null, $sql);
        $entities = [];
        foreach ($results as $row) {
            $id            = $this->filterDbId($row['MailingId']);
            $subject       = $this->filterDbString($row['Subject']);
            $email         = $this->filterDbString($row['ToAddresses']);
            $entities[$id] = [
                'mailingId'           => $id,
                'mailingName'         => 'Mail ' . $id . ': ' . $subject,
                'emailAddress'        => $this->getEmailAddress($email),
                'mailingOn'           => $this->filterDbDate($row['MailingOn']),
                'mailingBy'           => $this->filterDbId($row['MailingBy']),
                'subject'             => $subject,
                'body'                => $this->filterDbString($row['Body']),
                'sender'              => $this->filterDbString($row['Sender']),
                'text'                => $this->filterDbString($row['MailingText']),
                'tags'                => $this->filterDbArray($row['MailingTags']),
                'trackingToken'       => $this->filterDbString($row['TrackingToken']),
                'openedFromIpAddress' => $this->filterDbString($row['OpenedFromIpAddress']),
                'openedFromHeaders'   => $row['OpenedFromHeaders'], //@todo process as JSON
                'openedOn'            => $this->filterDbDate($row['OpenedOn']),
                'emailTemplate'       => $this->filterDbString($row['EmailTemplate']),
                'emailLocale'         => $this->filterDbString($row['EmailLocale']),
                'status'              => $this->filterDbString($row['Status']),
                'attempt'             => $this->filterDbInt($row['Attempt']),
                'maxAttempts'         => $this->filterDbInt($row['MaxAttempts']),
                'queueUntil'          => $this->filterDbDate($row['QueueUntil']),
                'errorMessage'        => $this->filterDbString($row['ErrorMessage']),
                'stackTrace'          => $this->filterDbString($row['StackTrace']),
            ];
        }
        return $entities;
    }

    public function getMailing(int $id): array|null
    {
        $mailings = $this->getMailings();

        if (! isset($mailings[$id]) || ! ($mailing = $mailings[$id])) {
            return null;
        }
        return $mailing;
    }

    /**
     * Get entity data for the specified entity and id
     *
     * @throws Exception
     */
    public function getObject(string $entity, int $id, bool $failSilently = false): array|null
    {
        $entitySpec     = $this->getEntitySpecification($entity);
        $objectFunction = $entitySpec->getObjectFunction;
        if (
            ! isset($objectFunction)
            || ! method_exists($this, $objectFunction)
            || method_exists('SionTable', $objectFunction)
        ) {
            if ($failSilently) {
                try {
                    $object = $this->tryGettingObject($entity, $id);
                } catch (Exception $e) {
                    $object = null;
                }
            } else {
                $object = $this->tryGettingObject($entity, $id);
            }
            return $object;
        }
        //@todo clarify which exceptions are thrown and when.
        $entityData = $this->$objectFunction($id);
        if (! $entityData && ! $failSilently) {
            throw new InvalidArgumentException('No entity provided.');
        }
        return $entityData;
    }

    /**
     * Try making an SQL query to get an object for the given entityType and identifier
     *
     * @throws Exception
     */
    protected function tryGettingObject(string $entity, int $entityId): array|null
    {
        if (! isset($entityId)) {
            throw new Exception('Invalid entity id requested.');
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (! isset($entitySpec->tableName) || ! isset($entitySpec->tableKey)) {
            throw new Exception("Invalid entity configuration for `$entity`.");
        }
        $gateway   = $this->getTableGateway($entitySpec->tableName);
        $select    = $this->getSelectPrototype($entity);
        $predicate = new Operator($entitySpec->tableKey, Operator::OPERATOR_EQUAL_TO, $entityId);
        $select->where($predicate);
        $result = $gateway->selectWith($select);
        if (! $result instanceof ResultSetInterface) {
            throw new Exception("Unexpected query result for entity `$entity`");
        }
        $results = $result->toArray();
        if (! isset($results[0])) {
            return null;
        }

        return $this->processEntityRow($entity, $results[0]);
    }

    /**
     * @param array|PredicateInterface|PredicateInterface[] $query
     * @param array $options 'failSilently'(bool), 'orCombination'(bool), 'limit'(int), 'offset'(int), 'order'
     * @return array
     * @throws Exception
     */
    public function getObjects(string $entity, array|PredicateInterface $query = [], array $options = []): array
    {
        $entitySpec      = $this->getEntitySpecification($entity);
        $objectsFunction = $entitySpec->getObjectsFunction;
        if (
            ! isset($objectsFunction)
            || ! method_exists($this, $objectsFunction)
            || method_exists('SionTable', $objectsFunction)
        ) {
            $objects = $this->queryObjects($entity, $query, $options);
            if (! isset($objects)) {
                throw new Exception('Invalid get_objects_function set for entity \'' . $entity . '\'');
            }
            return $objects;
        }
        $objects            = $this->$objectsFunction($query, $options);
        $shouldFailSilently = isset($options['failSilently']) && (bool) $options['failSilently'];
        if (! $objects && ! $shouldFailSilently) {
            throw new InvalidArgumentException('No entity provided.');
        }
        return $objects;
    }

    /**
     * Try a query given certain parameters and options
     *
     * @param array|PredicateInterface|PredicateInterface[] $query
     * @param array $options 'failSilently'(bool), 'orCombination'(bool), 'limit'(int),
     *      'offset'(int), 'order'
     * @throws Exception
     * @return array
     */
    public function queryObjects(string $entity, array|PredicateInterface $query = [], array $options = []): array
    {
        $cacheKey = null;
        if (is_array($query) && empty($query) && empty($options)) {
            $cacheKey = 'query-objects-' . $entity;
            if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
                return $cache;
            }
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (! isset($entitySpec->tableName) || ! isset($entitySpec->tableKey)) {
            throw new Exception("There is no table or table key set for entity `$entity`");
        }
        $gateway      = $this->getTableGateway($entitySpec->tableName);
        $select       = $this->getSelectPrototype($entity);
        $fieldMap     = $entitySpec->updateColumns;
        $tableName    = $entitySpec->tableName;
        $columnPrefix = "$tableName.";

        $shouldFailSilently = isset($options['failSilently']) && $options['failSilently'];
        $combination        = isset($options['orCombination']) && $options['orCombination']
            ? PredicateSet::OP_OR
            : PredicateSet::OP_AND;

        if ($query instanceof PredicateInterface) {
            $where = $query;
        } elseif (is_array($query)) {
            $where = new Where();
            foreach ($query as $key => $value) {
                if ($value instanceof PredicateInterface) {
                    $where->addPredicate($value, $combination);
                }
                if (! isset($fieldMap[$key])) {
                    continue;
                }
                if (is_array($value)) {
                    $clause = new In($columnPrefix . $fieldMap[$key], $value);
                } elseif (null === $value) {
                    $clause = new IsNull($columnPrefix . $fieldMap[$key]);
                } else {
                    $clause = new Operator(
                        $columnPrefix . $fieldMap[$key],
                        Operator::OPERATOR_EQUAL_TO,
                        $value
                    );
                }
                $where->addPredicate($clause, $combination);
            }
        } else {
            throw new InvalidArgumentException(
                'Invalid query parameter. Should be either array or PredicateInterface'
            );
        }
        $select->where($where);
        if (isset($options['limit'])) {
            $select->limit($options['limit']);
        }
        if (isset($options['offset'])) {
            $select->offset($options['offset']);
        }
        if (isset($options['having'])) {
            $select->having($options['having']);
        }
        //@todo map field to column name, think about this well
        if (isset($options['order'])) {
//             if (is_array($options['order'])
//                 && (!isset($options['noOrderFieldMapping']) || !$options['noOrderFieldMapping'])
//             ) {
//                 $order = [];
//                 foreach ($ as $key => $value) {
//                     ;
//                 }
//             } else {
                //first clear any ordering already setup in the select
                $select->reset('order');
                $select->order($options['order']);
//             }
        }

        $prefixColumnsWithTable = true;
        if (isset($options['prefixColumnsWithTable'])) {
            $prefixColumnsWithTable = $options['prefixColumnsWithTable'];
        }
        if (isset($options['columns'])) {
            $select->columns($options['columns'], $prefixColumnsWithTable);
        } elseif (isset($options['fields']) && is_array($options['fields'])) {
            $columns = [];
            foreach ($options['fields'] as $key => $value) {
                $fieldName = $value;
                $aliasName = is_int($key) ? $key : null;
                if (! isset($fieldMap[$fieldName])) {
                    throw new Exception('Unknown field name');
                }
                $column = $fieldMap[$fieldName];
                if (isset($aliasName)) {
                    $columns[$aliasName] = $column;
                } else {
                    $columns[] = $column;
                }
            }
            $select->columns($columns, $prefixColumnsWithTable);
        }

        $result = $gateway->selectWith($select);
        if (! $result instanceof ResultSetInterface) {
            if ($shouldFailSilently) {
                return [];
            } else {
                $type = gettype($result);
                if ('object' === $type) {
                    $type = $result::class;
                }
                throw new Exception("Unexpected query result of type `$type`");
            }
        }
        $results = $result->toArray();
        $objects = [];
        foreach ($results as $row) {
            $data = $this->processEntityRow($entity, $row);
            $id   = $data[$entitySpec->entityKeyField] ?? null;
            //make sure we don't overwrite other entries
            if (isset($id) && ! isset($objects[$id])) {
                $objects[$id] = $data;
            } else {
                $objects[] = $data;
            }
        }

        if (isset($cacheKey)) {
            $dependencies = $entitySpec->dependsOnEntities;
            if (! in_array($entity, $dependencies, true)) {
                $dependencies[] = $entity;
            }
            $this->cacheEntityObjects($cacheKey, $objects, $dependencies);
        }
        return $objects;
    }

    /**
     * Process an SQL-returned row, mapping column names to our field names
     *
     * @param array $row
     * @return array
     * @throws Exception
     */
    protected function processEntityRow(string $entity, array $row): array
    {
        //this variable maps entity types to rowProcessorFunction names... for performance
        static $entityRowFunctionCache;
        $entitySpec = null;

        //first figure out if we have a processing function or not
        if (! isset($entityRowFunctionCache[$entity])) {
            $entitySpec = $this->getEntitySpecification($entity);

            if (
                isset($entitySpec->rowProcessorFunction)
                && method_exists($this, $entitySpec->rowProcessorFunction)
                && ! method_exists('SionTable', $entitySpec->rowProcessorFunction)
            ) {
                $entityRowFunctionCache[$entity] = $entitySpec->rowProcessorFunction;
            } else {
                $entityRowFunctionCache[$entity] = false;
            }
        }

        //then actually process the row
        if (false !== $entityRowFunctionCache[$entity]) {
            $processor = $entityRowFunctionCache[$entity];
            return $this->$processor($row);
        } else {
            if (! isset($entitySpec)) {
                $entitySpec = $this->getEntitySpecification($entity);
            }
            $columnsMap = $entitySpec->updateColumns;
            $data       = [];
            foreach ($row as $column => $value) {
                if (isset($columnsMap[$column])) {
                    //@todo add some generic filter here to convert datetimes
                    $data[$columnsMap[$column]] = $value;
                }
            }
        }
        return $data;
    }

    /**
     * Get a standardized select object to retrieve records from the database
     */
    protected function getSelectPrototype(string $entity): Select
    {
        if (isset($this->selectPrototypes[$entity])) {
            return clone $this->selectPrototypes[$entity];
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (! isset($entitySpec->tableName) || empty($entitySpec->updateColumns)) {
            throw new Exception("Cannot construct select prototype for `$entity`");
        }
        $select = new Select($entitySpec->tableName);
        $select->columns(array_values($entitySpec->updateColumns));
        //@todo maybe add an order config to entity specs
        $this->selectPrototypes[$entity] = $select;
        return clone $select;
    }

    /**
     * Query visit counts for a particular entity. The ids to aggregate can be
     * specified by the 2nd parameter. The options array is reserved for adding
     * further aggregate types in the future.
     * An associative array keyed on the entityId is returned containing at least
     * elements 'total' and 'pastMonth'
     *
     * @param array $ids
     * @param array $options
     * @return int[][]
     * @psalm-return array<array{total: int, pastMonth: int}>
     */
    public function getVisitCounts(string $entity, array $ids = [], array $options = []): array
    {
        $counts  = [];
        $gateway = $this->getVisitTableGateway();

        //query the total values
        $select = new Select($this->visitsTableName);
        $select->columns([
            'EntityId',
            'TotalVisits' => new Expression('COUNT(*)'),
        ]);
        $where = new PredicateSet([new Operator('Entity', Operator::OPERATOR_EQUAL_TO, $entity)]);
        if (! empty($ids)) {
            $where->addPredicate(new In('EntityId', $ids));
        }
        $select->where($where)
        ->group(['EntityId']);
        $result = $gateway->selectWith($select);
        foreach ($result as $row) {
            if (! isset($row['EntityId'])) {
                continue;
            }
            if (! isset($counts[$row['EntityId']])) {
                $counts[$row['EntityId']] = [
//                    'total'     => 0, this value is written immediately after
                    'pastMonth' => 0,
                ];
            }
            $counts[$row['EntityId']]['total'] = (int) $row['TotalVisits'];
        }

        //query the pastMonth values
        $select = new Select($this->visitsTableName);
        $select->columns([
            'EntityId',
            'PastMonthVisits' => new Expression('COUNT(*)'),
        ]);
        $where->addPredicate(
            new \Laminas\Db\Sql\Predicate\Expression('`VisitedAt` >= DATE_ADD(NOW(), INTERVAL -1 MONTH)')
        );
        $select->where($where)
        ->group(['EntityId']);
        $result = $gateway->selectWith($select);
        foreach ($result as $row) {
            if (! isset($row['EntityId'])) {
                continue;
            }
            if (! isset($counts[$row['EntityId']])) {
                $counts[$row['EntityId']] = [
                    'total' => 0,
//                    'pastMonth' => 0,
                ];
            }
            $counts[$row['EntityId']]['pastMonth'] = (int) $row['PastMonthVisits'];
        }

        return $counts;
    }

    /**
     * Sort an entity object
     *
     * @param array $array
     * @param array $fieldsAndSortParameter ex. ['name' => SORT_ASC, 'country' = SORT_DESC]
     */
    public static function sortArrayOfEntities(array &$array, array $fieldsAndSortParameter): void
    {
        foreach ($fieldsAndSortParameter as $field => $sortParameter) {
            if (
                ! is_string($field) || ($sortParameter !== SORT_ASC &&
                $sortParameter !== SORT_DESC)
            ) {
                throw new InvalidArgumentException(
                    'The $fieldsAndSortParameter should be in format [\'name\' => SORT_ASC, \'country\' = SORT_DESC]'
                );
            }
        }

        $sort = [];
        foreach ($array as $k => $v) {
            foreach ($fieldsAndSortParameter as $field => $sortParameter) {
                $sort[$field][$k] = $v[$field];
            }
        }
        // sort by event_type desc and then title asc
        $params = [];
        foreach ($fieldsAndSortParameter as $field => $sortParameter) {
            $params[] = $sort[$field];
            $params[] = $sortParameter;
        }
        $params[] = &$array;
        call_user_func_array('array_multisort', $params);
    }

    /**
     * @param array|string|Closure|Where $where
     * @param null|string
     * @param null|array
     * @param null|numeric[] $sqlArgs
     * @return array
     * @psalm-param array{0: numeric}|null $sqlArgs
     * @psalm-return list<mixed>
     */
    public function fetchSome(
        Where|array|string|Closure|null $where,
        string|null $sql = null,
        array|null $sqlArgs = null
    ): array {
        if (null === $where && null === $sql) {
            throw new InvalidArgumentException('No query requested.');
        }
        if (null !== $sql) {
            if (null === $sqlArgs) {
                $sqlArgs = Adapter::QUERY_MODE_EXECUTE; //make sure query executes
            }
            $result = $this->getAdapter()->query($sql, $sqlArgs);
        } else {
            //@todo somehow we need to get a table gateway that's not arbitrary
            $result = $this->changesTableGateway->select($where);
        }

        $return = [];
        foreach ($result as $row) {
            $return[] = $row;
        }
        return $return;
    }

    /**
     * Validate urls and configure their labels
     *
     * @param string[] $unprocessedUrls Should be a 2-dimensional array, each element containing a 'url' and 'label' key
     * @return ((null|string)[]|string)[]|null
     * @throws InvalidArgumentException
     * @todo check URL against Google Safe Browsing
     * @psalm-return list<array{label: null|string, url: string}|string>|null
     */
    public static function processUrls(array $unprocessedUrls): array|null
    {
        if (null === $unprocessedUrls) {
            return null;
        }
        $urls = [];
        foreach ($unprocessedUrls as $urlRow) {
            if (
                ! is_array($urlRow)
                || ! array_key_exists('url', $urlRow)
                || ! array_key_exists('label', $urlRow)
            ) {
                throw new InvalidArgumentException(
                    'Each element of unprocessedUrls must contain keys \'url\' and \'label\''
                );
            }
            if (null !== $urlRow['url']) {
                $url = new Http($urlRow['url']);
                if ($url->isValid()) {
                    if (null === $urlRow['label'] || 0 === strlen($urlRow['label'])) {
                        $urlRow['label'] = $url->getHost();
                    }
                    $urlRow['url'] = $url->toString();
                    $urls[]        = $urlRow;
                }
            }
        }
        return $urls;
    }

    /**
     * Validate urls and configure their labels
     *
     * @param string[] $unprocessedUrls Should be a 2-dimensional array, each element containing a 'url' and 'label' key
     * @param array $unacceptedLabels
     * @throws InvalidArgumentException
     * @return string[]|null
     * @todo check URL against Google Safe Browsing
     */
    public static function processJsonUrls(array $unprocessedUrls, array $unacceptedLabels = [])
    {
        $lowerUnacceptedLabels = [];
        foreach ($unacceptedLabels as $value) {
            if (is_string($value)) {
                $lowerUnacceptedLabels[] = strtolower($value);
            }
        }

        $urls = [];
        foreach ($unprocessedUrls as $urlRow) {
            if (! is_array($urlRow) || ! array_key_exists('url', $urlRow)) {
                throw new InvalidArgumentException('Each element of unprocessedUrls must contain key \'url\'');
            }
            if (
                isset($urlRow['label'])
                && in_array(strtolower($urlRow['label']), $lowerUnacceptedLabels, true)
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
        }
        return $urls;
    }

    /**
     * Return the EntitySpec for a given $entity string identifier
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function getEntitySpecification(string $entity): Entity
    {
        if (empty($this->entitySpecifications)) {
            throw new Exception('No entity specifications are loaded. Please see sionmodel.global.php.dist');
        }
        if (! isset($this->entitySpecifications[$entity])) {
            throw new InvalidArgumentException('The request entity was not found. \'' . $entity . '\'');
        }
        return $this->entitySpecifications[$entity];
    }

    /**
     * Make sure entity type is prepared for update/create
     *
     * @throws Exception
     */
    public function isReadyToUpdateAndCreate(string $entity): bool
    {
        $entitySpec = $this->getEntitySpecification($entity);
        if (! $entitySpec->isEnabledForUpdateAndCreate()) {
            throw new Exception('The following config keys are required to update entity \''
                . '\': table_name, table_key, update_columns');
        }
        if (
            isset($this->entitySpecifications[$entity]->getObjectFunction)
            && (! method_exists($this, $this->entitySpecifications[$entity]->getObjectFunction)
                || method_exists('SionTable', $this->entitySpecifications[$entity]->getObjectFunction))
        ) {
            throw new Exception(
                '\'get_object_function\' configuration for entity \'' . $entity
                . '\' refers to a function that doesn\'t exist'
            );
        }
        if (
            isset($this->entitySpecifications[$entity]->databaseBoundDataPreprocessor) &&
            null !== $this->entitySpecifications[$entity]->databaseBoundDataPreprocessor &&
            ! method_exists($this, $this->entitySpecifications[$entity]->databaseBoundDataPreprocessor)
        ) {
            throw new Exception(
                '\'databaseBoundDataPreprocessor\' configuration for entity \'' . $entity
                . '\' refers to a function that doesn\'t exist'
            );
        }
        return true;
    }

    /**
     * Simply incur an update of the 'updatedOn' field of an entity.
     * If a  field is indicated, it may incur a field-specific updatedOn update.
     *
     * @throws InvalidArgumentException|Exception
     */
    public function touchEntity(string $entity, int $id, ?string $field = null, bool $refreshCache = true)
    {
        if (! $this->existsEntity($entity, $id)) {
            throw new InvalidArgumentException('Entity doesn\'t exist.');
        }
        $entitySpec = $this->getEntitySpecification($entity);
        if (null === $field && null !== $entitySpec->entityKeyField) {
            throw new InvalidArgumentException(
                "Please specify the entity_key_field configuration for entity '$entity' "
                . "to use the touchEntity function."
            );
        }
        if (null !== $field && ! is_array($field)) {
            $field = [$field];
        }
        $fields = $field ?? [$entitySpec->entityKeyField];
        return $this->updateEntity($entity, $id, [], $fields, $refreshCache);
    }

    /**
     * @throws InvalidArgumentException
     * @todo group $fieldsToTouch, $refreshCache into $options and add an option to not registerChange
     */
    public function updateEntity(
        string $entity,
        int $id,
        array $data,
        array $fieldsToTouch = [],
        bool $refreshCache = true
    ): array {
        if (! $this->isReadyToUpdateAndCreate($entity)) {
            throw new InvalidArgumentException('Improper configuration for entity \'' . $entity . '\'');
        }
        $entitySpec   = $this->getEntitySpecification($entity);
        $tableName    = $entitySpec->tableName;
        $tableKey     = $entitySpec->tableKey;
        $tableGateway = new TableGateway($tableName, $this->adapter);

        $entityData             = $this->getObject($entity, $id);
        $updateCols             = $entitySpec->updateColumns;
        $manyToOneUpdateColumns = $entitySpec->manyToOneUpdateColumns ?? null;
        $reportChanges          = $entitySpec->reportChanges ?? false;

        /*
         * Run the new/old data through the preprocessor function if it exists
         */
        if (
            isset($entitySpec->databaseBoundDataPreprocessor) &&
            method_exists($this, $entitySpec->databaseBoundDataPreprocessor) &&
            ! method_exists('SionTable', $entitySpec->databaseBoundDataPreprocessor) //make sure noone's being sneaky)
        ) {
            $preprocessor     = $entitySpec->databaseBoundDataPreprocessor;
            $preprocessedData = $this->$preprocessor($data, $entityData, self::ENTITY_ACTION_UPDATE);
            if (is_array($preprocessedData)) {
                $data = $preprocessedData;
            }
        }
        $this->updateHelper(
            $id,
            $data,
            $entity,
            $tableKey,
            $tableGateway,
            $updateCols,
            $entityData,
            $manyToOneUpdateColumns,
            $reportChanges,
            $fieldsToTouch
        );

        /*
         * This is a little early to flush the cache, because there may be more changes
         * in the postprocessor, but we'll do it just in case the user has registered
         * some cache item that needs to be updated before we return the new entity data to
         * the user.
         * This shouldn't cause problems because usually a getObject call doesn't result in
         * a cache set.
         * @todo When we implement an options array, we should add an option to control this
         * potentially resource-heavy operation
         */
        if ($refreshCache) {
            $this->removeDependentCacheItems($entity);
        }

        //if the id is being changed, update it
        $keyField = $entitySpec->entityKeyField;
        if (
            isset($data[$keyField]) &&
            isset($entityData[$keyField]) &&
            $data[$keyField] !== $entityData[$keyField]
        ) {
            $id = $data[$keyField];
        }

        $newEntityData = $this->getObject($entity, $id);

        /*
         * Run the changed/new data through the preprocessor function if it exists
         */
        if (
            isset($entitySpec->databaseBoundDataPostprocessor) &&
            method_exists($this, $entitySpec->databaseBoundDataPostprocessor) &&
            ! method_exists('SionTable', $entitySpec->databaseBoundDataPostprocessor) //make sure noone's being sneaky
        ) {
            $postprocessor = $entitySpec->databaseBoundDataPostprocessor;
            $this->$postprocessor($data, $newEntityData, self::ENTITY_ACTION_UPDATE);
        }
        if ($refreshCache) {
            $this->removeDependentCacheItems($entity);
        }

        return $newEntityData;
    }

    /**
     * @param array $data New data
     * @param string $entityType Name of the entity type
     * @param string $tableKey Name of the field where the primary key will be found
     * @param array $updateCols An associative array mapping the data array key to the name of the database column
     * @param array $referenceEntity Old data
     * @param array $manyToOneUpdateColumns List of columns that represent an aspect of the entity
     * @param bool $reportChanges Create a record in the changes table?
     * @param array $fieldsToTouch Change the UpdatedOn property associated with field(s)
     * @return bool TRUE if row(s) were affected, FALSE if not
     * @throws Exception
     */
    protected function updateHelper(
        int $id,
        array $data,
        string $entityType,
        string $tableKey,
        TableGatewayInterface $tableGateway,
        array $updateCols,
        array $referenceEntity,
        array $manyToOneUpdateColumns = [],
        bool $reportChanges = false,
        array $fieldsToTouch = []
    ): bool {
        if ($entityType === '') {
            throw new Exception('No entity provided.');
        }
        if ($tableKey === '') {
            throw new Exception('No table key provided');
        }
        $now        = (new DateTime("now", new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $updateVals = [];
        $changes    = [];
        foreach ($referenceEntity as $field => $value) {
            $tempNewValue = null;
            if (
                ! in_array($field, $fieldsToTouch, true)
                && (
                    ! array_key_exists($field, $updateCols)
                    || ! array_key_exists($field, $data)
                    || $value === $data[$field]
                )
            ) {
                continue;
            } elseif (in_array($field, $fieldsToTouch, true) && ! array_key_exists($field, $data)) {
                $data[$field] = $value;
            }
            if ($data[$field] instanceof DateTime) { //convert Date objects to strings
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

            $fieldUpdatedOn = $field . 'UpdatedOn';
            if (
                array_key_exists($fieldUpdatedOn, $updateCols)
                && ! array_key_exists($fieldUpdatedOn, $data) //check if this column has updatedOn column
            ) {
                $updateVals[$updateCols[$fieldUpdatedOn]] = $now;
            }

            $fieldUpdatedBy = $field . 'UpdatedBy';
            if (
                array_key_exists($fieldUpdatedBy, $updateCols)
                && ! array_key_exists($fieldUpdatedBy, $data)
                && null !== $this->actingUserId
            ) { //check if this column has updatedBy column
                $updateVals[$updateCols[$fieldUpdatedBy]] = $this->actingUserId;
            }
            if (is_array($manyToOneUpdateColumns) && isset($manyToOneUpdateColumns[$field])) {
                if (
                    array_key_exists($manyToOneUpdateColumns[$field] . 'UpdatedOn', $updateCols) &&
                    ! array_key_exists($manyToOneUpdateColumns[$field] . 'UpdatedOn', $data)
                ) { //check if this column maps to some other updatedOn column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$field] . 'UpdatedOn']] = $now;
                }
                if (
                    array_key_exists($manyToOneUpdateColumns[$field] . 'UpdatedBy', $updateCols) &&
                    ! array_key_exists($manyToOneUpdateColumns[$field] . 'UpdatedBy', $data) &&
                    null !== $this->actingUserId
                ) { //check if this column  maps to some other updatedBy column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$field] . 'UpdatedBy']] = $this->actingUserId;
                }
            }

            $changes[] = [
                'entity'   => $entityType,
                'field'    => $field,
                'id'       => $id,
                'oldValue' => $value,
                'newValue' => $tempNewValue ?? $data[$field],
            ];
        }

        if (count($updateVals) > 0) {
            if (isset($updateCols['updatedOn']) && ! isset($updateVals[$updateCols['updatedOn']])) {
                $updateVals[$updateCols['updatedOn']] = $now;
            }
            if (
                isset($updateCols['updatedBy']) && ! isset($updateVals[$updateCols['updatedBy']]) &&
                isset($this->actingUserId)
            ) {
                $updateVals[$updateCols['updatedBy']] = $this->actingUserId;
            }
            //@todo shouldn't we try to catch an error and log it?
            //returns the number of affected rows
            $rowsAffected = $tableGateway->update($updateVals, [$tableKey => $id]);
            if ($rowsAffected > 0 && $reportChanges) {
                $this->reportChange($changes);
            }
            if ($rowsAffected > 1) { //we expect that there be exactly one or zero row affected
                $table = $tableGateway->getTable();
                throw new Exception(
                    "We expect no more than one row to be affected by an updateEntity call. "
                    . "$rowsAffected were affected modifying key `$id` in `$table`"
                );
            }
            return (bool) $rowsAffected;
        }
        return false;
    }

    public function createEntity(string $entity, array $data, bool $refreshCache = true): int
    {
        if (! $this->isReadyToUpdateAndCreate($entity)) {
            throw new InvalidArgumentException('Improper configuration for entity \'' . $entity . '\'');
        }
        $entitySpec             = $this->getEntitySpecification($entity);
        $tableName              = $entitySpec->tableName;
        $tableGateway           = $this->getTableGateway($tableName);
        $requiredCols           = $entitySpec->requiredColumnsForCreation;
        $updateCols             = $entitySpec->updateColumns;
        $manyToOneUpdateColumns = $entitySpec->manyToOneUpdateColumns;
        $reportChanges          = $entitySpec->reportChanges;

        /**
         * Run the changed/new data through the preprocessor function if it exists
         *
         * @see Entity
         */
        if (null !== $preprocessor = $entitySpec->databaseBoundDataPreprocessor) {
            $preprocessedData = $this->$preprocessor($data, [], self::ENTITY_ACTION_CREATE);
            if (is_array($preprocessedData)) {
                $data = $preprocessedData;
            }
        }

        $return = $this->createHelper(
            $data,
            $requiredCols,
            $updateCols,
            $entity,
            $tableGateway,
            $manyToOneUpdateColumns,
            $reportChanges
        );

        if ($refreshCache) {
            $this->removeDependentCacheItems($entity);
        }

        /**
         * Run the changed/new data through the postprocessor function if it exists
         *
         * @see Entity
         *
         * @todo Code for the case of no AUTO_INCREMENT (look for the $tableKey set in the $data)
         */
        if (
            isset($return) && //$return should be the AUTO_INCREMENT value of the inserted row
            isset($entitySpec->databaseBoundDataPostprocessor)
        ) {
            $newEntityData = $this->getObject($entity, $return);
            $postprocessor = $entitySpec->databaseBoundDataPostprocessor;
            $this->$postprocessor($data, $newEntityData, self::ENTITY_ACTION_CREATE);
        }

        return $return;
    }

    protected function createHelper(
        array $data,
        array $requiredCols,
        array $updateCols,
        string $entityType,
        TableGatewayInterface $tableGateway,
        array $manyToOneUpdateColumns = [],
        bool $reportChanges = false
    ): int|null {
        //make sure required cols are being passed
        foreach ($requiredCols as $colName) {
            if (! isset($data[$colName])) {
                throw new InvalidArgumentException(
                    "Not all required fields for the creation of an entity were provided. Missing `$colName`"
                );
            }
        }

        $now        = (new DateTime("now", new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $updateVals = [];
        foreach ($data as $col => $value) {
            if (! isset($updateCols[$col])) {
                continue;
            }
            if ($value instanceof DateTime) {
                $data[$col] = $value->format('Y-m-d H:i:s');
            }
            if (is_array($data[$col])) {
                $data[$col] = $this->formatDbArray($data[$col]);
            }
            if ($data[$col] instanceof GeoPoint) {
                $data[$col] = new Expression($data[$col]->getDatabaseInsertString());
            }
            $updateVals[$updateCols[$col]] = $data[$col];
            //check if this column has updatedOn column
            if (null !== $value && isset($updateCols[$col . 'UpdatedOn']) && ! isset($data[$col . 'UpdatedOn'])) {
                $updateVals[$updateCols[$col . 'UpdatedOn']] = $now;
            }
            if (
                null !== $value
                && isset($updateCols[$col . 'UpdatedBy'])
                && ! isset($data[$col . 'UpdatedBy'])
                && null !== $this->actingUserId
            ) { //check if this column has updatedOn column
                $updateVals[$updateCols[$col . 'UpdatedBy']] = $this->actingUserId;
            }
            if (null !== $value && is_array($manyToOneUpdateColumns) && isset($manyToOneUpdateColumns[$col])) {
                if (
                    isset($updateCols[$manyToOneUpdateColumns[$col] . 'UpdatedOn'])
                    && ! isset($data[$manyToOneUpdateColumns[$col] . 'UpdatedOn'])
                ) { //check if this column maps to some other updatedOn column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$col] . 'UpdatedOn']] = $now;
                }
                if (
                    isset($updateCols[$manyToOneUpdateColumns[$col] . 'UpdatedBy'])
                    && ! isset($data[$manyToOneUpdateColumns[$col] . 'UpdatedBy'])
                    && null !== $this->actingUserId
                ) { //check if this column  maps to some other updatedBy column
                    $updateVals[$updateCols[$manyToOneUpdateColumns[$col] . 'UpdatedBy']] = $this->actingUserId;
                }
            }
        }
        if (isset($updateCols['updatedOn']) && ! isset($updateVals[$updateCols['updatedOn']])) {
            $updateVals[$updateCols['updatedOn']] = $now;
        }
        if (
            isset($updateCols['updatedBy']) && ! isset($updateVals[$updateCols['updatedBy']]) &&
            null !== $this->actingUserId
        ) {
            $updateVals[$updateCols['updatedBy']] = $this->actingUserId;
        }
        //check if this column has updatedOn column
        if (isset($updateCols['createdOn']) && ! isset($data['createdOn'])) {
            $updateVals[$updateCols['createdOn']] = $now;
        }
        if (
            isset($updateCols['createdBy']) && ! isset($data['createdBy']) &&
            null !== $this->actingUserId
        ) { //check if this column has updatedOn column
            $updateVals[$updateCols['createdBy']] = $this->actingUserId;
        }
        if (count($updateVals) > 0) {
            $rowsAffected = $tableGateway->insert($updateVals);
            if ($rowsAffected === 0 || $rowsAffected > 1) {
                $table = $tableGateway->getTable();
                throw new Exception(
                    "Expected 1 row affected from createEntity, $rowsAffected affected inserting into `$table`"
                );
            }
            $newId      = $tableGateway->getLastInsertValue();
            $changeVals = [
                [
                    'entity' => $entityType,
                    'field'  => 'newEntry',
                    'id'     => $newId,
                ],
            ];
            if ($reportChanges) {
                $this->reportChange($changeVals);
            }
            return $newId;
        }
        return null;
    }

    /**
     * Get an instance of a TableGateway for a particular table name
     */
    protected function getTableGateway(string $tableName): TableGatewayInterface
    {
        if (isset($this->tableGatewaysCache[$tableName])) {
            return $this->tableGatewaysCache[$tableName];
        }
        $gateway = new TableGateway($tableName, $this->getAdapter());
        //@todo is there a way to make sure the table exists?
        return $this->tableGatewaysCache[$tableName] = $gateway;
    }

    /**
     * Get a TableGateway instance for a given entity name
     *
     * @throws Exception
     */
    protected function getTableGatewayForEntity(string $entity): TableGatewayInterface
    {
        if (! isset($this->entitySpecifications[$entity])) {
            throw new Exception("Entity not found: `$entity`");
        }
        $spec = $this->entitySpecifications[$entity];
        if (! isset($spec->tableName)) {
            throw new Exception("Table not found for entity `$entity`");
        }
        return $this->getTableGateway($spec->tableName);
    }

    /**
     * Check if an entity exists
     *
     * @throws Exception
     */
    public function existsEntity(string $entity, int $id): bool
    {
        $entitySpec = $this->getEntitySpecification($entity);
        $tableName  = $entitySpec->tableName;
        $tableKey   = $entitySpec->tableKey;
        $gateway    = $this->getTableGateway($tableName);
        $result     = $gateway->select([$tableKey => $id]);
        if (! $result instanceof ResultSet || 0 === $result->count()) {
            return false;
        }
        if ($result->count() > 1) {
            throw new Exception(
                'Improper primary key configuration of entity \'' . $entity . '\'. Multiple records returned.'
            );
        }
        return true;
    }

    /**
     * Similar to the existsEntity function, this one checks for the existence of
     * several objects, by id and returns an associative array mapping $id => bool
     *
     * @param int[] $ids
     * @return bool[]
     * @psalm-return array<array-key|int, bool>
     * @throws Exception
     */
    public function existsEntities(string $entity, array $ids): array
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
        $gateway = $this->getTableGateway($tableName);
        $result  = $gateway->select([$tableKey => $ids]);
        if (! $result instanceof ResultSet) {
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
     *
     * @throws Exception
     */
    public function deleteEntity(string $entity, int $id, bool $refreshCache = true): void
    {
        $entitySpec = $this->getEntitySpecification($entity);
        //make sure we have enough information to delete
        if (! $entitySpec->isEnabledForEntityDelete()) {
            throw new Exception('Entity \'' . $entity . '\' is not configured for deleting.');
        }
        //make sure entity exists before attempting to delete
        if (! $this->existsEntity($entity, $id)) {
            throw new Exception('The requested entity for deletion ' . $entity . $id . ' does not exist.');
        }
        $tableName    = $entitySpec->tableName;
        $tableKey     = $entitySpec->tableKey;
        $gateway      = $this->getTableGateway($tableName);
        $rowsAffected = $gateway->delete([$tableKey => $id]);

        if ($rowsAffected !== 1) {
            throw new Exception('Delete action expected \'1\' affected row, received \'' . $rowsAffected . '\'');
        }

        if ($entitySpec->reportChanges) {
            $changeVals = [
                [
                    'entity' => $entity,
                    'field'  => 'entryDeleted',
                    'id'     => $id,
                ],
            ];
            $this->reportChange($changeVals);
        }

        if ($refreshCache) {
            $this->removeDependentCacheItems($entity);
        }
    }

    /**
     * Takes an array of associative arrays containing reports of changed columns.
     * Keys are table(req), field(req),  id(req), newValue, oldValue.
     *
     * @todo include the UserAgent
     */
    public function reportChange(array $data): int
    {
        $changesTableGateway = $this->getChangesTableGateway();
        if (! $changesTableGateway instanceof TableGatewayInterface) {
            return -1;
        }
        $i    = 0;
        $date = new DateTime("now", new DateTimeZone('utc'));
        foreach ($data as $row) {
            if (isset($row['entity']) && isset($row['field']) && isset($row['id'])) {
                if (isset($row['oldValue']) && $row['oldValue'] instanceof DateTime) {
                    $row['oldValue'] = $this->formatDbDate($row['oldValue']);
                }
                if (isset($row['newValue']) && $row['newValue'] instanceof DateTime) {
                    $row['newValue'] = $this->formatDbDate($row['newValue']);
                }
                if (isset($row['oldValue']) && is_array($row['oldValue'])) {
                    $row['oldValue'] = $this->formatDbArray($row['oldValue']);
                }
                if (isset($row['newValue']) && is_array($row['newValue'])) {
                    $row['newValue'] = $this->formatDbArray($row['newValue']);
                }
                //if the value is too long, don't insert it.
                if (
                    isset($row['oldValue'])
                    && is_string($row['oldValue'])
                    && strlen($row['oldValue']) > $this->maxChangeTableValueStringLength
                ) {
                    if (isset($this->maxChangeTableValueStringLengthReplacementText)) {
                        $row['oldValue'] = $this->maxChangeTableValueStringLengthReplacementText;
                    } else {
                        unset($row['oldValue']);
                    }
                }
                if (
                    isset($row['newValue'])
                    && is_string($row['newValue'])
                    && strlen($row['newValue']) > $this->maxChangeTableValueStringLength
                ) {
                    if (isset($this->maxChangeTableValueStringLengthReplacementText)) {
                        $row['newValue'] = $this->maxChangeTableValueStringLengthReplacementText;
                    } else {
                        unset($row['newValue']);
                    }
                }
                $params = [
                    'ChangedEntity'  => $row['entity'],
                    'ChangedField'   => $row['field'],
                    'ChangedIDValue' => $row['id'],
                    'NewValue'       => $row['newValue'] ?? null,
                    'OldValue'       => $row['oldValue'] ?? null,
                    'UpdatedOn'      => $date->format('Y-m-d H:i:s'),
                    'UpdatedBy'      => $this->actingUserId,
                    'IpAddress'      => $_SERVER['REMOTE_ADDR'], //@todo there should be a better way to do this
                ];
                $changesTableGateway->insert($params);
                $i++;
            }
        }

        return $i;
    }

    /**
     * @return (int|null)[]
     * @psalm-return array<numeric-string, int|null>
     */
    public function getChangesCountPerMonth(): array
    {
        $tableEntities = $this->getTableEntities();
        $predicate     = new Where();
        $gateway       = $this->getChangesTableGateway();
        $select        = new Select($this->changeTableName);
        $select->columns([
            'TheMonth' => new Expression('MONTH(`UpdatedOn`)'),
            'TheYear'  => new Expression('YEAR(`UpdatedOn`)'),
            'Count'    => new Expression('Count(*)'),
        ]);
        $select->group(['TheMonth', 'TheYear']);
        $select->where($predicate->in('ChangedEntity', $tableEntities));
        $select->order('TheYear, TheMonth');
        $resultsChanges = $gateway->selectWith($select);
        $months         = [];
        foreach ($resultsChanges as $row) {
            if (
                is_numeric($row['TheMonth']) && $row['TheMonth'] > 0 && $row['TheMonth'] <= 12 &&
                is_numeric($row['TheYear']) && $row['TheYear'] >= 2015 && $row['TheYear'] <= 2050 &&
                is_numeric($row['Count'])
            ) {
                $key          = (string) ($row['TheYear'] * 100 + $row['TheMonth']);
                $months[$key] = $this->filterDbInt($row['Count']);
            }
        }
        return $months;
    }

    /**
     * Get the list of entities registered to this particular SionTable.
     * The entity spec must specify the 'sion_model_class' option.
     *
     * @return string[]
     */
    public function getTableEntities(): array
    {
        $tableEntities = [];
        foreach ($this->entitySpecifications as $key => $entitySpec) {
            if ($entitySpec->sionModelClass === $this::class) {
                $entityName      = null !== $entitySpec->name ? $entitySpec->name : $key;
                $tableEntities[] = $entityName;
            }
        }
        return $tableEntities;
    }

    /**
     * Get changes for a particular entity
     */
    public function getEntityChanges(string $entity, int $entityId): array
    {
        $gateway = $this->getTableGateway($this->changeTableName);
        $select  = new Select($this->changeTableName);
        $select->where([
            'ChangedEntity'  => $entity,
            'ChangedIDValue' => $entityId,
        ]);
        $select->order(['UpdatedOn' => 'DESC']);
        $resultsChanges = $gateway->selectWith($select);
        $objects        = [
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
     */
    public function getChanges(int $maxRows = 250): array
    {
        $entityTypes = $this->getTableEntities();
        $gateway     = $this->getTableGateway($this->changeTableName);
        $select      = new Select($this->changeTableName);
        $select->where(['ChangedEntity' => $entityTypes]);
        $select->order(['UpdatedOn' => 'DESC']);
        $select->limit($maxRows);
        $resultsChanges = $gateway->selectWith($select);
        $results        = $resultsChanges->toArray();

        //collect list of objects to query
        $objectKeyList = [];
        foreach ($results as $key => $row) {
            $entity   = $row['ChangedEntity'];
            $entityId = $row['ChangedIDValue'];
            //only bring in recognized entities from this class
            if (
                ! isset($this->entitySpecifications[$entity]) ||
                $this->entitySpecifications[$entity]->sionModelClass !== static::class
            ) {
                unset($results[$key]);
                continue;
            }
            if (! isset($objectKeyList[$entity])) {
                $objectKeyList[$entity] = [];
            }
            if (! in_array($entityId, $objectKeyList[$entity])) {
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

        return $changes;
    }

    /**
     * Process a row from the changes table and add it to the referenced array
     * This allows DRYs up code for processing various subsets of changes
     */
    protected function processChangeRow(array $row, array $objects, array &$changes): bool
    {
        static $users;
        $user = null;
        if (isset($row['UpdatedBy']) && is_string($row['UpdatedBy']) && is_numeric($row['UpdatedBy'])) {
            if (! isset($users)) {
                $userTable = $this->getUserTable();
                $users     = $userTable->getUsers();
            }
            if (isset($users[$row['UpdatedBy']])) {
                $user = $users[$row['UpdatedBy']];
            } else {
                $user = [
                    'userId' => $this->filterDbId($row['UpdatedBy']),
                ];
            }
        }
        $entity    = $this->filterDbString($row['ChangedEntity']);
        $entityId  = $this->filterDbString($row['ChangedIDValue']);
        $updatedOn = $this->filterDbDate($row['UpdatedOn']);
        //only bring in recognized entities from this class
        if (
            ! isset($this->entitySpecifications[$entity]) ||
            $this->entitySpecifications[$entity]->sionModelClass !== static::class ||
            ! isset($updatedOn)
        ) {
            return false;
        }
        $change = [
            'changeId'            => $this->filterDbId($row['ChangeID']),
            'entityType'          => $entity,
            'entitySpecification' => $this->entitySpecifications[$entity],
            'entityId'            => $entityId,
            'field'               => $this->filterDbString($row['ChangedField']),
            'newValue'            => $row['NewValue'],
            'oldValue'            => $row['OldValue'],
            'ipAddress'           => $this->filterDbString($row['IpAddress']),
            'updatedOn'           => $updatedOn,
            'updatedBy'           => $user,
            'object'              => null,
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
                $change['object'][$this->entitySpecifications[$entity]->nameField]
                    = ucfirst($entity) . ' Id: ' . $entityId;
            }
        }
        //we'll sort by the key afterwards
        $key = (int) date_format($updatedOn, 'U');
        while (isset($changes[$key])) {
            ++$key;
        }
        $changes[$key] = $change;
        return true;
    }

    /**
     * Register a visit in the visits table as defined by the config
     *
     * @param string $entity
     * @param int $entityId If null, it refers to an entity index that was visited, or
     *                      non-numeric entity on the site
     * @throws InvalidArgumentException
     */
    public function registerVisit($entity, $entityId = null): void
    {
        $date   = new DateTime("now", new DateTimeZone('UTC'));
        $params = [
            'Entity'    => $entity,
            'EntityId'  => $entityId,
            'UserId'    => $this->actingUserId,
            'IpAddress' => $this->privacyHash($_SERVER['REMOTE_ADDR']),
            'UserAgent' => $this->privacyHash($_SERVER['HTTP_USER_AGENT']),
            'VisitedAt' => $date->format('Y-m-d H:i:s'),
        ];
        $this->getVisitTableGateway()->insert($params);
    }

    /**
     * Hashes some data using the configured hash algorithm and salt.
     *
     * @param string $data
     */
    public function privacyHash($data): string|null
    {
        if (! isset($data)) {
            return null;
        }
        if (isset($this->privacyHashAlgorithm)) {
            if (isset($this->privacyHashSalt)) {
                $data = $this->privacyHashSalt . $data;
            }
            return Hash::compute($this->privacyHashAlgorithm, $data);
        }
        return $data;
    }

    public function getUserTable(): UserTable|null
    {
        return $this->userTable;
    }

    /**
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
     *
     * @param DateTime|null $startDate
     * @param DateTime|null $endDate
     * @throws InvalidArgumentException
     * @return string|null
     */
    public static function getYearRange($startDate, $endDate)
    {
        if (
            (null !== $startDate && ! $startDate instanceof DateTime) ||
            (null !== $endDate && ! $endDate instanceof DateTime)
        ) {
            throw new InvalidArgumentException('Date parameters must be either DateTime instances or null.');
        }

        $text = '';
        if (
            (null !== $startDate && $startDate instanceof DateTime) ||
            (null !== $endDate && $startDate instanceof DateTime)
        ) {
            if (null !== $startDate xor null !== $endDate) { //only one is set
                if (null !== $startDate) {
                    $text .= $startDate->format('Y');
                } else {
                    $text .= $endDate->format('Y');
                }
            } else {
                $startYear = (int) $startDate->format('Y');
                $endYear   = (int) $endDate->format('Y');
                if ($startYear == $endYear) {
                    $text .= ' ' . $startYear;
                } else {
                    $text .= ' ' . $startYear . '-' . $endYear;
                }
            }
            return $text;
        } else {
            return null;
        }
    }

    /**
     * Check if an assignment should be considered active based on the start/end date inclusive
     *
     * @param null|DateTime $startDate
     * @param null|DateTime $endDate
     * @return bool
     * @throws InvalidArgumentException
     */
    public static function areWeWithinDateRange($startDate, $endDate)
    {
        if (
            (null !== $startDate && ! $startDate instanceof DateTime) ||
            (null !== $endDate && ! $endDate instanceof DateTime)
        ) {
            throw new InvalidArgumentException('Invalid value passed to `areWeWithinDateRange`');
        }
        static $today;
        if (! isset($today)) {
            $timeZone = new DateTimeZone('UTC');
            $today    = new DateTime("now", $timeZone);
            $today->setTime(0, 0, 0, 0);
        }
        return ($startDate <= $today && (null === $endDate || $endDate >= $today)) ||
            (null === $startDate && (null === $endDate || $endDate >= $today));
    }

    /**
     * Filter a database int
     */
    protected function filterDbId(?string $str): int|null
    {
        if (! isset($str) || $str === '' || $str === '0') {
            return null;
        }
        return (int) $str;
    }

    /**
     * Null database string
     */
    protected function filterDbString(?string $str): string|null
    {
        if ($str === '') {
            return null;
        }
        return $str;
    }

    /**
     * Filter a database int
     */
    protected function filterDbInt(string $str): int|null
    {
        if (! isset($str) || $str === '') {
            return null;
        }
        return (int) $str;
    }

    /**
     * Filter a database boolean
     */
    protected function filterDbBool(?string $str): bool
    {
        if (null === $str || $str === '' || $str === '0') {
            return false;
        }
        static $filter;
        if (! is_object($filter)) {
            $filter = new Boolean();
        }
        return $filter->filter($str);
    }

    /**
     * @param string $str
     */
    protected function filterDbDate(string $str): DateTime|null
    {
        static $tz;
        if (! isset($tz)) {
            $tz = new DateTimeZone('UTC');
        }
        if (null === $str || $str === '' || $str === '0000-00-00' || $str === '0000-00-00 00:00:00') {
            return null;
        }
        try {
            $return = new DateTime($str, $tz);
        } catch (Exception) {
            $return = null;
        }
        return $return;
    }

    protected function filterDbGeoPoint(?string $str): ?GeoPoint
    {
        if (! isset($str)) {
            return null;
        }
        $re = '/[\d.-]+/u';
//         $str = 'POINT(76.2144 10.5276)';

        $matches = null;
        preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);

        // Print the entire match result
        if (! isset($matches)) {
            return null;
        }

        $longitude = isset($matches[0]) ? $matches[0][0] : 0;
        $latitude  = isset($matches[1]) ? $matches[1][0] : 0;
        return new GeoPoint($longitude, $latitude);
    }

    protected function filterEmailString(?string $str): string|null
    {
        static $validator;
        $str = $this->filterDbString($str);
        if (null === $str) {
            return null;
        }
        if (! is_object($validator)) {
            $validator = new EmailAddress();
        }
        if ($validator->isValid($str)) {
            return $str;
        }
        return null;
    }

    protected function formatDbDate(DateTime $object): string|null
    {
        return $object->format('Y-m-d H:i:s');
    }

    protected function formatDbArray(string|array|null $arr, string $delimiter = '|', bool $trim = true): string|null
    {
        if (! isset($arr)) {
            return null;
        }
        if (! is_array($arr)) {
            return $arr;
        }
        if (empty($arr)) {
            return null;
        }
        if ($trim) {
            foreach ($arr as $key => $value) {
                $arr[$key] = trim($value);
            }
        }
        return implode($delimiter, $arr);
    }

    protected function filterDbArray(?string $str, string $delimiter = '|', bool $trim = true): array
    {
        if (! isset($str) || $str === '') {
            return [];
        }
        $return = explode($delimiter, $str);
        if ($trim) {
            foreach ($return as $key => $value) {
                $return[$key] = trim($value);
            }
        }
        return $return;
    }

    /**
     * Check a URL and, if it is valid, return an array of format:
     * [ 'url' => 'https://...', 'label' => 'google.com']
     *
     * @psalm-return array{url: string, label: null|string}|null
     */
    public static function filterUrl(?string $str, ?string $label = null): array|null
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

    /**
     * @return (array|mixed)[]
     * @psalm-return array<list<mixed>|mixed>
     */
    public static function keyArray(array $a, string $key, bool $unique = true): array
    {
        $return = [];
        foreach ($a as $item) {
            if (! $unique) {
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
     */
    public static function strPad(
        string $input,
        int $padLength,
        string $padString = ' ',
        int $padType = STR_PAD_RIGHT
    ): string {
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

        $repeatCount = (int) floor($lengthOfPadding / $padStringLength);

        if ($padType === STR_PAD_BOTH) {
            $repeatCountLeft = $repeatCountRight = ($repeatCount - $repeatCount % 2) / 2;

            $lastStringLength       = $lengthOfPadding - 2 * $repeatCountLeft * $padStringLength;
            $lastStringLeftLength   = $lastStringRightLength = (int) floor($lastStringLength / 2);
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

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    public function setAdapter(AdapterInterface $adapter): static
    {
        $this->adapter = $adapter;
        return $this;
    }

    public function getActingUserId(): int|null
    {
        return $this->actingUserId;
    }

    public function setActingUserId(int $actingUserId): static
    {
        $this->actingUserId = $actingUserId;
        return $this;
    }

    public function getChangesTableGateway(): TableGateway|TableGatewayInterface|null
    {
        return $this->getTableGateway($this->changeTableName);
    }

    public function setChangesTableGateway(TableGatewayInterface $gateway): static
    {
        $this->changesTableGateway = $gateway;
        return $this;
    }

    public function getVisitTableGateway(): TableGatewayInterface|null
    {
        if (! isset($this->visitsTableName)) {
            return null;
        }
        return $this->getTableGateway($this->visitsTableName);
    }

    /**
     * @deprecated
     *
     * @return ISO639
     */
    protected function getIso639()
    {
        if (! isset($this->iso639)) {
            $this->iso639 = new ISO639();
        }
        return $this->iso639;
    }

    protected function getLanguageSupport(): LanguageSupport
    {
        return $this->languageSupport ?? $this->languageSupport = new LanguageSupport();
    }

    /**
     * Returns an associative array mapping 2-digit ISO-639 language codes to their names in the given language
     *
     * @return string[]
     */
    public function getLanguageNames(string $inLanguage = 'en')
    {
        return LanguageSupport::getLanguageNames($inLanguage);
    }

    /**
     * Returns an associative array mapping 2-digit ISO-639 language codes to the native language name
     *
     * @deprecated
     *
     * @return string[]
     */
    public function getNativeLanguageNames(): array
    {
        if (! isset($this->nativeLanguageNames)) {
            $languageRecords           = $this->getIso639()->allLanguages();
            $this->nativeLanguageNames = [];
            foreach ($languageRecords as $item) {
                $this->nativeLanguageNames[$item[0]] = $item[5];
            }
        }
        return $this->nativeLanguageNames;
    }

    /**
     * Get the name of a language by its 2-digit ISO-639 code
     */
    public function getLanguageName(string $twoDigitLangCode, string $inLanguage = 'en'): string|null
    {
        return LanguageSupport::getLanguageName($twoDigitLangCode, $inLanguage);
    }

    /**
     * Get the native name of a language by its 2-digit ISO-639 code
     *
     * @deprecated
     *
     * @param string $twoDigitLangCode
     */
    public function getNativeLanguageName($twoDigitLangCode): string|null
    {
        if (! isset($twoDigitLangCode) || ! is_string($twoDigitLangCode)) {
            throw new InvalidArgumentException('Please pass a two-digit language code to get its native name');
        }
        if (! isset($this->nativeLanguageNames)) {
            $this->getNativeLanguageNames();
        }
        return $this->nativeLanguageNames[$twoDigitLangCode] ?? null;
    }
}
