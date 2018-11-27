<?php
namespace SionModel\Db\Model;

use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\In;

class PredicatesTable extends SionTable
{
    const COMMENT_KIND_COMMENT = 'comment'; //only comment
    const COMMENT_KIND_RATING = 'rating'; //only rating
    const COMMENT_KIND_REVIEW = 'review'; //comment+rating
    
    const COMMENT_STATUS_IN_REVIEW = 'in-review';
    const COMMENT_STATUS_PUBLISHED = 'published';
    const COMMENT_STATUS_DENIED = 'denied';
    
    /**
     * Query params are [predicateKind|objectEntityKind,objectId(array|string)]
     *
     * @param array $query
     * @param array $options
     */
    public function getComments($query = [], array $options = [])
    {
        $gateway = $this->getTableGateway('comments');
        $select = $this->getCommentSelectPrototype();
        $where = new Where();
        $combination = (isset($options['orCombination']) && $options['orCombination']) 
            ? PredicateSet::OP_OR 
            : PredicateSet::OP_AND;
        $fieldMap = $this->getEntitySpecification('comment')->updateColumns;
        
        if (isset($query['objectEntityId'])) {
            if (!isset($query['predicateKind'])) {
                throw new \Exception('When asking for comments refering to a specific entity, '
                    .'please specify the predicateKind');
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
                new Operator('relationships.PredicateKind', Operator::OPERATOR_EQUAL_TO, $query['predicateKind'])
                ]);
            $objectEntityIdPredicate = null;
            if (is_array($query['objectEntityId'])) {
                if (empty($query['objectEntityId'])) {
                } elseif(1 === count($query['objectEntityId'])) {
                    $query['objectEntityId'] = $query['objectEntityId'][0];
                } else {
                    $objectEntityIdPredicate = new In('relationships.ObjectEntityId', $query['objectEntityId']);
                }
            }
            if (!is_array($query['objectEntityId'])) {
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
            $select->join('relationships',
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
            $processedRow = $this->processCommentRow($row);
            $entities[$processedRow['commentId']] = $processedRow;
        }
        return $entities;
    }
    
    public function getComment($id)
    {
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGateway('comments');
        }
        $select = $this->getCommentSelectPrototype();
        $select->where(['CommentId' => $id]);
        /** @var ResultSet $result */
        $result = $gateway->selectWith($select);
        $results = $result->toArray();
        
        if (!isset($results[0])) {
            return null;
        }
        $object = $this->processCommentRow($results[0]);
        return $object;
    }
    
    /**
     * Update the categories of all related books at the same time
     * @param array $data
     * @param array $newEntityData
     * @param string $action
     */
    protected function postprocessComment($data, $newEntityData, $action)
    {
        static $commentPredicates;
        if (self::ENTITY_ACTION_CREATE === $action && isset($data['entity']) && isset($data['entityId'])) {
            if (!isset($commentPredicates)) {
                $commentPredicates = $this->getCommentPredicates();
            }
            $entity = $data['entity'];
            if (!isset($commentPredicates[$entity])) {
                throw new \Exception("No comment predicate for entity `$entity`");
            }
            $newRelationshipData = [
                'subjectEntityId' => $newEntityData['commentId'],
                'objectEntityId' => $data['entityId'],
                'predicateKind' => $commentPredicates[$entity],
            ];
            $this->createEntity('relationship', $newRelationshipData);
        }
    }
    
    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getCommentSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select('comments');
            $select->columns(['CommentId', 'Rating', 'CommentKind', 'Comment', 'Status',
                'ReviewedBy', 'ReviewedOn', 'CreatedOn', 'CreatedBy']);
            $select->order(['CreatedOn']);
        }
        
        return clone $select;
    }
    
    protected function processCommentRow($row)
    {
        static $usernames;
        if (!isset($usernames)) {
            $usernames = $this->getUserTable()->getUsernames();
        }
        $reviewedBy = $this->filterDbId($row['ReviewedBy']);
        $createdBy = $this->filterDbId($row['CreatedBy']);
        $data = [
            'commentId'         => $this->filterDbId($row['CommentId']),
            'rating'            => $this->filterDbInt($row['Rating']),
            'commentKind'       => $row['CommentKind'],
            'comment'           => $row['Comment'],
            'status'            => $row['Status'],
            'reviewedOn'        => $this->filterDbDate($row['ReviewedOn']),
            'reviewedBy'        => $reviewedBy,
            'createdOn'         => $this->filterDbDate($row['CreatedOn']),
            'createdBy'         => $createdBy,
            
            'reviewedByUsername' => isset($usernames[$reviewedBy]) ? $usernames[$reviewedBy] : null,
            'createdByUsername' => isset($usernames[$createdBy]) ? $usernames[$createdBy] : null,
        ];
        return $data;
    }
    
    /**
     * Return an associative array keyed on the the entity type mapped to the predicate key
     * @return string[]
     */
    public function getCommentPredicates()
    {
        $predicates = $this->getPredicates();
        $objects = [];
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
        $select = $this->getPredicateSelectPrototype();
        $results = $gateway->selectWith($select);
        
        $objects = [];
        foreach ($results as $row) {
            $id = $row['PredicateKind'];
            $objects[$id] = [
                'kind'                  => $id,
                'subjectEntity'         => $row['SubjectEntityKind'],
                'objectEntity'          => $row['ObjectEntityKind'],
                'predicateText'         => $row['PredicateText'],
                'description'           => $row['DescriptionEn'],
            ];
        }
        
        $this->cacheEntityObjects($cacheKey, $objects);
        return $objects;
    }
    
    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getPredicateSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select('predicates');
            $select->columns(['PredicateKind', 'SubjectEntityKind', 'ObjectEntityKind', 
                'PredicateText', 'DescriptionEn']);
            $select->order(['PredicateKind']);
        }
        
        return clone $select;
    }
    
    
    
    protected function processRelationshipRow($row)
    {
        $data = [
            'relationshipId' => $this->filterDbId($row['RelationshipId']),
            'subjectEntityId' => $this->filterDbId($row['SubjectEntityId']),
            'objectEntityId' => $this->filterDbId($row['ObjectEntityId']),
            'predicateKind' => $row['PredicateKind'],
            'priority' => $row['Priority'],
            'publicNotes' => $row['PublicNotes'],
            'adminNotes' => $row['AdminNotes'],
            'publicNotesUpdatedOn' => $this->filterDbDate($row['PublicNotesUpdatedOn']),
            'publicNotesUpdatedBy' => $this->filterDbId($row['PublicNotesUpdatedBy']),
            'adminNotesUpdatedOn' => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy' => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'updatedOn' => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy' => $this->filterDbId($row['UpdatedBy']),
        ];
        return $data;
    }
    
    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getRelationshipSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select('predicates');
            $select->columns(['RelationshipId', 'SubjectEntityId', 'ObjectEntityId', 'PredicateKind', 
                'Priority', 'PublicNotes', 'AdminNotes', 'PublicNotesUpdatedOn', 'PublicNotesUpdatedBy', 
                'AdminNotesUpdatedOn', 'AdminNotesUpdatedBy', 'UpdatedOn', 'UpdatedBy']);
            //@todo join in the predicate table and add in all the significant columns
            $select->order(['PredicateKind', 'Priority', 'UpdatedOn']);
        }
        
        return clone $select;
    }
}
