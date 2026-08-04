<?php

/**
 * Zend Framework (http://framework.zend.com/)
*
* @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
* @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
* @license   http://framework.zend.com/license/new-bsd New BSD License
*/

namespace SionModel\Controller;

use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\View\Model\JsonModel;
use BjyAuthorize\Exception\UnAuthorizedException;
use SionModel\Form\ConfirmForm;
use SionModel\Service\ProblemService;
use SionModel\Service\ChangesCollector;

class SionModelController extends AbstractActionController
{
    use MaintenanceKeyTrait;

    protected $services = [];

    public function __construct($services)
    {
        $this->services = $services;
    }

    public function clearPersistentCacheAction()
    {
        $this->assertApiKey();
        $cache = $this->getPersistentCache();
        if (! is_object($cache)) {
            throw new \Exception('Please configure the persistent cache to clear the cache.');
        }
        if (! $cache instanceof FlushableInterface) {
            throw new \Exception('Configured persistent cache does not support flushing.');
        }
        if (! $cache->flush()) {
            $this->getResponse()->setStatusCode(401);
            $message = 'Unsuccessful flush';
        } else {
            $message = 'Success';
        }
        return new JsonModel(['message' => $message]);
    }

    /**
     * Report APCu occupancy as JSON. The persistent cache degrades to misses
     * once the shared segment fills (apc.shm_size), and only the web SAPI
     * can see its own segment — CLI probes look at a different one.
     */
    public function cacheStatusAction()
    {
        $this->assertApiKey();
        if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
            return new JsonModel(['apcuEnabled' => false]);
        }
        $sma = apcu_sma_info(true);
        $cache = apcu_cache_info(true);
        $totalBytes = (int)($sma['num_seg'] * $sma['seg_size']);
        $availBytes = (int)$sma['avail_mem'];
        $usedBytes = $totalBytes - $availBytes;
        return new JsonModel([
            'apcuEnabled' => true,
            'totalBytes' => $totalBytes,
            'usedBytes' => $usedBytes,
            'availBytes' => $availBytes,
            'percentUsed' => $totalBytes > 0 ? round($usedBytes * 100 / $totalBytes, 1) : null,
            'entries' => isset($cache['num_entries']) ? (int)$cache['num_entries'] : null,
            'hits' => isset($cache['num_hits']) ? (int)$cache['num_hits'] : null,
            'misses' => isset($cache['num_misses']) ? (int)$cache['num_misses'] : null,
            'expunges' => isset($cache['expunges']) ? (int)$cache['expunges'] : null,
            'uptimeSeconds' => isset($cache['start_time']) ? time() - (int)$cache['start_time'] : null,
            'phpVersion' => PHP_VERSION,
            'largestEntries' => $this->getLargestApcuEntries(),
        ]);
    }

    /**
     * The biggest cache entries, largest first, as [key => bytes].
     *
     * Aggregate occupancy says the segment is full; it does not say which key
     * filled it. That distinction is what decides whether the fix is a bigger
     * segment or a narrower query, and it is also how the
     * sion_model.max_cached_item_size budget gets tuned against real data
     * rather than a guess. `mem_size` is what APCu actually allocated for the
     * entry, so unlike a serialize() estimate it needs no interpretation.
     *
     * @param int $limit
     * @return array<string, int>
     */
    protected function getLargestApcuEntries($limit = 15)
    {
        //the `true` variant of apcu_cache_info() omits the entry list, so this
        //is the one call in this action that has to walk every entry
        $info = apcu_cache_info();
        if (! isset($info['cache_list']) || ! is_array($info['cache_list'])) {
            return [];
        }
        $sizes = [];
        foreach ($info['cache_list'] as $entry) {
            if (! isset($entry['info'])) {
                continue;
            }
            $sizes[$entry['info']] = isset($entry['mem_size']) ? (int)$entry['mem_size'] : 0;
        }
        arsort($sizes);
        return array_slice($sizes, 0, $limit, true);
    }

    /**
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function dataProblemsAction()
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
    public function autoFixDataProblemsAction()
    {
        $simulate = true;

        /** @var ProblemService $table */
        $table = $this->services[ProblemService::class];

        $form = new ConfirmForm();
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
            'problems' => $problems,
            'isSimulation' => $simulate,
            'form' => $form,
        ]);
        $view->setTemplate('sion-model/sion-model/data-problems');
        return $view;
    }

    /**
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function viewChangesAction()
    {
        $config = $this->getSionModelConfig();
        $maxRows = (isset($config['changes_max_rows']) &&
            (is_numeric($config['changes_max_rows']) || ! isset($config['changes_max_rows']))) ?
            (int)$config['changes_max_rows'] : 500;
        if (! isset($config['changes_show_all']) || $config['changes_show_all']) {
            /** @var ChangesCollector $collector */
            $collector = $this->services[ChangesCollector::class];
            $results = $collector->getAllChanges();
        } else {
            if (! isset($this->services[$config['changes_model']])) {
                throw new \InvalidArgumentException('The \'changes_model\' configuration is incorrect.');
            }
            /** @var \SionModel\Db\Model\SionTable $table */
            $table = $this->services[$config['changes_model']];
            $getAllChanges = key_exists('changes_show_all', $config) && ! is_null($config['changes_show_all']) ?
                (bool)$config['changes_show_all'] : false;
            $results = $table->getChanges($getAllChanges);
        }
        return new ViewModel([
            'changes'       => $results,
            'maxRows'       => $maxRows,
            'showEntity'    => true,
        ]);
    }

    public function phpInfoAction()
    {
        return [];
    }

    /**
     * Gate a maintenance endpoint behind the sion_model.api_keys config,
     * so deploy hooks can call it without a session.
     *
     * @throws UnAuthorizedException
     */
    protected function assertApiKey()
    {
        $config = $this->getSionModelConfig();
        $this->assertApiKeyIn(
            isset($config['api_keys']) && is_array($config['api_keys']) ? $config['api_keys'] : []
        );
    }

    protected function getSionModelConfig()
    {
        if (isset($this->services['SionModel\Config'])) {
            return $this->services['SionModel\Config'];
        }
        return [];
    }

    protected function getPersistentCache()
    {
        if (isset($this->services['SionModel\PersistentCache'])) {
            return $this->services['SionModel\PersistentCache'];
        }
        return null;
    }
}
