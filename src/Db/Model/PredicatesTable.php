<?php
namespace SionModel\Db\Model;

use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Predicate;

class PredicatesTable extends SionTable
{
    const COMMENT_KIND_COMMENT = 'comment';
    const COMMENT_KIND_REVIEW = 'review';
    
    const COMMENT_STATUS_IN_REVIEW = 'in-review';
    const COMMENT_STATUS_PUBLISHED = 'published';
    const COMMENT_STATUS_DENIED = 'denied';
    
    /**
     * Query params are [predicateKind|objectEntityKind,objectId(array|string)]
     *
     * @param array $query
     * @param array $options
     */
    public function getCommentsForEntity($query, array $options = [])
    {
        $gateway = $this->getTableGateway('comments');
        $where = [];
        $where['relationships.PredicateKind'] = $query['predicate'];
        if (!is_array($query['objectId'])) {
            $where['relationships.ObjectEntityId'] = $query['objectId'];
        }
    
        $select = $this->getCommentSelectPrototype();
        $select->where($where);
        
        if (is_array($query['objectId'])) {
            $predicate = new Predicate();
            $select->where($predicate->in('ObjectEntityId', $query['objectId']));
        }
        $results = $gateway->selectWith($select);
        
        $entities = [];
        foreach ($results as $row) {
            $processedRow = $this->processCommentRow($row);
            $entities[$processedRow['commentId']] = $processedRow;
        }
        return $entities;
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
            $select->join('relationships', 'relationships.SubjectEntityId=comments.CommentId', [], Select::JOIN_INNER);
            $select->order(['CreatedOn']);
        }
        
        return clone $select;
    }
    
    protected function processCommentRow($row)
    {
        $data = [
            'commentId'         => $this->filterDbId($row['CommentId']),
            'rating'            => $this->filterDbInt($row['Rating']),
            'commentKind'       => $row['CommentKind'],
            'comment'           => $row['Comment'],
            'status'            => $row['Status'],
            'reviewedBy'        => $this->filterDbId($row['ReviewedBy']),
            'reviewedOn'        => $this->filterDbDate($row['ReviewedOn']),
            'createdOn'         => $this->filterDbId($row['CreatedOn']),
            'createdBy'         => $this->filterDbDate($row['CreatedBy']),
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
            'relationshipId' => $row['RelationshipId'],
            'subjectEntityId' => $row['SubjectEntityId'],
            'objectEntityId' => $row['ObjectEntityId'],
            'predicateKind' => $row['PredicateKind'],
            'priority' => $row['Priority'],
            'publicNotes' => $row['PublicNotes'],
            'adminNotes' => $row['AdminNotes'],
            'publicNotesUpdatedOn' => $row['PublicNotesUpdatedOn'],
            'publicNotesUpdatedBy' => $row['PublicNotesUpdatedBy'],
            'adminNotesUpdatedOn' => $row['AdminNotesUpdatedOn'],
            'adminNotesUpdatedBy' => $row['AdminNotesUpdatedBy'],
            'updatedOn' => $row['UpdatedOn'],
            'updatedBy' => $row['UpdatedBy'],
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
