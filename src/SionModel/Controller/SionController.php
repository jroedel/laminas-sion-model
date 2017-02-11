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
use SionModel\Service\ProblemService;
use SionModel\Db\Model\SionTable;
use SionModel\Form\ConfirmForm;

class SionController extends AbstractActionController
{
    /**
     * @todo Add a way to count the amount of errors
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function dataProblemsAction()
    {
        $sm = $this->getServiceLocator();
        /** @var ProblemService $table */
        $table = $sm->get('SionModel\Service\ProblemService');
    
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
        
        $sm = $this->getServiceLocator();
        /** @var ProblemService $table */
        $table = $sm->get('SionModel\Service\ProblemService');
        
        $form = new ConfirmForm();
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost()->toArray ();
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
        $view->setTemplate('sion-model/sion/data-problems');
        return $view;
    }
    
    /**
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function viewChangesAction()
    {
        $sm = $this->getServiceLocator();
        $config = $sm->get('SionModel\Config');
        if (!$sm->has($config['visits_model'])) {
            throw new \InvalidArgumentException('The \'visits_model\' configuration is incorrect.');
        }
        /** @var SionTable $table */
        $table = $sm->get($config['visits_model']);
        $results = $table->getChanges();

        $view = new ViewModel([
            'changes' => $results,
        ]);
        $view->setTemplate('sion-model/view-changes');
        return $view;
    }
}
