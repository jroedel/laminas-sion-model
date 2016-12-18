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

    protected $problemSpecifications = [];

    protected $entity;

    protected $data;

    protected $problemFieldName;

    protected $problem;

    protected $severity;

    public function __construct($problem, $data, $severity = null)
    {
        $this->setProblem($problem);
        $this->setData($data);
    }

    protected function addProblemSpecifications($problemSpecifications)
    {
        $this->problemSpecifications = array_merge($this->problemSpecifications, $problemSpecifications);
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
}