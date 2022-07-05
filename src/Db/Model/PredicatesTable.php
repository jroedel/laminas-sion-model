<?php

declare(strict_types=1);

namespace SionModel\Db\Model;

use DateTime;
use Exception;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Predicate\In;
use Laminas\Db\Sql\Predicate\Operator;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;

use Psr\Container\ContainerInterface;
use function count;
use function is_array;

class PredicatesTable extends SionTable
{
    public const COMMENT_KIND_COMMENT = 'comment'; //only comment
    public const COMMENT_KIND_RATING  = 'rating'; //only rating
    public const COMMENT_KIND_REVIEW  = 'review'; //comment+rating
    public const COMMENT_KINDS        = [
        self::COMMENT_KIND_COMMENT => 'Comment',
        self::COMMENT_KIND_RATING  => 'Rating',
        self::COMMENT_KIND_REVIEW  => 'Review',
    ];

    public const COMMENT_STATUS_IN_REVIEW = 'in-review';
    public const COMMENT_STATUS_PUBLISHED = 'published';
    public const COMMENT_STATUS_DENIED    = 'denied';

    public function __construct(AdapterInterface $adapter, ContainerInterface $container, ?int $actingUserId)
    {
        parent::__construct($adapter, $container, $actingUserId);
    }

    /**
     * Query params are [predicateKind|objectEntityKind,objectId(array|string)]
     *
     * @throws Exception
     */
    public function getComments(array $query = [], array $options = []): array
    {
        $gateway     = $this->getTableGateway('comments');
        $select      = $this->getCommentSelectPrototype();
        $where       = new Where();
        $combination = isset($options['orCombination']) && $options['orCombination']
            ? PredicateSet::OP_OR
            : PredicateSet::OP_AND;
        $fieldMap    = $this->getEntitySpecification('comment')->updateColumns;

        if (isset($query['objectEntityId'])) {
            if (! isset($query['predicateKind'])) {
                throw new Exception('When asking for comments refering to a specific entity, '
                    . 'please specify the predicateKind');
            }
            $joinPredicate = new PredicateSet();
            $joinPredicate->addPredicates([
                new Operator(
                    'relationships.SubjectEntityId',
                    Operator::OPERATOR_EQUAL_TO,
                    'comments.CommentId',
                    Operator::TYPE_IDENTIFIER,
                    Operator::TYPE_IDENTIFIER
                ),
                new Operator('relationships.PredicateKind', Operator::OPERATOR_EQUAL_TO, $query['predicateKind']),
            ]);
            $objectEntityIdPredicate = null;
            if (is_array($query['objectEntityId'])) {
                if (empty($query['objectEntityId'])) {
                } elseif (1 === count($query['objectEntityId'])) {
                    $query['objectEntityId'] = $query['objectEntityId'][0];
                } else {
                    $objectEntityIdPredicate = new In('relationships.ObjectEntityId', $query['objectEntityId']);
                }
            }
            if (! is_array($query['objectEntityId'])) {
                $objectEntityIdPredicate = new Operator(
                    'relationships.ObjectEntityId',
                    Operator::OPERATOR_EQUAL_TO,
                    $query['objectEntityId']
                );
            }
            if (isset($objectEntityIdPredicate)) {
                $joinPredicate->addPredicate($objectEntityIdPredicate);
            }
        }
        if (isset($joinPredicate)) {
            $select->join(
                'relationships',
                $joinPredicate,
                [],
                Select::JOIN_INNER
            );
        }

        if (isset($query['status'])) {
            $statusClause = new Operator(
                $fieldMap['status'],
                Operator::OPERATOR_EQUAL_TO,
                $query['status']
            );
            $where->addPredicate($statusClause, $combination);
        }

        $select->where($where);
        $results = $gateway->selectWith($select);

        $entities = [];
        foreach ($results as $row) {
            $processedRow                         = $this->processCommentRow($row);
            $entities[$processedRow['commentId']] = $processedRow;
        }
        return $entities;
    }

    public function getComment($id)
    {
        static $gateway;
        if (! isset($gateway)) {
            $gateway = $this->getTableGateway('comments');
        }
        $select = $this->getCommentSelectPrototype();
        $select->where(['CommentId' => $id]);
        /** @var ResultSetInterface $result */
        $result  = $gateway->selectWith($select);
        $results = $result->toArray();

        if (! isset($results[0])) {
            return null;
        }
        return $this->processCommentRow($results[0]);
    }

    /**
     * Update the categories of all related books at the same time
     *
     * @param array $data
     * @param array $newEntityData
     * @param string $action
     */
    protected function postprocessComment($data, $newEntityData, $action): void
    {
        static $commentPredicates;
        if (self::ENTITY_ACTION_CREATE === $action && isset($data['entity']) && isset($data['entityId'])) {
            if (! isset($commentPredicates)) {
                $commentPredicates = $this->getCommentPredicates();
            }
            $entity = $data['entity'];
            if (! isset($commentPredicates[$entity])) {
                throw new Exception("No comment predicate for entity `$entity`");
            }
            $newRelationshipData = [
                'subjectEntityId' => $newEntityData['commentId'],
                'objectEntityId'  => $data['entityId'],
                'predicateKind'   => $commentPredicates[$entity],
            ];
            $this->createEntity('relationship', $newRelationshipData);
        }
    }

    /**
     * Get a standardized select object to retrieve records from the database
     *
     * @return Select
     */
    protected function getCommentSelectPrototype()
    {
        static $select;
        if (! isset($select)) {
            $select = new Select('comments');
            $select->columns([
                'CommentId',
                'Rating',
                'CommentKind',
                'Comment',
                'Status',
                'ReviewedBy',
                'ReviewedOn',
                'CreatedOn',
                'CreatedBy',
            ]);
            $select->order(['CreatedOn']);
        }

        return clone $select;
    }

    /**
     * @return (DateTime|int|mixed|null|string)[]
     * @psalm-return array{commentId: (int), rating: (int|null), commentKind: mixed, comment: mixed, status: mixed, reviewedOn: DateTime, reviewedBy: (null|int), createdOn: DateTime, createdBy: (null|int), reviewedByUsername: (mixed|null|string), createdByUsername: (mixed|null|string)}
     */
    protected function processCommentRow($row): array
    {
        static $usernames;
        if (! isset($usernames)) {
            $usernames = $this->getUserTable()->getUsernames();
        }
        $reviewedBy = $this->filterDbId($row['ReviewedBy']);
        $createdBy  = $this->filterDbId($row['CreatedBy']);
        return [
            'commentId'          => $this->filterDbId($row['CommentId']),
            'rating'             => $this->filterDbInt($row['Rating']),
            'commentKind'        => $row['CommentKind'],
            'comment'            => $row['Comment'],
            'status'             => $row['Status'],
            'reviewedOn'         => $this->filterDbDate($row['ReviewedOn']),
            'reviewedBy'         => $reviewedBy,
            'createdOn'          => $this->filterDbDate($row['CreatedOn']),
            'createdBy'          => $createdBy,
            'reviewedByUsername' => $usernames[$reviewedBy] ?? null,
            'createdByUsername'  => $usernames[$createdBy] ?? null,
        ];
    }

    /**
     * Return an associative array keyed on the the entity type mapped to the predicate key
     *
     * @return string[]
     */
    public function getCommentPredicates()
    {
        $predicates = $this->getPredicates();
        $objects    = [];
        foreach ($predicates as $kind => $object) {
            if ('comment' === $object['subjectEntity']) {
                $objects[$object['objectEntity']] = $kind;
            }
        }
        return $objects;
    }

    public function getPredicates()
    {
        $cacheKey = 'predicates';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $gateway = $this->getTableGateway('predicates');
        $select  = $this->getPredicateSelectPrototype();
        $results = $gateway->selectWith($select);

        $objects = [];
        foreach ($results as $row) {
            $id           = $row['PredicateKind'];
            $objects[$id] = [
                'kind'          => $id,
                'subjectEntity' => $row['SubjectEntityKind'],
                'objectEntity'  => $row['ObjectEntityKind'],
                'predicateText' => $row['PredicateText'],
                'description'   => $row['DescriptionEn'],
            ];
        }

        $this->cacheEntityObjects($cacheKey, $objects);
        return $objects;
    }

    /**
     * Get a standardized select object to retrieve records from the database
     *
     * @return Select
     */
    protected function getPredicateSelectPrototype()
    {
        static $select;
        if (! isset($select)) {
            $select = new Select('predicates');
            $select->columns([
                'PredicateKind',
                'SubjectEntityKind',
                'ObjectEntityKind',
                'PredicateText',
                'DescriptionEn',
            ]);
            $select->order(['PredicateKind']);
        }

        return clone $select;
    }

    /**
     * @return (DateTime|mixed|null|int)[]
     * @psalm-return array{relationshipId: (null|int), subjectEntityId: (null|int), objectEntityId: (null|int), predicateKind: mixed, priority: mixed, publicNotes: mixed, adminNotes: mixed, publicNotesUpdatedOn: DateTime, publicNotesUpdatedBy: (null|int), adminNotesUpdatedOn: DateTime, adminNotesUpdatedBy: (null|int), updatedOn: DateTime, updatedBy: (null|int)}
     */
    protected function processRelationshipRow($row): array
    {
        return [
            'relationshipId'       => $this->filterDbId($row['RelationshipId']),
            'subjectEntityId'      => $this->filterDbId($row['SubjectEntityId']),
            'objectEntityId'       => $this->filterDbId($row['ObjectEntityId']),
            'predicateKind'        => $row['PredicateKind'],
            'priority'             => $row['Priority'],
            'publicNotes'          => $row['PublicNotes'],
            'adminNotes'           => $row['AdminNotes'],
            'publicNotesUpdatedOn' => $this->filterDbDate($row['PublicNotesUpdatedOn']),
            'publicNotesUpdatedBy' => $this->filterDbId($row['PublicNotesUpdatedBy']),
            'adminNotesUpdatedOn'  => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'  => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'updatedOn'            => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'            => $this->filterDbId($row['UpdatedBy']),
        ];
    }

    /**
     * Get a standardized select object to retrieve records from the database
     *
     * @return Select
     */
    protected function getRelationshipSelectPrototype()
    {
        static $select;
        if (! isset($select)) {
            $select = new Select('predicates');
            $select->columns([
                'RelationshipId',
                'SubjectEntityId',
                'ObjectEntityId',
                'PredicateKind',
                'Priority',
                'PublicNotes',
                'AdminNotes',
                'PublicNotesUpdatedOn',
                'PublicNotesUpdatedBy',
                'AdminNotesUpdatedOn',
                'AdminNotesUpdatedBy',
                'UpdatedOn',
                'UpdatedBy',
            ]);
            //@todo join in the predicate table and add in all the significant columns
            $select->order(['PredicateKind', 'Priority', 'UpdatedOn']);
        }

        return clone $select;
    }
}
