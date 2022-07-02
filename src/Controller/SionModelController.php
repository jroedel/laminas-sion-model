<?php

declare(strict_types=1);

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 */

namespace SionModel\Controller;

use BjyAuthorize\Exception\UnAuthorizedException;
use Exception;
use InvalidArgumentException;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use SionModel\Db\Model\SionTable;
use SionModel\Form\ConfirmForm;
use SionModel\Service\ChangesCollector;
use SionModel\Service\ProblemService;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_numeric;
use function is_object;

class SionModelController extends AbstractActionController
{
    public function __construct(protected array $services = [])
    {
    }

    public function clearPersistentCacheAction(): JsonModel
    {
        $key = $this->params()->fromQuery('key');
        if (! isset($key)) {
            throw new UnAuthorizedException();
        }
        $config  = $this->getSionModelConfig();
        $apiKeys = isset($config['api_keys']) && is_array($config['api_keys']) ? $config['api_keys'] : [];
        if (! in_array($key, $apiKeys)) {
            throw new UnAuthorizedException();
        }
        $cache = $this->getPersistentCache();
        if (! is_object($cache)) {
            throw new Exception('Please configure the persistent cache to clear the cache.');
        }
        if (! $cache instanceof FlushableInterface) {
            throw new Exception('Configured persistent cache does not support flushing.');
        }
        if (! $cache->flush()) {
            $this->getResponse()->setStatusCode(401);
            $message = 'Unsuccessful flush';
        } else {
            $message = 'Success';
        }
        return new JsonModel(['message' => $message]);
    }

    public function dataProblemsAction(): ModelInterface|ResponseInterface|array
    {
        /** @var ProblemService $table */
        $table = $this->services[ProblemService::class];

        $problems = $table->getCurrentProblems();

        return new ViewModel([
            'problems' => $problems,
        ]);
    }

    /**
     * Autofix data problems. User must accept the changes to be applied.
     */
    public function autoFixDataProblemsAction(): ModelInterface|ResponseInterface|array
    {
        $simulate = true;

        /** @var ProblemService $table */
        $table = $this->services[ProblemService::class];

        $form    = new ConfirmForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) { //check the CSRF value
                $simulate = false;
            }
        }
        $problems = $table->autoFixProblems($simulate);

        $view = new ViewModel([
            'problems'     => $problems,
            'isSimulation' => $simulate,
            'form'         => $form,
        ]);
        $view->setTemplate('sion-model/sion-model/data-problems');
        return $view;
    }

    /**
     * @todo The logic here doesn't add up
     */
    public function viewChangesAction(): ModelInterface|ResponseInterface|array
    {
        $config  = $this->getSionModelConfig();
        $maxRows = isset($config['changes_max_rows']) && (is_numeric($config['changes_max_rows']))
            ? (int) $config['changes_max_rows']
            : 500;
        if (! isset($config['changes_show_all']) || $config['changes_show_all']) {
            /** @var ChangesCollector $collector */
            $collector = $this->services[ChangesCollector::class];
            $results   = $collector->getAllChanges();
        } else {
            if (! isset($this->services[$config['changes_model']])) {
                throw new InvalidArgumentException('The \'changes_model\' configuration is incorrect.');
            }
            /** @var SionTable $table */
            $table         = $this->services[$config['changes_model']];
            $getAllChanges = array_key_exists('changes_show_all', $config) && $config['changes_show_all'];
            $results       = $table->getChanges($getAllChanges ? 0 : 250);
        }
        return new ViewModel([
            'changes'    => $results,
            'maxRows'    => $maxRows,
            'showEntity' => true,
        ]);
    }

    /**
     * @psalm-return array<empty, empty>
     */
    public function phpInfoAction(): ModelInterface|ResponseInterface|array
    {
        return [];
    }

    protected function getSionModelConfig(): array
    {
        if (isset($this->services['SionModel\Config'])) {
            return $this->services['SionModel\Config'];
        }
        return [];
    }

    protected function getPersistentCache(): StorageInterface|null
    {
        if (isset($this->services['SionModel\PersistentCache'])) {
            return $this->services['SionModel\PersistentCache'];
        }
        return null;
    }
}
