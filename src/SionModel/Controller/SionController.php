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
use SionModel\Form\SionForm;
use SionModel\Entity\Entity;
use SionModel\Form\DeleteEntityForm;
use Zend\Mvc\Controller\Plugin\FlashMessenger;
use JTranslate\Controller\Plugin\NowMessenger;
use SionModel\Form\TouchForm;
use Zend\View\Model\JsonModel;
use Zend\Form\Form;

class SionController extends AbstractActionController
{
    /**
     * @var Entity[] $entitySpecifications
     */
    protected $entitySpecifications;
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

    protected $actionRouteKeys = [
        'show'      => 'showRouteKey',
        'edit'      => 'editRouteKey',
        'delete'    => 'deleteRouteKey',
        'touch'     => 'touchRouteKey',
        'touchJson' => 'touchJsonRouteKey',
    ];

    /**
     * @param string $entity
     * @throws \Exception
     */
    public function __construct($entity = null)
    {
        //@todo should we throw an exception if we don't get an $entity?
        $this->setEntity($entity);
    }

    public function indexAction()
    {
        /** @var SionTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $objects    = $table->getObjects($entity);
        $view = new ViewModel([
            'entity'    => $entity,
            'entitySpec'=> $entitySpec,
            'objects'   => $objects,
        ]);

        return $view;
    }

    /**
     * Retrieve a requested entityObject by the route parameter specified by the entity's show_route_key.
     * The consumer of the SionController should implement the view template
     * @todo introduce resource-level checks
     * @throws \Exception
     * @return \Zend\View\Model\ViewModel
     */
    public function showAction()
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        if (is_null($entitySpec->showRouteKey)) {
            throw new \Exception("Please set the show_route_key config key of $entity in order to use the showAction.");
        }
        $id = (Int)$this->getEntityIdParam('show');
        if (!$id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucwords($entity).' not found.');
            $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
            $this->redirect ()->toRoute ( $redirectRoute);
        }

        /** @var SionTable $table */
        $table = $this->getSionTable();
        if (!$table->existsEntity($entity, $id)) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( ucfirst($entity).' not found.' );
            $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
            $this->redirect ()->toRoute ( $redirectRoute );
        }

        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (is_null($entityObject)) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( ucfirst($entity).' not found.' );
            $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
            $this->redirect ()->toRoute ( $redirectRoute );
        }

        $changes = $table->getEntityChanges($entity, $id);

        $table->registerVisit($entity, $entityObject[$entitySpec->entityKeyField]);

        //@todo enable suggest form
//         $sm = $this->getServiceLocator ();
//         /** @var SionForm $suggestForm **/
//         if (is_null($entitySpec->suggestForm)) {
//             $suggestForm = $sm->get('SionModel\Form\SuggestForm');
//         } elseif ($sm->has($entitySpec->suggestForm)) {
//             $suggestForm = $sm->get($entitySpec->suggestForm);
//         } elseif (class_exists($entitySpec->suggestForm)) {
//             $suggestFormName = $entitySpec->suggestForm;
//             $suggestForm = new $suggestFormName;
//         } else {
//             throw new \InvalidArgumentException('Invalid suggest_form specified for \''.$entity.'\' entity.');
//         }

        $view = new ViewModel([
            'entityId'      => $id,
            'entity'        => $entityObject,
            'changes'       => $changes,
//             'suggestForm'   => $suggestForm,
//             'deviceType'    => $deviceType,
        ]);

        //check if the user has the showActionTemplate option set, if not they'll go to the default
        if (!is_null($entitySpec->showActionTemplate)) {
            $template = $entitySpec->showActionTemplate;
            $view->setTemplate($template);
        }
        return $view;
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
            $form = new $entitySpec->createActionForm;
        } else {
            throw new \InvalidArgumentException('Invalid create_action_form specified for \''.$entity.'\' entity.');
        }

        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $this->getPostDataForCreateAction();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                //if we have a dataHandler registered, call it @deprecated
                if (!is_null($entitySpec->createActionValidDataHandler)) {
                    $handlerFunction = $entitySpec->createActionValidDataHandler;
                    if (!method_exists($this, $handlerFunction) ||
                        method_exists('SionController', $handlerFunction)
                    ) {
                        throw new \Exception('Invalid create_action_valid_data_handler set for entity \''.$entity.'\'');
                    }
                    //don't return here so that if the handler doesn't redirect, we send them back to the form
                    $this->$handlerFunction($data, $form);
                } else { //if we have no data handler, we'll do it ourselves
                    $this->createEntityPostFormValidation($data, $form);
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
     * Return an array of data to be passed to the setData function of the CreateEntity form
     * @return array
     */
    public function getPostDataForCreateAction()
    {
        return $this->getRequest()->getPost()->toArray();
    }

    /**
     * Creates a new entity, notifies the user via flash messenger and redirects.
     * @param mixed[] $data
     * @param Form $form
     */
    public function createEntityPostFormValidation($data, $form)
    {
        $entity = $this->getEntity();
        $table = $this->getSionTable();
        if (!($newId = $table->createEntity($entity, $data))) {
            $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
        } else {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
            ->addMessage ( ucwords($entity).' successfully created.' );
            $this->redirectAfterCreate((int) $newId);
        }
    }

    /**
     * This function is called after a successful entity creation to redirect the user.
     * May be overwritten by a child Controller to add functionality.
     * @param int $newId
     * @throws \Exception
     */
    public function redirectAfterCreate($newId)
    {
        $sm = $this->getServiceLocator ();
        /** @var SionTable $table **/
        $table = $this->getSionTable();
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();

        //check if user has the redirect route set
        if (!is_null($entitySpec->createActionRedirectRoute)) {
            if (is_null($entitySpec->createActionRedirectRouteKeyField) ||
                $entitySpec->createActionRedirectRouteKeyField == $entitySpec->entityKeyField ||
                is_null($entitySpec->createActionRedirectRouteKey)
            ) {
                $this->redirect ()->toRoute ($entitySpec->createActionRedirectRoute,
                    !is_null($entitySpec->createActionRedirectRouteKey) ?
                    [$entitySpec->createActionRedirectRouteKey => $newId] : []);
            } else {
                $entityObj = $table->getObject($entity, $newId);
                if (!key_exists($entitySpec->createActionRedirectRouteKeyField, $entityObj)) {
                    throw new \Exception('create_action_redirect_route_key_field is misconfigured for entity \''.$entity.'\'');
                }
                $this->redirect ()->toRoute ($entitySpec->createActionRedirectRoute,
                    [$entitySpec->createActionRedirectRouteKey => $entityObj[$entitySpec->createActionRedirectRouteKeyField]]);
            }
        } else {
            $this->redirect ()->toRoute ($this->getDefaultRedirectRoute());
        }
    }

    /**
     * Standard edit action which checks edit_route_key input, looks up the entity,
     * checks for a post, validates the data with the form and submits the change if the form validates.
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @return ViewModel
     */
    public function editAction()
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        if (is_null($entitySpec->editRouteKey)) {
            throw new \Exception("Please set the edit_route_key config key of $entity in order to use the editAction.");
        }
        $id = (Int)$this->getEntityIdParam('edit');
        //if the entity doesn't exist, redirect to the index or the default route
        if (! $id) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( ucfirst($entity).' not found.' );
            $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
            $this->redirect ()->toRoute ( $redirectRoute);
        }
        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (is_null($entityObject)) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( ucfirst($entity).' not found.' );
            $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
            $this->redirect ()->toRoute ( $redirectRoute );
        }

        if (is_null($entitySpec->editActionForm)) {
            throw new \InvalidArgumentException('If the editAction for \''.$entity.'\' is to be used, it must specify the edit_action_form configuration.');
        }
        $sm = $this->getServiceLocator();
        /** @var SionForm $form **/
        if ($sm->has($entitySpec->editActionForm)) {
            $form = $sm->get($entitySpec->editActionForm);
        } elseif (class_exists($entitySpec->editActionForm)) {
            $className = $entitySpec->editActionForm;
            $form = new $className();
        } else {
            throw new \InvalidArgumentException('Invalid edit_action_form specified for \''.$entity.'\' entity.');
        }

        $request = $this->getRequest ();
        if ($request->isPost ()) {
            $data = $this->getPostDataForEditAction();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                $this->updateEntityPostFormValidation($id, $data, $form);
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($entityObject);
        }
        $deleteForm = new DeleteEntityForm();
        $view = new ViewModel([
            'entity'    => $entityObject,
            'entityId'  => $id,
            'form'      => $form,
            'deleteForm'=> $deleteForm,
        ]);

        //check if the user has the editActionTemplate option set, if not they'll go to the default
        if (!is_null($entitySpec->editActionTemplate)) {
            $template = $entitySpec->editActionTemplate;
            $view->setTemplate($template);
        }
        return $view;
    }

    /**
     * Return an array of data to be passed to the setData function of the EditEntity form
     * @return array
     */
    public function getPostDataForEditAction()
    {
        return $this->getRequest()->getPost()->toArray();
    }

    /**
     * Updates a given entity, notifies the user via flash messenger and calls the redirect function.
     * @param int $id
     * @param mixed[] $data
     * @param Form $form
     * @throws \Exception
     */
    public function updateEntityPostFormValidation($id, $data, $form)
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        /** @var SionTable $table **/
        $table = $this->getSionTable();
        $table->updateEntity($entity, $id, $data);
        $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( ucfirst($entity).' successfully updated.' );
        $this->redirectAfterEdit($id);
    }

    /**
     * Redirects the user after successfully editing an entity.
     * @param int $id
     * @throws \Exception
     */
    public function redirectAfterEdit($id)
    {
        $entitySpec = $this->getEntitySpecification();
        $entityObject = $this->getEntityObject($id);
        if ($entitySpec->showRouteKey && $entitySpec->showRouteKey &&
            $entitySpec->showRouteKeyField
        ) {
            if (!key_exists($entitySpec->showRouteKeyField, $entityObject)) {
                throw new \Exception("show_route_key_field config for entity '$entity' refers to a key that doesn't exist");
            }
            $this->redirect ()->toRoute ($entitySpec->showRoute,
                    [$entitySpec->showRouteKey => $entityObject[$entitySpec->showRouteKeyField]]);
        } elseif ($entitySpec->indexRoute) {
            $this->redirect ()->toRoute ($entitySpec->indexRoute);
        } else {
            $this->redirect ()->toRoute ( $this->getDefaultRedirectRoute() );
        }
    }

    /**
     * @todo test!
     * @throws \Exception
     * @return \Zend\View\Model\ViewModel
     */
    public function touchAction()
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $id = (Int)$this->getEntityIdParam('touch');
        //if the entity doesn't exist, redirect to the index or the default route
        if (! $id) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( ucfirst($entity).' not found.' );
            $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
            $this->redirect ()->toRoute ( $redirectRoute);
        }
        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (is_null($entityObject)) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( ucfirst($entity).' not found.' );
            $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
            $this->redirect ()->toRoute ( $redirectRoute );
        }

        $form = new TouchForm();

        $request = $this->getRequest ();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var SionTable $table **/
                $table = $this->getSionTable();
                $fieldToTouch = $this->whichFieldToTouch();
                $table->touchEntity($entity, $id, $fieldToTouch);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( ucfirst($entity).' successfully marked up-to-date.' );
                if ($entitySpec->showRouteKey && $entitySpec->showRouteKey &&
                    $entitySpec->showRouteKeyField
                ) {
                    if (!key_exists($entitySpec->showRouteKeyField, $entityObject)) {
                        throw new \Exception("show_route_key_field config for entity '$entity' refers to a key that doesn't exist");
                    }
                    $this->redirect ()->toRoute ($entitySpec->showRoute,
                        [$entitySpec->showRouteKey => $entityObject[$entitySpec->showRouteKeyField]]);
                } else {
                    $this->redirect ()->toRoute ( $this->getDefaultRedirectRoute() );
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($entityObject);
        }
        return new ViewModel([
            'entity'    => $entityObject,
            'entityId'  => $id,
            'form'      => $form,
        ]);
    }

    /**
     * Touch the entity, and return the status through the HTTP code
     * @return \Zend\Stdlib\ResponseInterface|\SionModel\Controller\JsonModel
     */
    public function touchJsonAction()
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();

        $id = (Int)$this->getEntityIdParam('touchJson');
        if (!isset($id) || is_null($id)) {
            return $this->sendFailedMessage('Invalid id passed.');
        }
        $callback = $this->params ()->fromQuery ('callback', null);
        if (is_null($callback)) {
            return $this->sendFailedMessage('All requests must include a callback function set as a query parameter \'callback\'.');
        }

        $form = new TouchForm();

        $request = $this->getRequest();
        if (!$request->isPost ()) {
            return $this->sendFailedMessage('Please use post method.');
        }
        //$data = Json::decode($request->getContent(), Json::TYPE_ARRAY);
        $data = $request->getPost()->toArray();
        $form->setData($data);
        if (!$form->isValid()) {
            return $this->sendFailedMessage('The following fields are invalid: '.
                implode(', ', array_keys($form->getInputFilter()->getInvalidInput())).
                    $request->getContent());
        }

        $sm = $this->getServiceLocator();
        /** @var SionTable $table */
        $table = $this->getSionTable();
        $fieldToTouch = $this->whichFieldToTouch();
        $return = $table->touchEntity($entity, $id, $fieldToTouch);

        $view = new JsonModel([
            'return'    => $return,
            'field'     => $fieldToTouch,
            'message'   => 'Success',
        ]);
        $view->setJsonpCallback($callback);
        return $view;
    }

    /**
     * If the form has been posted, confirm the CSRF. If all is well, delete the entity.
     * If the request is a GET, ask the user to confirm the deletion
     * @return \Zend\View\Model\ViewModel
     *
     * @todo Create a view template to ask for confirmation
     * @todo check if client expects json, and make it AJAX friendly
     */
    public function deleteAction()
    {
        $entity = $this->getEntity();
        $id = (Int)$this->getEntityIdParam('delete');
        $entitySpec = $this->getEntitySpecification();

        $request = $this->getRequest();
        $sm = $this->getServiceLocator();

        //make sure we have all the information that we need to delete
        if (!$entitySpec->isEnabledForEntityDelete()) {
            $this->flashMessenger()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )
            ->addMessage ( 'This entity cannot be deleted, please check the configuration.');
            $this->redirectAfterDelete(false);
        }

        //make sure the user has permission to delete the entity
        if (!is_null($entitySpec->deleteActionAclResource) &&
            !$this->isAllowed($entitySpec->deleteActionAclResource,
                $entitySpec->deleteActionAclPermission ?
                $entitySpec->deleteActionAclPermission : null)
        ) {
            $this->flashMessenger()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )
            ->addMessage ( 'You do not have permission to delete this entity.');
            $this->redirectAfterDelete(false);
        }

        //make sure our table exists
        $table = $this->getSionTable();

        //make sure our entity exists
        if (!$table->existsEntity($entity, $id)) {
            $this->getResponse()->setStatusCode(401);
            $this->flashMessenger()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )
                ->addMessage ( 'The entity you\'re trying to delete doesn\'t exists.' );
            $this->redirectAfterDelete(false);
        }

        $form = new DeleteEntityForm();
        if ( $request->isPost ()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid()) {
                $result = $table->deleteEntity($entity, $id);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                ->addMessage ( 'Entity successfully deleted.' );
                $this->redirectAfterDelete(true);
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                $this->getResponse()->setStatusCode(401); //exists, but either didn't match params or bad csrf
            }
        }

        //set the form action url
        $form->setAttribute('action', $this->getRequest()->getRequestUri());
        $entityObject = $this->getEntityObject($id);

        $view = new ViewModel ( [
            'form' => $form,
            'entity' => $entity,
            'entityId' => $id,
            'entityObject' => $entityObject,
        ] );
        //@todo first check if the Controller has a default template, if not
        //look for a configuration value, if not, use the default template
        $view->setTemplate('sion-model/sion-model/delete');
        return $view;
    }

    /**
     * Called in order to redirect after error or success on deleteAction
     * @param bool $actionWasSuccessful
     * @throws \Exception
     */
    protected function redirectAfterDelete($actionWasSuccessful = true)
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        if (!is_null($entitySpec->deleteActionRedirectRoute)) {
            $this->redirect()->toRoute($entitySpec->deleteActionRedirectRoute);
        } else {
            if (is_null($defaultRedirectRoute = $this->getDefaultRedirectRoute())) {
                throw new \Exception("Please configure the deleteActionRedirectRoute for $entity, or the sion_model default_redirect_route.");
            }
            $this->redirect()->toRoute($defaultRedirectRoute);
        }
    }

    /**
     * Get the value of the entityId route parameter given the action. If no parameter is
     * set in the Request, $default is returned
     * @param string $action
     * @param mixed $default
     * @throws \Exception
     * @return mixed
     */
    protected function getEntityIdParam($action = 'show', $default = null)
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $actionRouteKey = null;
        if (key_exists($action, $this->actionRouteKeys)) {
            $actionRouteKey = $this->actionRouteKeys[$action];
            if (!is_null($entitySpec->$actionRouteKey)) {
                return $this->params()->fromRoute($entitySpec->$actionRouteKey, $default);
            }
        } else {
            $actionRouteKey = 'defaultRouteKey';
        }
        if (!is_null($entitySpec->defaultRouteKey)) {
            return $this->params()->fromRoute($entitySpec->defaultRouteKey, $default);
        }
        throw new \Exception("Please configure the $actionRouteKey for the $entity entity to use the $action action.");
    }

    protected function sendFailedMessage($message)
    {
        $response = $this->getResponse();
        $response->setStatusCode(401);
        $response->sendHeaders();
        $response->setContent($message);
        return $response;
    }

    /**
     * Election rules:
     * 1. If there is a route parameter to specify the field, and the field exists, use it.
     * 2. Else, if the touchDefaultField exists, use it.
     * 3. Else, touch the entityKeyField if it exists
     */
    protected function whichFieldToTouch()
    {
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $touchField = null;
        if (!is_null($entitySpec->touchFieldRouteKey)) {
            $touchField = $this->params ()->fromRoute ($entitySpec->touchFieldRouteKey);
            if (key_exists($touchField, $entitySpec->updateColumns)) {
                return $touchField;
            }
        }
        if (!is_null($entitySpec->touchDefaultField) && key_exists($entitySpec->touchDefaultField, $entitySpec->updateColumns)) {
            return $entitySpec->touchDefaultField;
        }
        if (!is_null($entitySpec->entityKeyField) && key_exists($entitySpec->entityKeyField, $entitySpec->updateColumns)) {
            return $entitySpec->entityKeyField;
        }
        throw new \Exception("Cannot find a field to touch for entity '$entity'");
    }

    /**
     * Get the entitySpecification value
     * @return Entity
     */
    public function getEntitySpecification()
    {
        if (is_null($this->entitySpecification)) {
            $entity = $this->getEntity();
            $entities = $this->getEntitySpecifications();
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
     * Get the array of entity specifications from config
     * @return \SionModel\Entity\Entity[]
     */
    public function getEntitySpecifications()
    {
        if (is_null($this->entitySpecifications)) {
            $sm = $this->getServiceLocator();
            /** @var EntitiesService $entitiesService */
            $entitiesService = $sm->get('SionModel\Service\EntitiesService');
            $this->entitySpecifications = $entitiesService->getEntities();
        }
        return $this->entitySpecifications;
    }

    public function getEntityObject($id)
    {
        $table = $this->getSionTable();
        return $table->getObject($this->getEntity(), $id);
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
            $sm = $this->getServiceLocator();
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
