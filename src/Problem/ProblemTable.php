<?php

namespace SionModel\Problem;

use SionModel\Db\Model\SionTable;

class ProblemTable extends SionTable
{
    /**
     *
     * @var EntityProblem $problemPrototype
     */
    protected $problemPrototype;

    protected $problemsCache;

    public function getProblems()
    {

        if (! is_null($this->problemsCache)) {
            return $this->problemsCache;
        }

        $sql = "SELECT `ProblemId`, `Project`, `Entity`, `EntityId`,
`Problem`, `Severity`, `IgnoredOn`, `IgnoredBy`, `ResolvedOn`,
`ResolvedBy`, `UpdatedOn`, `UpdatedBy`, `CreatedOn`, `CreatedBy`
FROM `a_data_problems` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $arrayValues = [
                'problemId' => $this->filterDbId($row['ProblemId']),
                'project' => $this->filterDbString($row['Project']),
                'entity' => $this->filterDbString($row['Entity']),
                'entityId' => $this->filterDbId($row['EntityId']),
                'problem' => $this->filterDbString($row['Problem']),
                'severity' => $this->filterDbString($row['Severity']),
                'ignoredOn' => $this->filterDbDate($row['IgnoredOn']),
                'ignoredBy' => $this->filterDbId($row['IgnoredBy']),
                'resolvedOn' => $this->filterDbDate($row['ResolvedOn']),
                'resolvedBy' => $this->filterDbId($row['ResolvedBy']),
                'updatedOn' => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy' => $this->filterDbId($row['UpdatedBy']),
                'createdOn' => $this->filterDbDate($row['CreatedOn']),
                'createdBy' => $this->filterDbId($row['CreatedBy']),
            ];
            $obj = clone $this->problemPrototype;
            $obj->exchangeArray($arrayValues);
            $entities[$obj->getIdentifier()] = $obj;
        }
        return $this->problemsCache = $entities;
    }

    public function getProblem()
    {
    }
}
