<?php
namespace SionModel\Service;

use Zend\ServiceManager\ServiceLocatorInterface;
use SionModel\Problem\Model\ProblemTable;
use SionModel\Problem\ProblemProviderInterface;
use SionModel\Problem\EntityProblem;
class ProblemService
{
    /**
     *
     * @var ProblemTable
     */
    protected $problemTable;

    /**
     * An array of service names
     * each array has
     * @var mixed[][]
     */
    protected $problemProviders = [];

    /**
     *
     * @var EntityProblem[][]
     */
    protected $sortedProblems;

    /**
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceManager;

    public function __construct($serviceManager, $problemTable, $problemProviders)
    {
        if (!$serviceManager instanceof ServiceLocatorInterface) {
            throw new \InvalidArgumentException('Invalid service manager passed.');
        }
        $this->serviceManager = $serviceManager;
        $this->problemTable =  $problemTable;

        foreach ($this->problemProviders as $entity => $service) {
            if (!$this->serviceManager->has($service)) {
                throw new \InvalidArgumentException('Invalid provider passed: '.$service);
            }
            $this->problemProviders[] = [
                'entity' => $entity,
                'serviceName' => $service,
                'service' => null,
                'problems' => [],
                'problemsQueried' => false,
            ];
        }
    }

    /**
     *
     * @param array $entites
     */
    public function getProblems(array $entites = null)
    {
        $problems = [];
        foreach ($this->problemProviders as $key => $provider) {
            $currentProblems = $this->getEntityProblems($key);
            foreach ($currentProblems as $problem) {
                $entity = $problem->getEntity();
                if (in_array($problem->getEntity(), $entites)) {
                    $problems[$problem->getIdentifier()] = $problem;
                }
            }
        }
        //sort by entity

        return $problems;
    }

    /**
     *
     * @param string $providerKey
     * @return EntityProblem[]
     * @throws \InvalidArgumentException
     */
    protected function getEntityProblems($providerKey)
    {
        if (!isset($this->problemProviders[$providerKey])) {
            throw new \InvalidArgumentException('Invalid provider requested: '.$providerKey);
        }

        if ($this->problemProviders[$providerKey]['problemsQueried']) {
            return $this->problemProviders[$providerKey]['problems'];
        } else {
            if (!is_null($this->problemProviders[$providerKey]['service'])) {
                $service = $this->problemProviders[$providerKey]['service'];
            } else {
                $service = $this->getProblemProviderService($providerKey);
            }
            $this->problemProviders[$providerKey]['problems'] = $service->getProblems();
            $this->problemProviders[$providerKey]['problemsQueried'] = true;
            return $this->problemProviders[$providerKey]['problems'];
        }
    }

    /**
     *
     * @param string $entity
     * @return ProblemProviderInterface
     * @throws \InvalidArgumentException
     */
    protected function getProblemProviderService($entity)
    {
        if (!isset($this->problemProviders[$entity])) {
            throw new \InvalidArgumentException('Requested problem provider not found');
        }
        if (!isset($this->initializedProviders[$entity]) || !$this->initializedProviders[$entity]) {
            $this->problemProviderServices[$entity] = $this->serviceManager->get($service);
            $this->initializedProviders[$entity] = true;
        }
        return $this->problemProviderServices[$entity];
    }
}