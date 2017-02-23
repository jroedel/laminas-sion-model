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
use SionModel\Db\Model\SionTable;
use SionModel;
use Patres\Form\DeleteEntityForm;
use SionModel\Entity\Entity;
use Zend\Mvc\Controller\Plugin\FlashMessenger;

class SionModelController extends AbstractActionController
{

    /**
     * Example route: /sm/delete/person/4
     * Only allow deleting if the entity specifically specifies that it is allowed
     * Also check an optional resource/permission identifier if the current user is allowed
     * @param entity string
     * @param entity_id int
     */
    public function deleteEntityAction()
    {
        $entity = $this->getEntity();
        $entityId = (Int)$this->params()->fromRoute('entity_id');
        $defaultRedirectRoute = $this->getDefaultRedirectRoute();
        $entitySpec = $this->getEntitySpecification(); //@todo

        $request = $this->getRequest();
        $sm = $this->getServiceLocator();

        //make sure we have all the information that we need to delete
        if (!$entitySpec->isEnabledForEntityDelete()) {
            $redirectRoute = $defaultRedirectRoute; //@todo check $entitySpec->deleteActionRedirectRoute, since it is not included in isEnabledForEntityDelete, also $entitySpec->sionTable
            $this->flashMessenger()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )
                ->addMessage ( 'This entity cannot be deleted, please check the configuration.');
            $this->redirect()->toRoute($redirectRoute);
        }

        //make sure the user has permission to delete the entity
        if (!is_null($entitySpec->deleteActionAclResource) &&
            !$this->isAllowed($entitySpec->deleteActionAclResource,
                $entitySpec->deleteActionAclPermission ?
                    $entitySpec->deleteActionAclPermission : null)
        ) {
            $redirectRoute = $defaultRedirectRoute;
            $this->flashMessenger()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )
                ->addMessage ( 'You do not have permission to delete this entity.');
            $this->redirect()->toRoute($redirectRoute);
        }

        //make sure our table exists
        $table = $this->getSionTable();

        //make sure our entity exists
        if (!$table->existsEntity($entity, $id)) {
            $this->getResponse()->setStatusCode(401);
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )
                ->addMessage ( 'The entity you\'re trying to delete doesn\'t exists.' );
            $this->redirect()->toRoute('roles');
        }

        $form = new DeleteEntityForm($entitySpec->tableName, $entitySpec->tableKey);
        if ( $request->isPost ()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid() && $form->getData()['entityId'] == $id) {
                $result = $table->deleteEntity($entity, $id);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                    ->addMessage ( 'Entity successfully deleted.' );
                $this->redirect()->toRoute($entitySpec->deleteActionRedirectRoute);
            } else {
                $this->getResponse()->setStatusCode(401); //exists, but either didn't match params or bad csrf
            }
        }

        return new ViewModel ( [
            'form' => $form,
            'entityId' => $entityId,
        ] );
    }

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

        return new ViewModel([
            'changes' => $results,
        ]);
    }
}
