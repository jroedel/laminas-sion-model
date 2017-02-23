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
use SionModel\Service\EntitiesService;
use SionModel;
use Patres\Form\DeleteEntityForm;
use SionModel\Form\SionForm;
use SionModel\Entity\Entity;
use Zend\Mvc\Controller\Plugin\FlashMessenger;
use JTranslate\Controller\Plugin\NowMessenger;

class SionController extends AbstractActionController
{
    /**
     * @var Entity $entitySpecification
     */
    protected $entitySpecification;
    
    /**
     * @var SionTable $sionTable
     */
    protected $sionTable;
    
    
    /**
    * @var string $entity
    */
    protected $entity;
    
    /**
    * @var defaultRedirectRoute $defaultRedirectRoute
    */
    protected $defaultRedirectRoute;
    
    /**
    * @var array $sionModelConfig
    */
    protected $sionModelConfig;
    
    /**
     * @param string $entity
     * @throws \Exception
     */
    public function __construct($entity)
    {
        $this->setEntity($entity);
    }
    
    public function createAction()
    {
        $sm = $this->getServiceLocator ();
        /** @var SionTable $table **/
        $table = $this->getSionTable();
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
    
        if (is_null($entitySpec->createActionForm)) {
            throw new \InvalidArgumentException('If the createAction for \''.$entity.'\' is to be used, it must specify the create_action_form configuration.');
        }
        /** @var SionForm $form **/
        if ($sm->has($entitySpec->createActionForm)) {
            $form = $sm->get($entitySpec->createActionForm);
        } elseif (class_exists($entitySpec->createActionForm)) {
            //@todo test this line
            $form = new $entitySpec->createActionForm;
        } else {
            throw new \InvalidArgumentException('Invalid create_action_form specified for \''.$entity.'\' entity.');
        }
        
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (!($newId = $table->createEntity($entity, $data))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                        ->addMessage ( ucwords($entity).' successfully created.' );
                    //check if user has the redirect route set
                    if (!is_null($entitySpec->createActionRedirectRoute)) {
                        $this->redirect ()->toRoute ($entitySpec->createActionRedirectRoute, 
                            !is_null($entitySpec->createActionRedirectRouteKey) ? 
                            [$entitySpec->createActionRedirectRouteKey => $newId] : []);
                    } else {
                        $this->redirect ()->toRoute ($this->getDefaultRedirectRoute());
                    }
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        $view = new ViewModel([
            'form' => $form,
        ]);
        
        //check if the user has the createActionTemplate option set, if not they'll go to the default
        if (!is_null($entitySpec->createActionTemplate)) {
            $template = $entitySpec->createActionTemplate;
            $view->setTemplate($template);
        }
        return $view;
    }
    
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
        $entitySpec = $this->getEntitySpecification();
        
        $request = $this->getRequest();
        $sm = $this->getServiceLocator();
        
        //make sure we have all the information that we need to delete
        if (!$entitySpec->isEnabledForEntityDelete()) {
            $redirectRoute = $defaultRedirectRoute;
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
     * Get the entitySpecification value
     * @return Entity
     */
    public function getEntitySpecification()
    {
        if (is_null($this->entitySpecification)) {
            $entity = $this->getEntity();
            $sm = $this->getServiceLocator();
            /** @var EntitiesService $entitiesService */
            $entitiesService = $sm->get('SionModel\Service\EntitiesService');
            $entities = $entitiesService->getEntities();
            if (!key_exists($entity, $entities)) {
                throw new \Exception('Invalid entity given\''.$entity.'\'');
            }
            $this->setEntitySpecification($entities[$entity]);
        }
        return $this->entitySpecification;
    }
    
    /**
     *
     * @param Entity $entitySpecification
     * @return self
     */
    public function setEntitySpecification(Entity $entitySpecification)
    {
        $this->entitySpecification = $entitySpecification;
        return $this;
    }
    
    /**
     * Get the sionTable value
     * @return SionTable
     */
    public function getSionTable()
    {
        if (is_null($this->sionTable)) {
            $sm = $this->getServiceLocator();
            $entitySpec = $this->getEntitySpecification();
            if (!$sm->has($entitySpec->sionModelClass)) {
                throw new \Exception('Invalid SionModel class set for entity \''.$this->getEntity().'\'');
            }
            $this->setSionTable($sm->get($entitySpec->sionModelClass));
        }
        return $this->sionTable;
    }
    
    /**
     * @param SionTable $sionTable
     * @return self
     */
    public function setSionTable($sionTable)
    {
        if (!$sionTable instanceof SionTable) {
            throw new \Exception('Expecting SionModelClass to be a SionTable instance.');
        }
        $this->sionTable = $sionTable;
        return $this;
    }

    /**
     * Get the entity value
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }
    
    /**
     *
     * @param string $entity
     * @return self
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * Get the defaultRedirectRoute value
     * @return defaultRedirectRoute
     */
    public function getDefaultRedirectRoute()
    {
        if (is_null($this->defaultRedirectRoute)) {
            $config = $this->getSionModelConfig();
            $redirectRoute = $config['default_redirect_route'];
            $this->setDefaultRedirectRoute($redirectRoute);
        }
        return $this->defaultRedirectRoute;
    }
    
    /**
     *
     * @param defaultRedirectRoute $defaultRedirectRoute
     * @return self
     */
    public function setDefaultRedirectRoute($defaultRedirectRoute)
    {
        $this->defaultRedirectRoute = $defaultRedirectRoute;
        return $this;
    }

    /**
     * Get the sionModelConfig value
     * @return array
     */
    public function getSionModelConfig()
    {
        if (is_null($this->sionModelConfig)) {
            $config = $sm->get('SionModel\Config');
            $this->setSionModelConfig($config);
        }
        return $this->sionModelConfig;
    }
    
    /**
     *
     * @param array $sionModelConfig
     * @return self
     */
    public function setSionModelConfig(array $sionModelConfig)
    {
        $this->sionModelConfig = $sionModelConfig;
        return $this;
    }
}
