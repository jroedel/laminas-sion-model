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
use SionModel\Cache\CacheStatusPayload;
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
     * Report both shared caches as JSON, because neither can be inspected from
     * anywhere else: an APCu *and* an OPcache segment belong to the SAPI that
     * created it, so a CLI probe reads its own empty copy and learns nothing
     * about what the web server is serving.
     *
     * The two failure modes this exists to catch are quiet ones. The persistent
     * cache degrades to misses once apc.shm_size fills; OPcache does not degrade
     * at all but *restarts*, discarding every compiled script, leaving only a
     * counter behind. tools/smoke-prod.sh polls this and warns on both.
     *
     * The APCu keys stay top-level and unchanged — smoke-prod.sh and the smoke
     * suite read them by name — with OPcache added alongside under its own key.
     *
     * The payload itself is built by SionModel\Cache\CacheStatusPayload, not
     * here, because this action is no longer the only thing that answers this
     * URL: App\Controller\CacheStatusController serves it under the Symfony
     * kernel (which the capsule runs and production does not, yet). Both must
     * emit the same document, so neither owns it.
     */
    public function cacheStatusAction()
    {
        $this->assertApiKey();

        return new JsonModel(CacheStatusPayload::build());
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
        //$maxRows bounds the *fetch* as well as the display. It did neither before:
        //the collector was called with no argument, so every table returned its own
        //hard-coded 250 while the view truncated to this number — and the single-table
        //branch below passed the changes_show_all *flag* where an int row count was
        //expected, i.e. `limit(false)`. One number now governs both, which is the only
        //way the page's cost is knowable from its configuration.
        if (! isset($config['changes_show_all']) || $config['changes_show_all']) {
            /** @var ChangesCollector $collector */
            $collector = $this->services[ChangesCollector::class];
            $results = $collector->getAllChanges($maxRows);
        } else {
            if (! isset($this->services[$config['changes_model']])) {
                throw new \InvalidArgumentException('The \'changes_model\' configuration is incorrect.');
            }
            /** @var \SionModel\Db\Model\SionTable $table */
            $table = $this->services[$config['changes_model']];
            $results = $table->getChanges($maxRows);
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
