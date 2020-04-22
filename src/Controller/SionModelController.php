<?php

/**
 * Zend Framework (http://framework.zend.com/)
*
* @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
* @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
* @license   http://framework.zend.com/license/new-bsd New BSD License
*/

namespace SionModel\Controller;

use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Cache\Storage\FlushableInterface;
use Zend\View\Model\JsonModel;
use BjyAuthorize\Exception\UnAuthorizedException;
use SionModel\Form\ConfirmForm;
use SionModel\Service\ProblemService;
use SionModel\Service\ChangesCollector;

class SionModelController extends AbstractActionController
{
    protected $services = [];

    public function __construct($services)
    {
        $this->services = $services;
    }

    public function clearPersistentCacheAction()
    {
        $key = $this->params()->fromQuery('key', null);
        if (is_null($key)) {
            throw new UnAuthorizedException();
        }
        $config = $this->getSionModelConfig();
        $apiKeys = isset($config['api_keys']) && is_array($config['api_keys']) ? $config['api_keys'] : [];
        if (! in_array($key, $apiKeys)) {
            throw new UnAuthorizedException();
        }
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
     *
     * @return \Zend\View\Model\ViewModel
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
     * @return \Zend\View\Model\ViewModel
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
            /** @var SionTable $table */
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
