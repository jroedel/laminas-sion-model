<?php
namespace SionModel\Db\Model;

use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Predicate;

class PredicatesTable extends SionTable
{
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
}
