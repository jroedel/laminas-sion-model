<?php

declare(strict_types=1);

namespace SionModel\Service;

use InvalidArgumentException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use SionModel\Problem\EntityProblem;
use SionModel\Problem\ProblemProviderInterface;
use SionModel\Problem\ProblemTable;

use function array_merge;
use function in_array;

class ProblemService
{
    /**
     * An array of initialized services keyed by the service name.
     * Each array contains the following keys: serviceName(string), service(object), problems(array)
     *
     * @var array[] $problemProviders
     */
    protected array $problemProviders = [];

    /**
     * Array of all collected problems keyed by the entityKey. Set from getCurrentProblems.
     *
     * @var EntityProblem[] $sortedProblems
     */
    protected array $sortedProblems = [];

    public function __construct(
        protected ServiceLocatorInterface $serviceLocator,
        protected ProblemTable $problemTable,
        array $problemProviders,
        protected EntityProblem $entityProblemPrototype
    ) {
        foreach ($problemProviders as $serviceName) {
            $this->problemProviders[$serviceName] = [
                'serviceName' => $serviceName,
                'service'     => null,
                'problems'    => null,
            ];
        }
    }

    public function getEntityProblemPrototype(): EntityProblem
    {
        return $this->entityProblemPrototype;
    }

    /**
     * Get current and db stored problems
     *
     * @param string[] $entityKeys
     * @todo integrate the db stored problems
     * @return EntityProblem[]
     * @psalm-return array<EntityProblem>
     */
    public function getProblems(?array $entityKeys = null): array
    {
        return $this->getCurrentProblems($entityKeys);
    }

    /**
     * Get the current entity problems from all the providers
     *
     * @param string[] $entityKeys
     * @return EntityProblem[]
     */
    public function getCurrentProblems(?array $entityKeys = null)
    {
        if (isset($this->sortedProblems)) {
            $problems = $this->sortedProblems;
        } else {
            $problems = [];
            foreach ($this->problemProviders as $providerInfo) {
                $currentProblems = $this->getCurrentProviderEntityProblems($providerInfo['serviceName']);
                $problems        = ArrayUtils::merge($problems, $currentProblems);
            }
            $this->sortedProblems = $problems;
        }

        //remove undesired entities and flatten array
        $result = [];
        foreach ($problems as $entityKey => $entityProblems) {
            if (! isset($entityKeys) || in_array($entityKey, $entityKeys)) {
                $result = array_merge($result, $entityProblems);
            }
        }

        return $result;
    }

    /**
     * Returns an associative array keyed by the entity name, each of which contains keyed EntityProblem
     * elements keyed by their MD5 Identifier
     *
     * @param string $providerKey
     * @return EntityProblem[][]
     * @throws InvalidArgumentException
     */
    protected function getCurrentProviderEntityProblems($providerKey)
    {
        if (! isset($this->problemProviders[$providerKey])) {
            throw new InvalidArgumentException('Requested problem provider \'' . $providerKey . '\' not found');
        }
        if (            isset($this->problemProviders[$providerKey]['problems'])) {
            return $this->problemProviders[$providerKey]['problems'];
        }
        $service = $this->getProblemProviderService($providerKey);

        $rawProblems = $service->getProblems();

        //key the array by entity name and MD5 identifiers
        $problems = [];
        foreach ($rawProblems as $problem) {
            if (! $problem instanceof EntityProblem) {
                throw new InvalidArgumentException(
                    'All elements returned by a problem provider must be EntityProblem instances.'
                );
            }
            if (! $problem->isValidProblem()) {
                throw new InvalidArgumentException(
                    'All elements returned by a problem provider must be valid EntityProblem instances. Perhaps the problem or data was never set.'
                );
            }

            $entity = $problem->getEntity();
            if (! isset($problems[$entity])) {
                $problems[$entity] = [];
            }
            $problems[$entity][$problem->getIdentifier()] = $problem;
        }

        return $this->problemProviders[$providerKey]['problems'] = $problems;
    }

    /**
     * @param string $entity
     * @return ProblemProviderInterface
     * @throws InvalidArgumentException
     */
    protected function getProblemProviderService(string $serviceName)
    {
        if (! isset($this->problemProviders[$serviceName])) {
            throw new InvalidArgumentException('Requested problem provider \'' . $serviceName . '\' not found');
        }
        if (isset($this->problemProviders[$serviceName]['service'])) {
            return $this->problemProviders[$serviceName]['service'];
        }
        $service = $this->serviceLocator->get($serviceName);
        if (! $service instanceof ProblemProviderInterface) {
            throw new InvalidArgumentException(
                'Requested problem provider \''
                . $serviceName
                . '\' not an instance of ProblemProviderInterface'
            );
        }

        return $this->problemProviders[$serviceName]['service'] = $service;
    }

    /**
     * Autofix any possible problems in all provider services.
     */
    public function autoFixProblems(bool $simulate = true): array
    {
        $problems = [];
        foreach ($this->problemProviders as $providerInfo) {
            $currentProblems = $this->getProblemProviderService($providerInfo['serviceName'])
                ->autoFixProblems($simulate);
            $problems        = ArrayUtils::merge($problems, $currentProblems);
        }
        return $problems;
    }
}
