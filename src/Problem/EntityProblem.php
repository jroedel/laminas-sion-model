<?php

declare(strict_types=1);

namespace SionModel\Problem;

use DateTime;
use Exception;
use InvalidArgumentException;
use SionModel\Entity\Entity;

use function array_key_exists;
use function array_merge;
use function is_string;
use function md5;
use function serialize;
use function strlen;

class EntityProblem
{
    public const SEVERITY_ERROR   = 'danger';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO    = 'info';

    public const SEVERITY_TEXT_CLASSES = [
        self::SEVERITY_ERROR   => 'text-danger',
        self::SEVERITY_WARNING => 'text-warning',
        self::SEVERITY_INFO    => 'text-info',
    ];

    public const TEXT_DOMAIN_DEFAULT = 'SionModel';

    /**
     * Associative array of Entity's, keyed by the Entity name
     *
     * @var Entity[] $entities
     */
    protected array $entities = [];

    protected array $problemSpecifications = [];

    protected string $entity;

    protected array $data;

    protected string $problemFieldName;

    /** @var string $problem */
    protected $problem;

    protected string $severity;

    protected string $translatorTextDomain;

    protected ?DateTime $ignoredOn;

    protected ?int $ignoredBy;

    protected ?DateTime $resolvedOn;

    protected ?int $resolvedBy;

    /**
     * Construct a new entity problem prototype. New entity problems are meant
     * to be cloned from the \SionModel\Service\ProblemService where they will
     * be prefilled with 'problem_specifications' values.
     *
     * @param Entity[] $entities Associative array of Entity's, keyed by the Entity name
     * @param array[] $problemSpecifications
     * @throws Exception
     */
    public function __construct(array $entities, array $problemSpecifications)
    {
        $this->entities = $entities;
        $this->addProblemSpecifications($problemSpecifications);
    }

    protected function addProblemSpecifications(array $problemSpecifications): void
    {
        $specsToAdd = [];
        foreach ($problemSpecifications as $problem => $spec) {
            //validate the problem specification
            if (
                isset($spec['entity']) && is_string($spec['entity']) &&
                strlen($spec['entity']) <= 50 &&
                isset($spec['defaultSeverity']) &&
                array_key_exists($spec['defaultSeverity'], self::SEVERITY_TEXT_CLASSES) &&
                isset($spec['text']) && is_string($spec['text'])
            ) {
                $specsToAdd[$problem] = $spec;
            } else {
                throw new Exception(
                    "Invalid problem specification '$problem'. Specify entity, defaultSeverity, and text."
                );
            }
        }
        $this->problemSpecifications = array_merge($this->problemSpecifications, $specsToAdd);
    }

    /**
     * A MD5 sum of concatenated entity, entityId, problem,
     * ignoredTime (when available), resolvedTime (when available).
     * This can be used to match up current problems with one's
     * stored in the database.
     */
    public function getIdentifier(): string
    {
        $str       = $this->getEntity() . $this->getEntityId() . $this->getProblem();
        $ignoredOn = $this->getIgnoredOn();
        if (isset($ignoredOn)) {
            $str .= serialize($ignoredOn);
        }
        $resolvedOn = $this->getResolvedOn();
        if (isset($resolvedOn)) {
            $str .= serialize($resolvedOn);
        }
        return md5($str);
    }

    public function exchangeArray(array $array): void
    {
    }

    public function getProblem(): string
    {
        return $this->problem;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setProblem(string $problem): static
    {
        if (! isset($this->problemSpecifications[$problem])) {
            throw new InvalidArgumentException('Unknown problem reported.');
        }
        $this->problem = $problem;
        $this->entity  = $this->problemSpecifications[$problem]['entity'];
        if (! isset($this->severity)) {
            $this->severity = $this->problemSpecifications[$problem]['defaultSeverity'];
        }
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getEntitySpecification(): Entity
    {
        return $this->entities[$this->getEntity()];
    }

    /**
     * EntityProblem is valid iff:
     * 1. Entity isn't null AND
     * 2. Problem isn't null AND
     * 3. Data isn't null
     */
    public function isValidProblem(): bool
    {
        return isset($this->entity) && isset($this->problem) && isset($this->data);
    }

    public function getProblemFieldName(): string
    {
        return $this->problemFieldName;
    }

    public function getProblemText()
    {
        if (! $this->isValidProblem()) {
            throw new Exception('Problem must be valid before retrieving problem text.');
        }

        return $this->problemSpecifications[$this->problem]['text'];
    }

    /**
     * Get the translatorTextDomain value
     */
    public function getTranslatorTextDomain(): string
    {
        if (! isset($this->translatorTextDomain)) {
            if (isset($this->problemSpecifications[$this->problem]['textDomain'])) {
                $this->translatorTextDomain = $this->problemSpecifications[$this->problem]['textDomain'];
            } else {
                $this->translatorTextDomain = self::TEXT_DOMAIN_DEFAULT;
            }
        }
        return $this->translatorTextDomain;
    }

    public function setTranslatorTextDomain(string $translatorTextDomain): static
    {
        $this->translatorTextDomain = $translatorTextDomain;
        return $this;
    }

    public function getProblemTextClass(): string
    {
        static $textClasses = self::SEVERITY_TEXT_CLASSES;
        if (isset($textClasses[$this->severity])) {
            return $textClasses[$this->severity];
        }
        return '';
    }

    /**
     * @return DateTime
     */
    public function getIgnoredOn()
    {
        return $this->ignoredOn;
    }

    /**
     * @param DateTime $ignoredOn
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
     * @param int $ignoredBy
     * @return self
     */
    public function setIgnoredBy($ignoredBy)
    {
        $this->ignoredBy = $ignoredBy;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getResolvedOn()
    {
        return $this->resolvedOn;
    }

    /**
     * @param DateTime $resolvedOn
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
        if (! $this->isValidProblem()) {
            throw new Exception('Problem must be valid before retrieving entity id.');
        }
        if (! isset($this->entities[$this->getEntity()])) {
            throw new Exception('Invalid entity used: ' . $this->getEntity());
        }
        $fieldName = $this->getEntitySpecification()->entityKeyField;
        if (! isset($this->getData()[$fieldName])) {
            throw new Exception("Problem data does not include id field: $fieldName");
        }
        return $this->getData()[$fieldName];
    }
}
