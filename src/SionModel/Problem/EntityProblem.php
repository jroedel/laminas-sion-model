<?php
namespace SionModel\Problem;

class EntityProblem
{
    const SEVERITY_ERROR = 'error';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO = 'info';

    const SEVERITY_TEXT_CLASSES = [
        self::SEVERITY_ERROR => 'text-danger',
        self::SEVERITY_WARNING => 'text-warning',
        self::SEVERITY_INFO => 'text-info',
    ];

    /**
     * Keeps track of which column holds the id
     * for each entity
     * @var mixed[]
     */
    protected $entitySpecifications = [];

    protected $problemSpecifications = [];

    protected $entity;

    protected $data;

    protected $problemFieldName;

    protected $problem;

    protected $severity;

    /**
     *
     * @var \DateTime
     */
    protected $ignoredOn;

    /**
     *
     * @var int
     */
    protected $ignoredBy;

    /**
     *
     * @var \DateTime
     */
    protected $resolvedOn;

    /**
     *
     * @var int
     */
    protected $resolvedBy;

    public function __construct($problem, $data, $severity = null)
    {
        $this->setProblem($problem);
        $this->setData($data);
    }

    protected function addEntitySpecifications($entitySpecifications)
    {
        $this->entitySpecifications = array_merge($this->entitySpecifications, $entitySpecifications);
    }

    protected function addProblemSpecifications($problemSpecifications)
    {
        $this->problemSpecifications = array_merge($this->problemSpecifications, $problemSpecifications);
    }

    /**
     * A MD5 sum of concatenated entity, entityId, problem,
     * ignoredTime (when available), resolvedTime (when available).
     * This can be used to match up current problems with one's
     * stored in the database.
     */
    public function getIdentifier()
    {
        $str = $this->getEntity().$this->getEntityId().$this->getProblem();
        if (!is_null($ignoredOn = $this->getIgnoredOn())) {
            $str .= serialize($ignoredOn);
        }
        if (!is_null($resolvedOn = $this->getResolvedOn())) {
            $str .= serialize($resolvedOn);
        }
        return md5($str);
    }

    public function getProblem()
    {
        return $this->problem;
    }

    /**
     *
     * @param string $problem
     * @throws \InvalidArgumentException
     */
    public function setProblem($problem)
    {
        if (!isset($this->problemSpecifications[$problem])) {
            throw new \InvalidArgumentException('Unknown problem reported.');
        }
        $this->problem = $problem;
        $this->entity = $this->problemSpecifications[$problem]['entity'];
        if (is_null($this->severity)) {
            $this->severity = $this->problemSpecifications[$problem]['defaultSeverity'];
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     *
     * @param array $data
     * @return self
     */
    public function setData($data)
    {
        if (!is_null($data) && !is_array($data)) {
            throw new \InvalidArgumentException('Data parameter must be an array.');
        }
        $this->data = $data;
        return $this;
    }

    /**
     * @return array
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     *
     * @param array $severity
     * @return self
     */
    public function setSeverity($severity)
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * EntityProblem is valid iff:
     * 1. Entity isn't null AND
     * 2. Problem isn't null AND
     * 3. Data isn't null
     */
    public function isValidProblem()
    {
        return !is_null($this->entity) &&
            !is_null($this->problem) &&
            !is_null($this->data);
    }

    /**
     * @return string
     */
    public function getProblemFieldName()
    {
        return $this->problemFieldName;
    }

    public function getProblemText()
    {
        if (!$this->isValidProblem()) {
            throw new \Exception('Problem must be valid before retrieving problem text.');
        }

        return $this->problemSpecifications[$this->problem]['text'];
    }

    /**
     *
     * @return string
     */
    public function getProblemTextClass()
    {
        static $textClasses = self::SEVERITY_TEXT_CLASSES;
        if (isset($textClasses[$this->severity])) {
            return $textClasses[$this->severity];
        }
        return '';
    }

    /**
     * @return \DateTime
     */
    public function getIgnoredOn()
    {
        return $this->ignoredOn;
    }

    /**
     *
     * @param \DateTime $ignoredOn
     * @return self
     */
    public function setIgnoredOn($ignoredOn)
    {
        $this->ignoredOn = $ignoredOn;
        return $this;
    }

    /**
     * @return int
     */
    public function getIgnoredBy()
    {
        return $this->ignoredBy;
    }

    /**
     *
     * @param int $ignoredBy
     * @return self
     */
    public function setIgnoredBy($ignoredBy)
    {
        $this->ignoredBy = $ignoredBy;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getResolvedOn()
    {
        return $this->resolvedOn;
    }

    /**
     *
     * @param \DateTime $resolvedOn
     * @return self
     */
    public function setResolvedOn($resolvedOn)
    {
        $this->resolvedOn = $resolvedOn;
        return $this;
    }

    /**
     * @return int
     */
    public function getResolvedBy()
    {
        return $this->resolvedBy;
    }

    /**
     *
     * @param int $resolvedBy
     * @return self
     */
    public function setResolvedBy($resolvedBy)
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }

    /**
     * @return int
     */
    public function getEntityId()
    {
        if (!$this->isValidProblem()) {
            throw new \Exception('Problem must be valid before retrieving entity id.');
        }
        if (!isset($this->entitySpecifications[$this->getEntity()])) {
            throw new \Exception('Invalid entity used: '.$this->getEntity());
        }
        $fieldName = $this->entitySpecifications[$this->getEntity()];
        if (!isset($this->getData()[$fieldName])) {
            throw new \Exception('Problem data does not include id field.');
        }
        return $this->getData()[$fieldName];
    }
}