<?php
namespace SionModel\Service;

use SionModel\Problem\Model\ProblemTable;
use SionModel\Problem\ProblemProviderInterface;
use SionModel\Problem\EntityProblem;
use Zend\Stdlib\ArrayUtils;
use Zend\ServiceManager\ServiceLocatorInterface;

class ProblemService
{
    /**
     * Service locator used for finding ProblemProvider's
     * @var ServiceLocatorInterface $serviceLocator
     */
    protected $serviceLocator;
    /**
     *
     * @var ProblemTable
     */
    protected $problemTable;

    /**
     * An array of initialized services keyed by the service name.
     * Each array contains the following keys: serviceName(string), service(object), problems(array)
     * @var mixed[][] $problemProviders
     */
    protected $problemProviders = [];

    /**
     * Array of all collected problems keyed by the entityKey. Set from getCurrentProblems.
     * @var EntityProblem[] $sortedProblems
     */
    protected $sortedProblems;

    /**
     *
     * @var EntityProblem $entityProblemPrototype
     */
    protected $entityProblemPrototype;

    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @param \SionModel\Problem\ProblemTable $problemTable
     * @param mixed[][] $problemProviders
     * @param EntityProblem $problemPrototype
     */
    public function __construct(ServiceLocatorInterface $serviceLocator, \SionModel\Problem\ProblemTable $problemTable, $problemProviders, EntityProblem $entityProblemPrototype)
    {
        $this->serviceLocator = $serviceLocator;
        $this->problemTable = $problemTable;
        foreach ($problemProviders as $serviceName) {
            $this->problemProviders[$serviceName] = [
                'serviceName' => $serviceName,
                'service' => null,
                'problems' => null,
            ];
        }
        $this->entityProblemPrototype = $entityProblemPrototype;
    }

    public function getEntityProblemPrototype()
    {
        return $this->entityProblemPrototype;
    }

    /**
     *
     * @param EntityProblem $entityProblemPrototype
     * @return self
     */
    public function setEntityProblemPrototype(EntityProblem $entityProblemPrototype)
    {
        $this->entityProblemPrototype = $entityProblemPrototype;
        return $this;
    }

    /**
     * Get current and db stored problems
     * @param string[] $entityKeys
     * @todo integrate the db stored problems
     */
    public function getProblems(array $entityKeys = null)
    {
        $problems = $this->getCurrentProblems($entities);
        return $problems;
    }

    /**
     * Get the current entity problems from all the providers
     * @param string[] $entityKeys
     */
    public function getCurrentProblems(array $entityKeys = null)
    {
        if (!is_null($this->sortedProblems)) {
            $problems = $this->sortedProblems;
        } else {
            $problems = [];
            foreach ($this->problemProviders as $providerInfo) {
                $currentProblems = $this->getCurrentProviderEntityProblems($providerInfo['serviceName']);
                $problems = ArrayUtils::merge($problems, $currentProblems);
            }
            $this->sortedProblems = $problems;
        }

        //remove undesired entities and flatten array
        $result = [];
        foreach ($problems as $entityKey => $entityProblems) {
            if (is_null($entityKeys) || in_array($entityKey, $entityKeys)) {
                $result = array_merge($result, $entityProblems);
            }
        }

        return $result;
    }

    /**
     * Returns an associative array keyed by the entity name, each of which contains keyed EntityProblem
     * elements keyed by their MD5 Identifier
     * @param string $providerKey
     * @return EntityProblem[][]
     * @throws \InvalidArgumentException
     */
    protected function getCurrentProviderEntityProblems($providerKey)
    {
        if (!isset($this->problemProviders[$providerKey])) {
            throw new \InvalidArgumentException('Requested problem provider \''.$providerKey.'\' not found');
        }
        if (!is_null($this->problemProviders[$providerKey]['problems'])
        ) {
            return $this->problemProviders[$providerKey]['problems'];
        }
        $service = $this->getProblemProviderService($providerKey);

        $rawProblems = $service->getProblems();

        //key the array by entity name and MD5 identifiers
        $problems = [];
        foreach ($rawProblems as $problem) {
            if (!$problem instanceof EntityProblem) {
                throw new \InvalidArgumentException('All elements returned by a problem provider must be EntityProblem instances.');
            }
            if (!$problem->isValidProblem()) {
                throw new \InvalidArgumentException('All elements returned by a problem provider must be valid EntityProblem instances. Perhaps the problem or data was never set.');
            }

            $entity = $problem->getEntity();
            if (!isset($problems[$entity])) {
                $problems[$entity] = [];
            }
            $problems[$entity][$problem->getIdentifier()] = $problem;
        }

        return $this->problemProviders[$providerKey]['problems'] = $problems;
    }

    /**
     *
     * @param string $entity
     * @return ProblemProviderInterface
     * @throws \InvalidArgumentException
     */
    protected function getProblemProviderService($serviceName)
    {
        if (!isset($this->problemProviders[$serviceName])) {
            throw new \InvalidArgumentException('Requested problem provider \''.$serviceName.'\' not found');
        }
        if (!is_null($this->problemProviders[$serviceName]['service'])) {
            return $this->problemProviders[$serviceName]['service'];
        }
        $service = $this->serviceLocator->get($serviceName);
        if (!$service instanceof ProblemProviderInterface) {
            throw new \InvalidArgumentException('Requested problem provider \''.$serviceName.'\' not an instance of ProblemProviderInterface');
        }

        return $this->problemProviders[$serviceName]['service'] = $service;
    }
    
    /**
     * Autofix any possible problems in all provider services. 
     * @param bool $simulate
     */
    public function autoFixProblems($simulate = true)
    {
        $problems = [];
        foreach ($this->problemProviders as $providerInfo) {
            $currentProblems = $this->getProblemProviderService($providerInfo['serviceName'])
                ->autoFixProblems($simulate);
            $problems = ArrayUtils::merge($problems, $currentProblems);
        }
        return $problems;
    }
}